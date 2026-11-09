import { apiUser } from "@/lib/auth";
import { addAttachment, UserError } from "@/lib/documents";
import { Kind } from "@/lib/registers";
import { deleteStoredFile, detectImage, MAX_IMAGE_BYTES, saveFile } from "@/lib/storage";

const KINDS: Kind[] = ["RECEIPT_COPY", "INVOICE_PHOTO", "CHALLAN_PHOTO", "GOODS_PHOTO"];

export async function POST(request: Request, context: { params: Promise<{ id: string }> }) {
  const user = await apiUser(request);
  if (user instanceof Response) return user;
  const documentId = Number((await context.params).id);
  if (!Number.isInteger(documentId)) return Response.json({ error: "Document not found." }, { status: 404 });
  const form = await request.formData();
  const kind = String(form.get("kind")) as Kind;
  const file = form.get("file");
  if (!KINDS.includes(kind) || !(file instanceof File)) return Response.json({ error: "Choose a photo first." }, { status: 400 });
  if (file.size > MAX_IMAGE_BYTES) return Response.json({ error: "Photo must be under 10 MB." }, { status: 400 });
  const data = new Uint8Array(await file.arrayBuffer());
  const image = detectImage(data);
  if (!image) return Response.json({ error: "Only JPG, PNG or WebP photos are allowed." }, { status: 400 });
  const key = await saveFile(data, image.extension);
  try {
    const attachment = await addAttachment(documentId, kind, { key, originalName: file.name || "photo", mime: image.mime, size: data.length }, user.id);
    return Response.json({ id: attachment.id, position: attachment.sort_order });
  } catch (error) {
    await deleteStoredFile(key);
    if (error instanceof UserError) return Response.json({ error: error.message }, { status: 400 });
    console.error(error);
    return Response.json({ error: "Photo could not be saved." }, { status: 500 });
  }
}
