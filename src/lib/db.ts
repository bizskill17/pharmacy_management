import { mkdir } from "node:fs/promises";
import path from "node:path";
import { hashPassword, MIN_PASSWORD_LENGTH } from "./password";
import { SCHEMA_SQL } from "./schema";

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Row = Record<string, any>;

export interface Db {
  query<T extends Row = Row>(text: string, params?: unknown[]): Promise<T[]>;
}

interface Driver extends Db {
  exec(text: string): Promise<void>;
  tx<T>(fn: (db: Db) => Promise<T>): Promise<T>;
}

async function postgresDriver(connectionString: string): Promise<Driver> {
  const { Pool, types } = await import("pg");
  types.setTypeParser(20, Number);
  types.setTypeParser(1700, parseFloat);
  types.setTypeParser(1082, (value: string) => value);
  const pool = new Pool({
    connectionString,
    max: 10,
    ssl: process.env.DATABASE_SSL === "true" ? { rejectUnauthorized: false } : undefined,
  });
  return {
    async query<T extends Row>(text: string, params: unknown[] = []) {
      return (await pool.query(text, params as unknown[])).rows as T[];
    },
    async exec(text) {
      await pool.query(text);
    },
    async tx(fn) {
      const client = await pool.connect();
      try {
        await client.query("BEGIN");
        const result = await fn({
          async query<T extends Row>(text: string, params: unknown[] = []) {
            return (await client.query(text, params as unknown[])).rows as T[];
          },
        });
        await client.query("COMMIT");
        return result;
      } catch (error) {
        await client.query("ROLLBACK");
        throw error;
      } finally {
        client.release();
      }
    },
  };
}

async function localDriver(): Promise<Driver> {
  const { PGlite, types } = await import("@electric-sql/pglite");
  const directory = process.env.PGLITE_DIR || path.join(process.cwd(), "data", "pglite");
  await mkdir(directory, { recursive: true });
  const pg = new PGlite(directory, {
    parsers: {
      [types.INT8]: (value: string) => Number(value),
      [types.NUMERIC]: (value: string) => parseFloat(value),
      [types.DATE]: (value: string) => value,
    },
  });
  await pg.waitReady;
  return {
    async query<T extends Row>(text: string, params: unknown[] = []) {
      return (await pg.query<T>(text, params as unknown[])).rows;
    },
    async exec(text) {
      await pg.exec(text);
    },
    async tx<T>(fn: (db: Db) => Promise<T>) {
      const result = await pg.transaction(async (transaction) =>
        fn({
          async query<R extends Row>(text: string, params: unknown[] = []) {
            return (await transaction.query<R>(text, params as unknown[])).rows;
          },
        }),
      );
      return result as T;
    },
  };
}

async function createAdminIfNeeded(driver: Driver): Promise<void> {
  const [{ count }] = await driver.query<{ count: number }>("SELECT count(*)::int AS count FROM users");
  if (count > 0) return;
  const phone = process.env.ADMIN_PHONE?.trim();
  const password = process.env.ADMIN_PASSWORD;
  if (!phone || !password) {
    console.warn("No users exist. Set ADMIN_PHONE and ADMIN_PASSWORD to create the first Admin.");
    return;
  }
  if (password.length < MIN_PASSWORD_LENGTH) {
    throw new Error(`ADMIN_PASSWORD must have at least ${MIN_PASSWORD_LENGTH} characters.`);
  }
  await driver.query(
    "INSERT INTO users (full_name, phone, password_hash, role) VALUES ($1, $2, $3, 'ADMIN') ON CONFLICT (phone) DO NOTHING",
    [process.env.ADMIN_NAME?.trim() || "Admin", phone, await hashPassword(password)],
  );
}

async function connect(): Promise<Driver> {
  const url = process.env.DATABASE_URL?.trim();
  const driver = url ? await postgresDriver(url) : await localDriver();
  await driver.exec(SCHEMA_SQL);
  await createAdminIfNeeded(driver);
  return driver;
}

const holder = globalThis as unknown as { __pharmacyDb?: Promise<Driver> };

function driver(): Promise<Driver> {
  if (!holder.__pharmacyDb) {
    holder.__pharmacyDb = connect().catch((error) => {
      holder.__pharmacyDb = undefined;
      throw error;
    });
  }
  return holder.__pharmacyDb;
}

export async function sql<T extends Row = Row>(text: string, params: unknown[] = []): Promise<T[]> {
  return (await driver()).query<T>(text, params);
}

/** Runs fn in one transaction. Inside fn, use only the supplied db handle. */
export async function withTx<T>(fn: (db: Db) => Promise<T>): Promise<T> {
  return (await driver()).tx(fn);
}

export async function audit(
  db: Db,
  userId: number | null,
  action: string,
  entityType: string,
  entityId: number | null,
  before: unknown = null,
  after: unknown = null,
): Promise<void> {
  await db.query(
    `INSERT INTO audit_log (user_id, action, entity_type, entity_id, before_data, after_data)
     VALUES ($1, $2, $3, $4, $5::jsonb, $6::jsonb)`,
    [userId, action, entityType, entityId, before === null ? null : JSON.stringify(before), after === null ? null : JSON.stringify(after)],
  );
}
