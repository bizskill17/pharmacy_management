import { revalidatePath } from "next/cache";
import { apiUser, MANAGERS } from "@/lib/auth";
import { CheckedRow, commitFile, MAX_BULK_FILE_BYTES, previewFile } from "@/lib/bulk-server";
import { UserError } from "@/lib/documents";
import { registerBySlug } from "@/lib/registers";

function summarize(rows: CheckedRow[]) {
  return rows.map((row) => ({
    row: row.row,
    no: row.no,
    date: row.date,
    party: row.party,
    amount: row.amount,
    outcome: row.check.outcome,
    reason: row.check.outcome === "ready" ? "" : row.check.reason,
    cells: row.cells,
  }));
}

export async function POST(request: Request, context: { params: Promise<{ slug: string }> }) {
  const user = await apiUser(request, MANAGERS);
  if (user instanceof Response) return user;
  const register = registerBySlug((await context.params).slug);
  if (!register) return Response.json({ error: "Register not found." }, { status: 404 });
  const form = await request.formData();
  const file = form.get("file");
  if (!(file instanceof File)) return Response.json({ error: "Choose a file first." }, { status: 400 });
  if (file.size > MAX_BULK_FILE_BYTES) return Response.json({ error: "File must be under 10 MB." }, { status: 400 });
  try {
    if (form.get("mode") === "confirm") {
      const result = await commitFile(register.type, file, user.id);
      revalidatePath("/r", "layout");
      return Response.json({ created: result.created, skipped: result.skipped, failed: result.failed, rows: summarize(result.rows) });
    }
    return Response.json({ rows: summarize(await previewFile(register.type, file)) });
  } catch (error) {
    if (error instanceof UserError) return Response.json({ error: error.message }, { status: 400 });
    console.error(error);
    return Response.json({ error: "The file could not be processed." }, { status: 500 });
  }
}
