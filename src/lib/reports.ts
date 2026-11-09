import { sql } from "./db";
import { displayDate } from "./format";
import { DocType, REGISTERS, registerByType } from "./registers";

export interface PendingRow {
  date: string;
  label: string;
  count: number;
}

export interface CountRow {
  label: string;
  count: number;
}

export interface MissingRow {
  label: string;
  from: string;
  to: string;
  count: number;
}

export async function pendingByDate(): Promise<PendingRow[]> {
  const rows = await sql<{ doc_date: string; doc_type: DocType; count: number }>(
    `SELECT doc_date, doc_type, count(*)::int AS count FROM documents
     WHERE status = 'PENDING' AND deleted_at IS NULL
     GROUP BY doc_date, doc_type ORDER BY doc_date DESC, doc_type LIMIT 1000`,
  );
  return rows.map((row) => ({ date: displayDate(row.doc_date), label: registerByType(row.doc_type).label, count: row.count }));
}

export async function uploadedToday(): Promise<CountRow[]> {
  const rows = await sql<{ doc_type: DocType; count: number }>(
    `SELECT doc_type, count(*)::int AS count FROM documents
     WHERE deleted_at IS NULL AND status = 'UPLOADED'
       AND (uploaded_at AT TIME ZONE 'Asia/Kolkata')::date = (now() AT TIME ZONE 'Asia/Kolkata')::date
     GROUP BY doc_type`,
  );
  return REGISTERS.map((register) => ({ label: register.label, count: rows.find((row) => row.doc_type === register.type)?.count ?? 0 }));
}

export async function missingNumbers(): Promise<MissingRow[]> {
  const rows = await sql<{ doc_type: DocType; doc_prefix: string; doc_seq: number; previous: number; width: number; financial_year: string }>(
    `SELECT * FROM (
       SELECT doc_type, doc_prefix, doc_seq, financial_year,
              length(doc_no) - length(doc_prefix) AS width,
              lag(doc_seq) OVER (PARTITION BY doc_type, doc_prefix, financial_year ORDER BY doc_seq) AS previous
       FROM documents WHERE deleted_at IS NULL
     ) numbered
     WHERE previous IS NOT NULL AND doc_seq - previous > 1
     ORDER BY financial_year DESC, doc_type, doc_prefix, doc_seq LIMIT 1000`,
  );
  return rows.map((row) => ({
    label: `${registerByType(row.doc_type).label} ${row.financial_year}`,
    from: row.doc_prefix + String(row.previous + 1).padStart(row.width, "0"),
    to: row.doc_prefix + String(row.doc_seq - 1).padStart(row.width, "0"),
    count: row.doc_seq - row.previous - 1,
  }));
}
