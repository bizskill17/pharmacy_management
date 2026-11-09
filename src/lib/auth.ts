import { createHmac, randomBytes, timingSafeEqual } from "node:crypto";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { sql } from "./db";

export type Role = "ADMIN" | "SUPERVISOR" | "UPLOADER";

export interface User {
  id: number;
  full_name: string;
  phone: string;
  role: Role;
}

const COOKIE_NAME = "pm_session";
const SESSION_SECONDS = 60 * 60 * 24 * 7;
const developmentSecret = randomBytes(32).toString("hex");

function secret(): string {
  const value = process.env.SESSION_SECRET;
  if (value) return value;
  if (process.env.NODE_ENV === "production") throw new Error("SESSION_SECRET must be set in production.");
  return developmentSecret;
}

function sign(payload: string): string {
  return createHmac("sha256", secret()).update(payload).digest("base64url");
}

export function createSessionToken(userId: number, now = Date.now()): string {
  const payload = Buffer.from(JSON.stringify({ uid: userId, exp: Math.floor(now / 1000) + SESSION_SECONDS })).toString("base64url");
  return `${payload}.${sign(payload)}`;
}

export function readSessionToken(token: string, now = Date.now()): number | null {
  const [payload, signature] = token.split(".");
  if (!payload || !signature) return null;
  const expected = Buffer.from(sign(payload));
  const actual = Buffer.from(signature);
  if (expected.length !== actual.length || !timingSafeEqual(expected, actual)) return null;
  try {
    const { uid, exp } = JSON.parse(Buffer.from(payload, "base64url").toString("utf8"));
    if (typeof uid !== "number" || typeof exp !== "number" || exp * 1000 < now) return null;
    return uid;
  } catch {
    return null;
  }
}

export async function startSession(userId: number): Promise<void> {
  const store = await cookies();
  store.set(COOKIE_NAME, createSessionToken(userId), {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production" && process.env.COOKIE_SECURE !== "false",
    path: "/",
    maxAge: SESSION_SECONDS,
  });
}

export async function endSession(): Promise<void> {
  (await cookies()).delete(COOKIE_NAME);
}

export async function currentUser(): Promise<User | null> {
  const token = (await cookies()).get(COOKIE_NAME)?.value;
  if (!token) return null;
  const userId = readSessionToken(token);
  if (userId === null) return null;
  const [user] = await sql<User & Record<string, unknown>>(
    "SELECT id, full_name, phone, role FROM users WHERE id = $1 AND is_active",
    [userId],
  );
  return user ?? null;
}

export function hasRole(user: User, roles?: Role[]): boolean {
  return !roles || roles.includes(user.role);
}

/** For pages and server actions. */
export async function requireUser(roles?: Role[]): Promise<User> {
  const user = await currentUser();
  if (!user) redirect("/login");
  if (!hasRole(user, roles)) redirect("/");
  return user;
}

export const MANAGERS: Role[] = ["ADMIN", "SUPERVISOR"];
export const ADMINS: Role[] = ["ADMIN"];

function jsonError(message: string, status: number): Response {
  return Response.json({ error: message }, { status });
}

/** Rejects cross-site writes to API routes. */
function sameOrigin(request: Request): boolean {
  if (["GET", "HEAD"].includes(request.method)) return true;
  const origin = request.headers.get("origin");
  if (!origin) return false;
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host");
  try {
    return new URL(origin).host === host;
  } catch {
    return false;
  }
}

/** For API routes. Returns the user or the error response to send. */
export async function apiUser(request: Request, roles?: Role[]): Promise<User | Response> {
  if (!sameOrigin(request)) return jsonError("Request origin not allowed.", 403);
  const user = await currentUser();
  if (!user) return jsonError("Please sign in again.", 401);
  if (!hasRole(user, roles)) return jsonError("You do not have access to this.", 403);
  return user;
}
