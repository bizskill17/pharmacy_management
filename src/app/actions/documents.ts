"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { MANAGERS, requireUser } from "@/lib/auth";
import { parseAmount, parseDate, parseWholeNumber } from "@/lib/bulk";
import { correctDocument, deleteDocument, restoreDocument, saveRemarks as storeRemarks, UserError } from "@/lib/documents";

export interface ActionResult {
  ok: boolean;
  message: string;
}

function failure(error: unknown): ActionResult {
  if (error instanceof UserError) return { ok: false, message: error.message };
  console.error(error);
  return { ok: false, message: "Something went wrong. Please try again." };
}

export async function saveRemarks(documentId: number, remarks: string): Promise<ActionResult> {
  const user = await requireUser();
  try {
    await storeRemarks(documentId, remarks.slice(0, 2000), user.id);
    revalidatePath("/r", "layout");
    return { ok: true, message: "Update saved." };
  } catch (error) {
    return failure(error);
  }
}

function optional(form: FormData, name: string, limit: number): string | null {
  const value = String(form.get(name) ?? "").trim();
  return value ? value.slice(0, limit) : null;
}

export async function correctAction(documentId: number, _previous: ActionResult | null, form: FormData): Promise<ActionResult> {
  const user = await requireUser(MANAGERS);
  const rawDate = String(form.get("date") ?? "");
  const date = parseDate(rawDate.split("-").reverse().join("/"));
  const amount = parseAmount(String(form.get("amount") ?? ""));
  const partyName = String(form.get("party") ?? "").trim();
  const itemsText = String(form.get("items") ?? "").trim();
  const items = itemsText ? parseWholeNumber(itemsText) : null;
  if (!date) return { ok: false, message: "Enter a valid date." };
  if (amount === null) return { ok: false, message: "Enter a valid amount." };
  if (!partyName) return { ok: false, message: "Party name is required." };
  if (itemsText && items === null) return { ok: false, message: "No of Item must be a whole number." };
  const areaValue = Number(form.get("area") ?? 0);
  try {
    await correctDocument(
      documentId,
      {
        date,
        partyName: partyName.slice(0, 200),
        amount,
        items,
        receipt: optional(form, "receipt", 50),
        mr: optional(form, "mr", 120),
        areaId: areaValue > 0 ? areaValue : null,
        address: optional(form, "address", 255),
      },
      user.id,
    );
    revalidatePath("/r", "layout");
    return { ok: true, message: "Correction saved." };
  } catch (error) {
    return failure(error);
  }
}

export async function deleteAction(documentId: number, slug: string): Promise<void> {
  const user = await requireUser(MANAGERS);
  await deleteDocument(documentId, user.id);
  revalidatePath("/r", "layout");
  redirect(`/r/${slug}`);
}

export async function restoreAction(documentId: number, slug: string): Promise<ActionResult> {
  const user = await requireUser(MANAGERS);
  try {
    await restoreDocument(documentId, user.id);
  } catch (error) {
    return failure(error);
  }
  revalidatePath("/r", "layout");
  redirect(`/r/${slug}/${documentId}`);
}
