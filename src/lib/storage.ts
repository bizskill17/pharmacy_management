import { randomUUID } from "node:crypto";
import { mkdir, readFile, unlink, writeFile } from "node:fs/promises";
import path from "node:path";

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

function root(): string {
  return path.resolve(process.env.UPLOAD_DIR?.trim() || path.join(process.cwd(), "data", "uploads"));
}

function resolveKey(key: string): string {
  const base = root();
  const full = path.resolve(base, key);
  if (!full.startsWith(base + path.sep)) throw new Error("Invalid file key.");
  return full;
}

/** Identifies an image from its content rather than its file name. */
export function detectImage(data: Uint8Array): { mime: string; extension: string } | null {
  if (data.length >= 3 && data[0] === 0xff && data[1] === 0xd8 && data[2] === 0xff) return { mime: "image/jpeg", extension: "jpg" };
  if (data.length >= 8 && Buffer.from(data.subarray(0, 8)).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) {
    return { mime: "image/png", extension: "png" };
  }
  if (data.length >= 12 && Buffer.from(data.subarray(0, 4)).toString("latin1") === "RIFF" && Buffer.from(data.subarray(8, 12)).toString("latin1") === "WEBP") {
    return { mime: "image/webp", extension: "webp" };
  }
  return null;
}

export async function saveFile(data: Uint8Array, extension: string): Promise<string> {
  const now = new Date();
  const folder = `${now.getUTCFullYear()}/${String(now.getUTCMonth() + 1).padStart(2, "0")}`;
  const key = `${folder}/${randomUUID()}.${extension}`;
  const full = resolveKey(key);
  await mkdir(path.dirname(full), { recursive: true });
  await writeFile(full, data);
  return key;
}

export async function readStoredFile(key: string): Promise<Buffer> {
  return readFile(resolveKey(key));
}

export async function deleteStoredFile(key: string): Promise<void> {
  await unlink(resolveKey(key)).catch(() => undefined);
}
