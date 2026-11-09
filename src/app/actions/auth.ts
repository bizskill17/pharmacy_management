"use server";

import { redirect } from "next/navigation";
import { endSession, startSession } from "@/lib/auth";
import { sql } from "@/lib/db";
import { verifyPassword } from "@/lib/password";

const MAX_FAILURES = 5;
const LOCK_MS = 15 * 60 * 1000;
const failures = new Map<string, { count: number; until: number }>();

export async function login(_previous: string | null, form: FormData): Promise<string | null> {
  const phone = String(form.get("phone") ?? "").trim();
  const password = String(form.get("password") ?? "");
  const attempt = failures.get(phone);
  if (attempt && attempt.count >= MAX_FAILURES && attempt.until > Date.now()) {
    return "Too many failed attempts. Try again in 15 minutes.";
  }
  const [user] = await sql<{ id: number; password_hash: string }>(
    "SELECT id, password_hash FROM users WHERE phone = $1 AND is_active",
    [phone],
  );
  if (!user || !(await verifyPassword(password, user.password_hash))) {
    const count = attempt && attempt.until > Date.now() ? attempt.count + 1 : 1;
    failures.set(phone, { count, until: Date.now() + LOCK_MS });
    return "Phone number or password is incorrect.";
  }
  failures.delete(phone);
  await sql("UPDATE users SET last_login_at = now() WHERE id = $1", [user.id]);
  await startSession(user.id);
  redirect("/");
}

export async function logout(): Promise<void> {
  await endSession();
  redirect("/login");
}
