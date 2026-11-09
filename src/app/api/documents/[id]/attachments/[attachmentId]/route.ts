import { apiUser } from "@/lib/auth";
import { removeLastAttachment, UserError } from "@/lib/documents";

export async function DELETE(request: Request, context: { params: Promise<{ id: string; attachmentId: string }> }) {
  const user = await apiUser(request);
  if (user instanceof Response) return user;
  const { id, attachmentId } = await context.params;
  try {
    await removeLastAttachment(Number(id), Number(attachmentId), user.id);
    return Response.json({ ok: true });
  } catch (error) {
    if (error instanceof UserError) return Response.json({ error: error.message }, { status: 400 });
    console.error(error);
    return Response.json({ error: "Photo could not be removed." }, { status: 500 });
  }
}
