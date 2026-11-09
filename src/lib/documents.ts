import { audit, Db, Row, sql, withTx } from "./db";
import { financialYear } from "./bulk";
import { DocType, isComplete, Kind, registerByType } from "./registers";

export const PAGE_SIZE = 50;

export interface DocumentRow extends Row {
  id: number;
  doc_type: DocType;
  doc_no: string;
  doc_date: string;
  financial_year: string;
  party: string | null;
  amount: number;
  item_count: number | null;
  receipt_ref: string | null;
  mr_name: string | null;
  area: string | null;
  area_id: number | null;
  address: string | null;
  status: "PENDING" | "UPLOADED";
  remarks: string | null;
  uploader: string | null;
  upload_date: string | null;
  deleted_at: Date | null;
}

export interface AttachmentRow extends Row {
  id: number;
  document_id: number;
  kind: Kind;
  sort_order: number;
}

const DOCUMENT_SELECT = `
  SELECT d.*, COALESCE(p.name, d.party_name_raw) AS party, a.name AS area,
         COALESCE(u.full_name, d.legacy_uploaded_by) AS uploader,
         to_char(d.uploaded_at AT TIME ZONE 'Asia/Kolkata', 'YYYY-MM-DD') AS upload_date
  FROM documents d
  LEFT JOIN parties p ON p.id = d.party_id
  LEFT JOIN areas a ON a.id = d.area_id
  LEFT JOIN users u ON u.id = d.uploaded_by`;

export type StatusFilter = "ALL" | "PENDING" | "UPLOADED" | "MINE" | "DELETED";

export interface ListFilter {
  type: DocType;
  status: StatusFilter;
  query: string;
  from: string;
  to: string;
  areaId: number | null;
  page: number;
  userId: number;
}

function escapeLike(value: string): string {
  return value.replace(/[\\%_]/g, (character) => `\\${character}`);
}

export async function listDocuments(filter: ListFilter): Promise<{ rows: DocumentRow[]; total: number }> {
  const params: unknown[] = [filter.type];
  const where = ["d.doc_type = $1"];
  const add = (clause: (position: string) => string, value: unknown) => {
    params.push(value);
    where.push(clause(`$${params.length}`));
  };
  where.push(filter.status === "DELETED" ? "d.deleted_at IS NOT NULL" : "d.deleted_at IS NULL");
  if (filter.status === "PENDING" || filter.status === "UPLOADED") add((p) => `d.status = ${p}`, filter.status);
  if (filter.status === "MINE") add((p) => `d.uploaded_by = ${p}`, filter.userId);
  if (filter.query) {
    add((p) => `(d.doc_no ILIKE ${p} OR d.party_name_raw ILIKE ${p} OR p.name ILIKE ${p})`, `%${escapeLike(filter.query)}%`);
  }
  if (filter.from) add((p) => `d.doc_date >= ${p}::date`, filter.from);
  if (filter.to) add((p) => `d.doc_date <= ${p}::date`, filter.to);
  if (filter.areaId) add((p) => `d.area_id = ${p}`, filter.areaId);
  const clause = where.join(" AND ");
  const [{ total }] = await sql<{ total: number }>(
    `SELECT count(*)::int AS total FROM documents d LEFT JOIN parties p ON p.id = d.party_id WHERE ${clause}`,
    params,
  );
  const rows = await sql<DocumentRow>(
    `${DOCUMENT_SELECT} WHERE ${clause} ORDER BY d.doc_date DESC, d.doc_seq DESC, d.id DESC LIMIT ${PAGE_SIZE} OFFSET ${(filter.page - 1) * PAGE_SIZE}`,
    params,
  );
  return { rows, total };
}

export async function registerCounts(type: DocType): Promise<{ pending: number; uploaded: number }> {
  const rows = await sql<{ status: string; count: number }>(
    "SELECT status, count(*)::int AS count FROM documents WHERE doc_type = $1 AND deleted_at IS NULL GROUP BY status",
    [type],
  );
  const count = (status: string) => rows.find((row) => row.status === status)?.count ?? 0;
  return { pending: count("PENDING"), uploaded: count("UPLOADED") };
}

export async function attachmentsFor(ids: number[]): Promise<AttachmentRow[]> {
  if (ids.length === 0) return [];
  return sql<AttachmentRow>(
    `SELECT id, document_id, kind, sort_order FROM attachments
     WHERE document_id = ANY($1::bigint[]) AND deleted_at IS NULL ORDER BY kind, sort_order`,
    [ids],
  );
}

export async function getDocument(id: number): Promise<DocumentRow | undefined> {
  const [row] = await sql<DocumentRow>(`${DOCUMENT_SELECT} WHERE d.id = $1`, [id]);
  return row;
}

export class UserError extends Error {}

async function lockDocument(db: Db, id: number): Promise<{ id: number; doc_type: DocType; deleted_at: Date | null }> {
  const [document] = await db.query<{ id: number; doc_type: DocType; deleted_at: Date | null }>(
    "SELECT id, doc_type, deleted_at FROM documents WHERE id = $1 FOR UPDATE",
    [id],
  );
  if (!document) throw new UserError("Document not found.");
  if (document.deleted_at) throw new UserError("This document has been deleted.");
  return document;
}

/** Sets status from attached photos and records who completed the upload. */
async function refreshStatus(db: Db, documentId: number, type: DocType, userId: number): Promise<void> {
  const rows = await db.query<{ kind: Kind; count: number }>(
    "SELECT kind, count(*)::int AS count FROM attachments WHERE document_id = $1 AND deleted_at IS NULL GROUP BY kind",
    [documentId],
  );
  const counts = Object.fromEntries(rows.map((row) => [row.kind, row.count]));
  if (isComplete(type, counts)) {
    await db.query(
      `UPDATE documents SET status = 'UPLOADED', uploaded_by = $2, legacy_uploaded_by = NULL, uploaded_at = now(), updated_at = now() WHERE id = $1`,
      [documentId, userId],
    );
  } else {
    await db.query(
      `UPDATE documents SET status = 'PENDING', uploaded_by = NULL, legacy_uploaded_by = NULL, uploaded_at = NULL, updated_at = now() WHERE id = $1`,
      [documentId],
    );
  }
}

export interface NewFile {
  key: string;
  originalName: string;
  mime: string;
  size: number;
}

/** Adds a photo in the next open slot. A slot opens only after the one before it is filled. */
export async function addAttachment(documentId: number, kind: Kind, file: NewFile, userId: number): Promise<AttachmentRow> {
  return withTx(async (db) => {
    const document = await lockDocument(db, documentId);
    const group = registerByType(document.doc_type).groups.find((candidate) => candidate.kind === kind);
    if (!group) throw new UserError("This photo type is not used for this register.");
    const [{ last }] = await db.query<{ last: number }>(
      "SELECT COALESCE(max(sort_order), 0)::int AS last FROM attachments WHERE document_id = $1 AND kind = $2 AND deleted_at IS NULL",
      [documentId, kind],
    );
    if (last >= group.max) throw new UserError(`Only ${group.max} ${group.label} allowed.`);
    const [attachment] = await db.query<AttachmentRow>(
      `INSERT INTO attachments (document_id, kind, file_key, original_name, mime_type, size_bytes, sort_order, uploaded_by)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id, document_id, kind, sort_order`,
      [documentId, kind, file.key, file.originalName.slice(0, 255), file.mime, file.size, last + 1, userId],
    );
    await refreshStatus(db, documentId, document.doc_type, userId);
    await audit(db, userId, "ATTACHMENT_ADD", "document", documentId, null, { attachmentId: attachment.id, kind, position: last + 1 });
    return attachment;
  });
}

/** Removes the last photo in its group, so a sequence never has a gap. */
export async function removeLastAttachment(documentId: number, attachmentId: number, userId: number): Promise<void> {
  await withTx(async (db) => {
    const document = await lockDocument(db, documentId);
    const [attachment] = await db.query<AttachmentRow>(
      "SELECT id, document_id, kind, sort_order FROM attachments WHERE id = $1 AND document_id = $2 AND deleted_at IS NULL",
      [attachmentId, documentId],
    );
    if (!attachment) throw new UserError("Photo not found.");
    const [{ last }] = await db.query<{ last: number }>(
      "SELECT max(sort_order)::int AS last FROM attachments WHERE document_id = $1 AND kind = $2 AND deleted_at IS NULL",
      [documentId, attachment.kind],
    );
    if (attachment.sort_order !== last) throw new UserError("Only the last photo in a group can be removed.");
    await db.query("UPDATE attachments SET deleted_at = now() WHERE id = $1", [attachmentId]);
    await refreshStatus(db, documentId, document.doc_type, userId);
    await audit(db, userId, "ATTACHMENT_REMOVE", "document", documentId, { attachmentId, kind: attachment.kind }, null);
  });
}

export async function saveRemarks(documentId: number, remarks: string, userId: number): Promise<void> {
  await withTx(async (db) => {
    const document = await lockDocument(db, documentId);
    if (document.doc_type !== "PURCHASE") throw new UserError("Remarks are only used for Purchase.");
    const [{ remarks: before }] = await db.query<{ remarks: string | null }>("SELECT remarks FROM documents WHERE id = $1", [documentId]);
    await db.query("UPDATE documents SET remarks = $2, updated_at = now() WHERE id = $1", [documentId, remarks.trim() || null]);
    await audit(db, userId, "REMARKS_UPDATE", "document", documentId, { remarks: before }, { remarks: remarks.trim() || null });
  });
}

export interface Correction {
  date: string;
  partyName: string;
  amount: number;
  items: number | null;
  receipt: string | null;
  mr: string | null;
  areaId: number | null;
  address: string | null;
}

export async function correctDocument(documentId: number, change: Correction, userId: number): Promise<void> {
  await withTx(async (db) => {
    const document = await lockDocument(db, documentId);
    const [before] = await db.query(
      "SELECT doc_date, party_name_raw, amount, item_count, receipt_ref, mr_name, area_id, address, financial_year, doc_no FROM documents WHERE id = $1",
      [documentId],
    );
    const year = financialYear(change.date);
    const [clash] = await db.query(
      "SELECT id FROM documents WHERE doc_type = $1 AND doc_no = $2 AND financial_year = $3 AND id <> $4 AND deleted_at IS NULL",
      [document.doc_type, before.doc_no, year, documentId],
    );
    if (clash) throw new UserError("Another document already has this number in that financial year.");
    const party = await upsertParty(db, change.partyName, document.doc_type);
    await db.query(
      `UPDATE documents SET doc_date = $2::date, financial_year = $3, party_id = $4, party_name_raw = $5, amount = $6, item_count = $7,
         receipt_ref = $8, mr_name = $9, area_id = $10, address = $11, updated_at = now() WHERE id = $1`,
      [documentId, change.date, year, party, change.partyName, change.amount, change.items, change.receipt, change.mr, change.areaId, change.address],
    );
    await audit(db, userId, "DOCUMENT_CORRECT", "document", documentId, before, change);
  });
}

export async function upsertParty(db: Db, rawName: string, type: DocType): Promise<number> {
  const supplier = type === "PURCHASE";
  const [party] = await db.query<{ id: number }>(
    `INSERT INTO parties (name, source_name, is_customer, is_supplier) VALUES ($1, $2, $3, $4)
     ON CONFLICT (source_name) DO UPDATE SET
       is_customer = parties.is_customer OR EXCLUDED.is_customer,
       is_supplier = parties.is_supplier OR EXCLUDED.is_supplier
     RETURNING id`,
    [rawName.replace(/\s+/g, " ").slice(0, 120), rawName, !supplier, supplier],
  );
  return party.id;
}

export async function deleteDocument(documentId: number, userId: number): Promise<void> {
  await withTx(async (db) => {
    await lockDocument(db, documentId);
    await db.query("UPDATE documents SET deleted_at = now(), updated_at = now() WHERE id = $1", [documentId]);
    await audit(db, userId, "DOCUMENT_DELETE", "document", documentId);
  });
}

export async function restoreDocument(documentId: number, userId: number): Promise<void> {
  await withTx(async (db) => {
    const [document] = await db.query<{ doc_type: DocType; doc_no: string; financial_year: string }>(
      "SELECT doc_type, doc_no, financial_year FROM documents WHERE id = $1 AND deleted_at IS NOT NULL FOR UPDATE",
      [documentId],
    );
    if (!document) throw new UserError("Document not found.");
    const [clash] = await db.query(
      "SELECT id FROM documents WHERE doc_type = $1 AND doc_no = $2 AND financial_year = $3 AND deleted_at IS NULL",
      [document.doc_type, document.doc_no, document.financial_year],
    );
    if (clash) throw new UserError("An active document already uses this number.");
    await db.query("UPDATE documents SET deleted_at = NULL, updated_at = now() WHERE id = $1", [documentId]);
    await audit(db, userId, "DOCUMENT_RESTORE", "document", documentId);
  });
}
