import { apiUser } from "@/lib/auth";
import { sql } from "@/lib/db";
import { readStoredFile } from "@/lib/storage";

export async function GET(request: Request, context: { params: Promise<{ id: string }> }) {
  const user = await apiUser(request);
  if (user instanceof Response) return user;
  const id = Number((await context.params).id);
  if (!Number.isInteger(id)) return new Response("Not found", { status: 404 });
  const [file] = await sql<{ file_key: string; mime_type: string }>(
    "SELECT file_key, mime_type FROM attachments WHERE id = $1 AND deleted_at IS NULL",
    [id],
  );
  if (!file) return new Response("Not found", { status: 404 });
  try {
    const data = await readStoredFile(file.file_key);
    return new Response(new Uint8Array(data), {
      headers: {
        "Content-Type": file.mime_type,
        "Cache-Control": "private, max-age=86400",
        "X-Content-Type-Options": "nosniff",
      },
    });
  } catch {
    return new Response("Not found", { status: 404 });
  }
}
