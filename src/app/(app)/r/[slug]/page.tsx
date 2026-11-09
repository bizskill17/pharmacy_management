import Link from "next/link";
import { notFound } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { sql } from "@/lib/db";
import { attachmentsFor, DocumentRow, listDocuments, PAGE_SIZE, registerCounts, StatusFilter } from "@/lib/documents";
import { displayDate, money } from "@/lib/format";
import { Column, Register, registerBySlug, TextField } from "@/lib/registers";

type Search = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value)?.trim() ?? "";
}

function textValue(document: DocumentRow, field: TextField): string {
  switch (field) {
    case "party": return document.party ?? "-";
    case "no": return document.doc_no;
    case "date": return displayDate(document.doc_date);
    case "amount": return money(document.amount);
    case "receipt": return document.receipt_ref ?? "-";
    case "mr": return document.mr_name ?? "-";
    case "area": return document.area ?? "-";
    case "address": return document.address ?? "-";
    case "items": return document.item_count === null ? "-" : String(document.item_count);
    case "uploadDate": return displayDate(document.upload_date);
    case "uploader": return document.uploader ?? "-";
    case "remarks": return document.remarks ?? "-";
  }
}

function Cell({ column, document, photo, register }: { column: Column; document: DocumentRow; photo?: number; register: Register }) {
  if (column.type === "text") return <td className={column.right ? "right" : undefined}>{textValue(document, column.field)}</td>;
  if (column.type === "link") return <td><Link className="doc-link" href={`/r/${register.slug}/${document.id}`}>{document.doc_no}</Link></td>;
  if (column.type === "status") {
    const label = document.deleted_at ? "DELETED" : document.status;
    return <td><span className={`pill ${label}`}>{label === "UPLOADED" ? "Uploaded" : label === "PENDING" ? "Pending" : "Deleted"}</span></td>;
  }
  return (
    <td>
      {photo ? (
        <a href={`/api/files/${photo}`} target="_blank" rel="noreferrer" aria-label={`Open ${column.label}`}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img className="thumb" src={`/api/files/${photo}`} alt={`${column.label} preview`} loading="lazy" />
        </a>
      ) : "-"}
    </td>
  );
}

export default async function RegisterPage({ params, searchParams }: { params: Promise<{ slug: string }>; searchParams: Promise<Search> }) {
  const user = await requireUser();
  const register = registerBySlug((await params).slug);
  if (!register) notFound();
  const search = await searchParams;
  const canManage = user.role !== "UPLOADER";
  const requested = first(search.status) as StatusFilter;
  const statuses: StatusFilter[] = canManage ? ["ALL", "PENDING", "UPLOADED", "MINE", "DELETED"] : ["ALL", "PENDING", "UPLOADED", "MINE"];
  const status: StatusFilter = statuses.includes(requested) ? requested : "ALL";
  const query = first(search.q);
  const dateFormat = /^\d{4}-\d{2}-\d{2}$/;
  const from = dateFormat.test(first(search.from)) ? first(search.from) : "";
  const to = dateFormat.test(first(search.to)) ? first(search.to) : "";
  const areaId = Number(first(search.area)) || null;
  const page = Math.max(1, Math.floor(Number(first(search.page))) || 1);

  const [{ rows, total }, counts, areas] = await Promise.all([
    listDocuments({ type: register.type, status, query, from, to, areaId, page, userId: user.id }),
    registerCounts(register.type),
    sql<{ id: number; name: string }>("SELECT id, name FROM areas WHERE is_active ORDER BY name"),
  ]);
  const attachments = await attachmentsFor(rows.map((row) => row.id));
  const photoIds = new Map(attachments.map((item) => [`${item.document_id}:${item.kind}:${item.sort_order}`, item.id]));
  const lastPage = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const link = (change: Record<string, string | number>) => {
    const values = new URLSearchParams();
    const merged: Record<string, string | number> = { q: query, from, to, area: areaId ?? "", status: status === "ALL" ? "" : status, page: "", ...change };
    Object.entries(merged).forEach(([key, value]) => {
      if (value !== "") values.set(key, String(value));
    });
    const text = values.toString();
    return `/r/${register.slug}${text ? `?${text}` : ""}`;
  };
  const statusLabels: Record<StatusFilter, string> = { ALL: "All", PENDING: "Pending", UPLOADED: "Uploaded", MINE: "My uploads", DELETED: "Deleted" };

  return (
    <main className="page">
      <div className="row between">
        <div>
          <h1>{register.label}</h1>
          <p className="muted">{counts.pending.toLocaleString("en-IN")} pending, {counts.uploaded.toLocaleString("en-IN")} uploaded</p>
        </div>
        {canManage && (
          <div className="row">
            <Link className="btn" href="/reports">Reports</Link>
            <Link className="btn primary" href={`/bulk?type=${register.slug}`}>Bulk upload</Link>
          </div>
        )}
      </div>

      <form className="filters" method="get">
        {status !== "ALL" && <input type="hidden" name="status" value={status} />}
        <div className="field">
          <label htmlFor="q">Search number or party</label>
          <input id="q" name="q" className="input" type="search" defaultValue={query} />
        </div>
        <div className="field">
          <label htmlFor="from">From date</label>
          <input id="from" name="from" className="input" type="date" defaultValue={from} />
        </div>
        <div className="field">
          <label htmlFor="to">To date</label>
          <input id="to" name="to" className="input" type="date" defaultValue={to} />
        </div>
        <div className="field">
          <label htmlFor="area">Area</label>
          <select id="area" name="area" className="input" defaultValue={areaId ?? ""}>
            <option value="">All areas</option>
            {areas.map((area) => <option key={area.id} value={area.id}>{area.name}</option>)}
          </select>
        </div>
        <button className="btn primary">Apply</button>
      </form>

      <div className="row">
        {statuses.map((value) => (
          <Link key={value} className={`toggle${value === status ? " on" : ""}`} href={link({ status: value === "ALL" ? "" : value })}>{statusLabels[value]}</Link>
        ))}
      </div>

      {rows.length === 0 ? (
        <p className="muted">No documents found.</p>
      ) : (
        <div className="table-box">
          <table className="data">
            <thead>
              <tr>{register.columns.map((column) => <th key={column.label} className={column.type === "text" && column.right ? "right" : undefined}>{column.label}</th>)}</tr>
            </thead>
            <tbody>
              {rows.map((document) => (
                <tr key={document.id}>
                  {register.columns.map((column) => (
                    <Cell
                      key={column.label}
                      column={column}
                      document={document}
                      register={register}
                      photo={column.type === "photo" ? photoIds.get(`${document.id}:${column.kind}:${column.index}`) : undefined}
                    />
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {lastPage > 1 && (
        <nav className="row between" aria-label="Pages">
          <span className="muted">Page {page} of {lastPage}</span>
          <span className="row">
            {page > 1 && <Link className="btn" href={link({ page: page - 1 })}>Previous</Link>}
            {page < lastPage && <Link className="btn" href={link({ page: page + 1 })}>Next</Link>}
          </span>
        </nav>
      )}
    </main>
  );
}
