import Link from "next/link";
import { notFound } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { sql } from "@/lib/db";
import { attachmentsFor, getDocument } from "@/lib/documents";
import { displayDate, money } from "@/lib/format";
import { registerBySlug } from "@/lib/registers";
import { CorrectionForm, RestoreButton } from "./Manage";
import { UpdateForm } from "./UpdateForm";

function todayInIndia(): string {
  return new Intl.DateTimeFormat("en-GB", { timeZone: "Asia/Kolkata", day: "2-digit", month: "2-digit", year: "numeric" }).format(new Date());
}

export default async function DocumentPage({ params }: { params: Promise<{ slug: string; id: string }> }) {
  const user = await requireUser();
  const { slug, id } = await params;
  const register = registerBySlug(slug);
  const documentId = Number(id);
  if (!register || !Number.isInteger(documentId)) notFound();
  const document = await getDocument(documentId);
  if (!document || document.doc_type !== register.type) notFound();

  const attachments = await attachmentsFor([documentId]);
  const initialPhotos: Record<string, { id: number; position: number }[]> = {};
  for (const item of attachments) (initialPhotos[item.kind] ??= []).push({ id: item.id, position: item.sort_order });
  const missing = register.groups.filter((group) => group.required && !initialPhotos[group.kind]?.length).map((group) => group.label);
  const canManage = user.role !== "UPLOADER";
  const deleted = Boolean(document.deleted_at);
  const areas = canManage ? await sql<{ id: number; name: string }>("SELECT id, name FROM areas WHERE is_active ORDER BY name") : [];

  const hasField = (field: string) => register.template.some((column) => column.key === field);
  const fields: [string, string][] = [
    [register.partyLabel, document.party ?? "-"],
    [register.numberLabel, document.doc_no],
    [register.dateLabel, displayDate(document.doc_date)],
    [register.amountLabel, money(document.amount)],
  ];
  if (hasField("receipt")) fields.push(["Receipt", document.receipt_ref ?? "-"]);
  if (hasField("items")) fields.push(["No of Item", document.item_count === null ? "-" : String(document.item_count)]);
  if (hasField("mr")) fields.push(["MR", document.mr_name ?? "-"], ["Area", document.area ?? "-"], ["Address", document.address ?? "-"]);
  fields.push(["Uploaded by", document.uploader ?? "-"], ["Upload date", displayDate(document.upload_date)]);

  const statusLabel = deleted ? "DELETED" : document.status;
  return (
    <main className="page">
      <div>
        <Link className="doc-link" href={`/r/${register.slug}`}>Back to {register.label}</Link>
        <div className="row" style={{ marginTop: 8, gap: 12 }}>
          <h1>{document.doc_no}</h1>
          <span className={`pill ${statusLabel}`}>{statusLabel === "UPLOADED" ? "Uploaded" : statusLabel === "PENDING" ? "Pending" : "Deleted"}</span>
        </div>
        <p className="muted">{missing.length ? `Still needed: ${missing.join(", ")}` : "All required files are attached."}</p>
      </div>
      {deleted && canManage && <RestoreButton documentId={documentId} slug={register.slug} />}
      <div className="split">
        <div className="side" style={{ display: "flex", flexDirection: "column", gap: 16 }}>
          <section className="card" style={{ gap: 0 }}>
            <h2 style={{ marginBottom: 8 }}>Details</h2>
            {fields.map(([label, value]) => <div key={label} className="kv"><span>{label}</span><span>{value}</span></div>)}
          </section>
          {canManage && !deleted && (
            <CorrectionForm
              documentId={documentId}
              slug={register.slug}
              partyLabel={register.partyLabel}
              areas={areas}
              showItems={hasField("items")}
              showReceipt={hasField("receipt")}
              showLocation={hasField("mr")}
              values={{
                date: document.doc_date,
                party: document.party_name_raw ?? document.party ?? "",
                amount: document.amount,
                items: document.item_count,
                receipt: document.receipt_ref ?? "",
                mr: document.mr_name ?? "",
                areaId: document.area_id,
                address: document.address ?? "",
              }}
            />
          )}
        </div>
        <UpdateForm
          documentId={documentId}
          title={`${register.label} update form`}
          groups={register.groups}
          sections={register.form}
          initialPhotos={initialPhotos}
          uploadDate={todayInIndia()}
          employee={user.full_name}
          initialRemarks={document.remarks ?? ""}
          locked={deleted}
        />
      </div>
    </main>
  );
}
