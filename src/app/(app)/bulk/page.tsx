import { MANAGERS, requireUser } from "@/lib/auth";
import { REGISTERS } from "@/lib/registers";
import { BulkUpload } from "./BulkUpload";

export default async function BulkPage({ searchParams }: { searchParams: Promise<{ type?: string }> }) {
  await requireUser(MANAGERS);
  const { type } = await searchParams;
  const initial = REGISTERS.some((register) => register.slug === type) ? (type as string) : REGISTERS[0].slug;
  const registers = REGISTERS.map((register) => ({
    slug: register.slug,
    label: register.label,
    columns: register.template.map((column) => column.header),
  }));
  return (
    <main className="page">
      <div>
        <h1>Bulk upload</h1>
        <p className="muted">Download a template, fill it in, then check the rows before saving.</p>
      </div>
      <BulkUpload registers={registers} initial={initial} />
    </main>
  );
}
