"use server";

import { revalidatePath } from "next/cache";
import { ADMINS, Role, requireUser } from "@/lib/auth";
import { audit, sql, withTx } from "@/lib/db";
import { hashPassword, MIN_PASSWORD_LENGTH } from "@/lib/password";

export interface FormResult {
  ok: boolean;
  message: string;
}

const ROLES: Role[] = ["ADMIN", "SUPERVISOR", "UPLOADER"];

function isUniqueViolation(error: unknown): boolean {
  return (error as { code?: string })?.code === "23505";
}

export async function createUser(_previous: FormResult | null, form: FormData): Promise<FormResult> {
  const admin = await requireUser(ADMINS);
  const name = String(form.get("name") ?? "").trim().slice(0, 120);
  const phone = String(form.get("phone") ?? "").trim().slice(0, 15);
  const password = String(form.get("password") ?? "");
  const role = String(form.get("role")) as Role;
  if (!name || !phone) return { ok: false, message: "Name and phone are required." };
  if (password.length < MIN_PASSWORD_LENGTH) return { ok: false, message: `Password must have at least ${MIN_PASSWORD_LENGTH} characters.` };
  if (!ROLES.includes(role)) return { ok: false, message: "Choose a role." };
  const hash = await hashPassword(password);
  try {
    await withTx(async (db) => {
      const [user] = await db.query<{ id: number }>(
        "INSERT INTO users (full_name, phone, password_hash, role) VALUES ($1, $2, $3, $4) RETURNING id",
        [name, phone, hash, role],
      );
      await audit(db, admin.id, "USER_CREATE", "user", user.id, null, { name, phone, role });
    });
  } catch (error) {
    if (isUniqueViolation(error)) return { ok: false, message: "This phone number is already used." };
    throw error;
  }
  revalidatePath("/admin/users");
  return { ok: true, message: "User created." };
}

export async function updateUser(_previous: FormResult | null, form: FormData): Promise<FormResult> {
  const admin = await requireUser(ADMINS);
  const userId = Number(form.get("id"));
  const role = String(form.get("role")) as Role;
  const active = form.get("active") === "on";
  const password = String(form.get("password") ?? "");
  if (!ROLES.includes(role)) return { ok: false, message: "Choose a role." };
  if (userId === admin.id && (role !== "ADMIN" || !active)) return { ok: false, message: "You cannot remove your own Admin access." };
  if (password && password.length < MIN_PASSWORD_LENGTH) return { ok: false, message: `Password must have at least ${MIN_PASSWORD_LENGTH} characters.` };
  const hash = password ? await hashPassword(password) : null;
  await withTx(async (db) => {
    await db.query(
      "UPDATE users SET role = $2, is_active = $3, password_hash = COALESCE($4, password_hash), updated_at = now() WHERE id = $1",
      [userId, role, active, hash],
    );
    await audit(db, admin.id, "USER_UPDATE", "user", userId, null, { role, active, passwordChanged: Boolean(hash) });
  });
  revalidatePath("/admin/users");
  return { ok: true, message: "Saved." };
}

export async function createArea(_previous: FormResult | null, form: FormData): Promise<FormResult> {
  const admin = await requireUser(ADMINS);
  const name = String(form.get("name") ?? "").trim().slice(0, 100);
  if (!name) return { ok: false, message: "Enter an area name." };
  try {
    await withTx(async (db) => {
      const [area] = await db.query<{ id: number }>("INSERT INTO areas (name) VALUES ($1) RETURNING id", [name]);
      await audit(db, admin.id, "AREA_CREATE", "area", area.id, null, { name });
    });
  } catch (error) {
    if (isUniqueViolation(error)) return { ok: false, message: "This area already exists." };
    throw error;
  }
  revalidatePath("/admin/areas");
  return { ok: true, message: "Area added." };
}

export async function toggleArea(form: FormData): Promise<void> {
  await requireUser(ADMINS);
  await sql("UPDATE areas SET is_active = NOT is_active WHERE id = $1", [Number(form.get("id"))]);
  revalidatePath("/admin/areas");
}

export async function updateParty(_previous: FormResult | null, form: FormData): Promise<FormResult> {
  const admin = await requireUser(ADMINS);
  const partyId = Number(form.get("id"));
  const name = String(form.get("name") ?? "").trim().slice(0, 120);
  const areaValue = Number(form.get("area") ?? 0);
  const active = form.get("active") === "on";
  if (!name) return { ok: false, message: "Enter a party name." };
  await withTx(async (db) => {
    await db.query("UPDATE parties SET name = $2, area_id = $3, is_active = $4, updated_at = now() WHERE id = $1", [partyId, name, areaValue > 0 ? areaValue : null, active]);
    await audit(db, admin.id, "PARTY_UPDATE", "party", partyId, null, { name, areaId: areaValue || null, active });
  });
  revalidatePath("/admin/parties");
  return { ok: true, message: "Saved." };
}
