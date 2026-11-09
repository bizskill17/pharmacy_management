import { DocType, registerByType, TemplateKey } from "./registers";

export type Cell = string | number | Date | null;

export const MAX_BULK_ROWS = 10000;

const MONTHS: Record<string, number> = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12 };

function validDate(year: number, month: number, day: number): string | null {
  const date = new Date(Date.UTC(year, month - 1, day));
  if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
  return `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

/** Accepts Excel dates, DD/MM/YYYY and DD-Mon-YY. Blank and 01/01/1970 are invalid. */
export function parseDate(value: Cell): string | null {
  let iso: string | null = null;
  if (value instanceof Date) {
    iso = validDate(value.getUTCFullYear(), value.getUTCMonth() + 1, value.getUTCDate());
  } else if (typeof value === "string") {
    const text = value.trim();
    const numeric = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    const named = text.match(/^(\d{1,2})-([A-Za-z]{3})-(\d{2}|\d{4})$/);
    if (numeric) iso = validDate(Number(numeric[3]), Number(numeric[2]), Number(numeric[1]));
    else if (named && MONTHS[named[2].toLowerCase()]) {
      const year = named[3].length === 2 ? 2000 + Number(named[3]) : Number(named[3]);
      iso = validDate(year, MONTHS[named[2].toLowerCase()], Number(named[1]));
    }
  }
  return iso === "1970-01-01" ? null : iso;
}

export function parseAmount(value: Cell): number | null {
  if (typeof value === "number") return Number.isFinite(value) && value >= 0 && value < 1e12 ? Math.round(value * 100) / 100 : null;
  if (typeof value !== "string") return null;
  const text = value.trim().replace(/,/g, "");
  if (!/^\d+(\.\d+)?$/.test(text)) return null;
  const amount = Number(text);
  return amount < 1e12 ? Math.round(amount * 100) / 100 : null;
}

export function parseWholeNumber(value: Cell): number | null {
  const text = typeof value === "number" ? String(value) : typeof value === "string" ? value.trim() : "";
  return /^\d{1,7}$/.test(text) ? Number(text) : null;
}

export interface DocNumber {
  no: string;
  prefix: string;
  seq: number;
}

/** Removes suffixes such as "18/09" and splits the series from the number. */
export function parseDocNumber(value: Cell): DocNumber | null {
  const no = String(value ?? "").trim().split(/\s+/)[0] ?? "";
  const match = no.match(/^(.*?)(\d{1,9})$/);
  if (!match || no.length > 30 || match[1].length > 10) return null;
  return { no, prefix: match[1], seq: Number(match[2]) };
}

/** Indian financial year, April to March, e.g. 2026-27. */
export function financialYear(iso: string): string {
  const year = Number(iso.slice(0, 4));
  const month = Number(iso.slice(5, 7));
  const start = month >= 4 ? year : year - 1;
  return `${start}-${String((start + 1) % 100).padStart(2, "0")}`;
}

export function checkHeaders(type: DocType, headers: Cell[]): string | null {
  const expected = registerByType(type).template.map((column) => column.header);
  const actual = headers.map((header) => String(header ?? "").trim());
  while (actual.length > expected.length && actual[actual.length - 1] === "") actual.pop();
  if (actual.length === expected.length && expected.every((header, index) => header.toLowerCase() === actual[index].toLowerCase())) return null;
  return `Columns must be, in this order: ${expected.join(", ")}.`;
}

export interface ParsedDocument {
  no: string;
  prefix: string;
  seq: number;
  date: string;
  year: string;
  party: string;
  amount: number;
  items: number | null;
  receipt: string | null;
  mr: string | null;
  areaId: number | null;
  address: string | null;
}

export type RowCheck =
  | { outcome: "ready"; value: ParsedDocument }
  | { outcome: "failed"; reason: string }
  | { outcome: "skipped"; reason: string };

function text(value: Cell, limit: number): string | null {
  if (value === null || value === undefined) return null;
  const result = (value instanceof Date ? value.toISOString().slice(0, 10) : String(value)).trim();
  return result ? result.slice(0, limit) : null;
}

/** Validates one data row. Duplicate checks need other rows, so they run separately. */
export function checkRow(type: DocType, cells: Cell[], areas: Map<string, number>): RowCheck {
  const value: Partial<Record<TemplateKey, Cell>> = {};
  registerByType(type).template.forEach((column, index) => {
    value[column.key] = cells[index] ?? null;
  });
  const required = registerByType(type).template.filter((column) => column.required);
  for (const column of required) {
    if (text(value[column.key] ?? null, 500) === null) return { outcome: "failed", reason: `${column.header} is blank` };
  }
  const number = parseDocNumber(value.no ?? null);
  if (!number) return { outcome: "failed", reason: "Number format is not valid" };
  const date = parseDate(value.date ?? null);
  if (!date) return { outcome: "failed", reason: "Date is blank or not valid" };
  const amount = parseAmount(value.amount ?? null);
  if (amount === null) return { outcome: "failed", reason: "Amount is not a valid number" };
  let items: number | null = null;
  if (text(value.items ?? null, 20) !== null) {
    items = parseWholeNumber(value.items ?? null);
    if (items === null) return { outcome: "failed", reason: "No of Item is not a whole number" };
  }
  let areaId: number | null = null;
  const areaName = text(value.area ?? null, 100);
  if (areaName) {
    areaId = areas.get(areaName.toLowerCase()) ?? null;
    if (areaId === null) return { outcome: "failed", reason: "Area not found" };
  }
  return {
    outcome: "ready",
    value: {
      ...number,
      date,
      year: financialYear(date),
      party: text(value.party ?? null, 200) as string,
      amount,
      items,
      receipt: text(value.receipt ?? null, 50),
      mr: text(value.mr ?? null, 120),
      areaId,
      address: text(value.address ?? null, 255),
    },
  };
}

/** Prevents a spreadsheet from treating text as a formula when a CSV is opened. */
export function csvCell(value: unknown): string {
  let text = String(value ?? "");
  if (/^[=+\-@\t\r]/.test(text)) text = `'${text}`;
  return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}
