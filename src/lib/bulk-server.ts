import ExcelJS from "exceljs";
import { parse } from "csv-parse/sync";
import { Cell, checkHeaders, checkRow, MAX_BULK_ROWS, ParsedDocument, RowCheck } from "./bulk";
import { audit, Db, sql, withTx } from "./db";
import { upsertParty, UserError } from "./documents";
import { DocType, registerByType } from "./registers";

export const MAX_BULK_FILE_BYTES = 10 * 1024 * 1024;

export interface SheetData {
  headers: Cell[];
  rows: { number: number; cells: Cell[] }[];
}

function cellValue(value: ExcelJS.CellValue): Cell {
  if (value === null || value === undefined) return null;
  if (value instanceof Date || typeof value === "string" || typeof value === "number") return value;
  if (typeof value === "boolean") return String(value);
  if (typeof value === "object") {
    if ("result" in value) return cellValue(value.result as ExcelJS.CellValue);
    if ("richText" in value) return value.richText.map((part) => part.text).join("");
    if ("text" in value) return String(value.text);
    if ("error" in value) return null;
  }
  return null;
}

function isBlank(cells: Cell[]): boolean {
  return cells.every((cell) => cell === null || (typeof cell === "string" && cell.trim() === ""));
}

export async function readSheet(file: File): Promise<SheetData> {
  const buffer = Buffer.from(await file.arrayBuffer());
  const isCsv = file.name.toLowerCase().endsWith(".csv");
  const table: { number: number; cells: Cell[] }[] = [];
  try {
    if (isCsv) {
      const records = parse(buffer, { bom: true, relax_column_count: true, skip_empty_lines: false }) as string[][];
      records.forEach((record, index) => table.push({ number: index + 1, cells: record }));
    } else {
      const workbook = new ExcelJS.Workbook();
      await workbook.xlsx.load(buffer as unknown as ArrayBuffer);
      const sheet = workbook.worksheets[0];
      if (!sheet) throw new UserError("The file has no worksheet.");
      sheet.eachRow({ includeEmpty: true }, (row, number) => {
        const cells: Cell[] = [];
        for (let column = 1; column <= sheet.actualColumnCount; column += 1) cells.push(cellValue(row.getCell(column).value));
        table.push({ number, cells });
      });
    }
  } catch (error) {
    if (error instanceof UserError) throw error;
    throw new UserError("The file could not be read. Use the Excel template or a CSV file.");
  }
  const [header, ...rows] = table;
  if (!header) throw new UserError("The file is empty.");
  const data = rows.filter((row) => !isBlank(row.cells));
  if (data.length > MAX_BULK_ROWS) throw new UserError(`A file can have at most ${MAX_BULK_ROWS.toLocaleString("en-IN")} rows.`);
  return { headers: header.cells, rows: data };
}

export interface CheckedRow {
  row: number;
  no: string;
  date: string;
  party: string;
  amount: string;
  check: RowCheck;
  cells: string[];
}

function display(cell: Cell | undefined): string {
  if (cell instanceof Date) return cell.toISOString().slice(0, 10);
  return String(cell ?? "").trim();
}

export async function checkSheet(db: Db, type: DocType, sheet: SheetData): Promise<CheckedRow[]> {
  const headerProblem = checkHeaders(type, sheet.headers);
  if (headerProblem) throw new UserError(headerProblem);
  const template = registerByType(type).template;
  const position = (key: string) => template.findIndex((column) => column.key === key);
  const areaRows = await db.query<{ id: number; name: string }>("SELECT id, name FROM areas WHERE is_active");
  const areas = new Map(areaRows.map((area) => [area.name.toLowerCase(), area.id]));
  const checks = sheet.rows.map((row) => ({ row, check: checkRow(type, row.cells, areas) }));
  const ready = checks.flatMap(({ check }) => (check.outcome === "ready" ? [check.value] : []));
  const existing = new Set(
    ready.length === 0
      ? []
      : (
          await db.query<{ doc_no: string; financial_year: string }>(
            "SELECT doc_no, financial_year FROM documents WHERE doc_type = $1 AND deleted_at IS NULL AND doc_no = ANY($2::text[])",
            [type, Array.from(new Set(ready.map((value) => value.no)))],
          )
        ).map((row) => `${row.doc_no}|${row.financial_year}`),
  );
  const seen = new Set<string>();
  return checks.map(({ row, check }) => {
    let result = check;
    if (check.outcome === "ready") {
      const key = `${check.value.no}|${check.value.year}`;
      if (existing.has(key)) result = { outcome: "skipped", reason: "Already exists" };
      else if (seen.has(key)) result = { outcome: "skipped", reason: "Repeated in this file" };
      seen.add(key);
    }
    return {
      row: row.number,
      no: display(row.cells[position("no")]),
      date: display(row.cells[position("date")]),
      party: display(row.cells[position("party")]),
      amount: display(row.cells[position("amount")]),
      check: result,
      cells: template.map((_, index) => display(row.cells[index])),
    };
  });
}

export async function previewFile(type: DocType, file: File): Promise<CheckedRow[]> {
  const sheet = await readSheet(file);
  const db: Db = { query: (text, params) => sql(text, params) };
  return checkSheet(db, type, sheet);
}

export interface BulkResult {
  batchId: number;
  created: number;
  skipped: number;
  failed: number;
  rows: CheckedRow[];
}

async function insertDocument(db: Db, type: DocType, value: ParsedDocument, batchId: number, userId: number): Promise<boolean> {
  const partyId = await upsertParty(db, value.party, type);
  const inserted = await db.query(
    `INSERT INTO documents (doc_type, doc_no, doc_prefix, doc_seq, doc_date, financial_year, party_id, party_name_raw, amount, item_count,
       receipt_ref, mr_name, area_id, address, import_batch_id, created_by)
     VALUES ($1, $2, $3, $4, $5::date, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16)
     ON CONFLICT (doc_type, doc_no, financial_year) WHERE deleted_at IS NULL DO NOTHING
     RETURNING id`,
    [type, value.no, value.prefix, value.seq, value.date, value.year, partyId, value.party, value.amount, value.items, value.receipt, value.mr, value.areaId, value.address, batchId, userId],
  );
  return inserted.length === 1;
}

/** Validates the file again inside one transaction, then saves the valid rows. */
export async function commitFile(type: DocType, file: File, userId: number): Promise<BulkResult> {
  const sheet = await readSheet(file);
  return withTx(async (db) => {
    const rows = await checkSheet(db, type, sheet);
    const [{ id: batchId }] = await db.query<{ id: number }>(
      "INSERT INTO import_batches (doc_type, source_kind, file_name, rows_total, imported_by) VALUES ($1, 'TEMPLATE_UPLOAD', $2, $3, $4) RETURNING id",
      [type, file.name.slice(0, 255), rows.length, userId],
    );
    let created = 0;
    let skipped = 0;
    let failed = 0;
    for (const row of rows) {
      if (row.check.outcome === "ready") {
        if (await insertDocument(db, type, row.check.value, batchId, userId)) created += 1;
        else skipped += 1;
      } else if (row.check.outcome === "skipped") skipped += 1;
      else failed += 1;
    }
    const problems = rows.filter((row) => row.check.outcome !== "ready").map((row) => ({ row: row.row, reason: row.check.outcome === "ready" ? "" : row.check.reason }));
    await db.query("UPDATE import_batches SET rows_created = $2, rows_skipped = $3, rows_failed = $4, error_report = $5::jsonb WHERE id = $1", [batchId, created, skipped, failed, JSON.stringify(problems)]);
    await audit(db, userId, "BULK_UPLOAD", "import_batch", batchId, null, { type, created, skipped, failed });
    return { batchId, created, skipped, failed, rows };
  });
}

export async function templateWorkbook(type: DocType): Promise<ArrayBuffer> {
  const register = registerByType(type);
  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet(register.label.replace(/&/g, "and"));
  sheet.columns = register.template.map((column) => ({ header: column.header, key: column.key, width: column.key === "party" || column.key === "address" ? 36 : 18 }));
  sheet.getRow(1).font = { bold: true };
  sheet.views = [{ state: "frozen", ySplit: 1 }];
  return (await workbook.xlsx.writeBuffer()) as unknown as ArrayBuffer;
}
