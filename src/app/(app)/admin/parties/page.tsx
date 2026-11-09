import { sql } from "@/lib/db";
import { PartyRowForm } from "../Forms";

const LIMIT = 50;

export default async function PartiesPage({ searchParams }: { searchParams: Promise<{ q?: string }> }) {
  const query = (await searchParams).q?.trim() ?? "";
  const pattern = `%${query.replace(/[\\%_]/g, (character) => `\\${character}`)}%`;
  const [parties, areas] = await Promise.all([
    sql<{ id: number; name: string; source_name: string; area_id: number | null; is_active: boolean }>(
      `SELECT id, name, source_name, area_id, is_active FROM parties WHERE name ILIKE $1 OR source_name ILIKE $1 ORDER BY name LIMIT ${LIMIT}`,
      [pattern],
    ),
    sql<{ id: number; name: string }>("SELECT id, name FROM areas WHERE is_active ORDER BY name"),
  ]);
  return (
    <>
      <form method="get" className="row">
        <div className="field" style={{ flex: "1 1 280px" }}>
          <label htmlFor="party-search">Search party</label>
          <input id="party-search" name="q" className="input" type="search" defaultValue={query} />
        </div>
        <button className="btn primary" style={{ alignSelf: "end" }}>Search</button>
      </form>
      <p className="muted">Parties are added automatically by bulk upload. Showing up to {LIMIT}.</p>
      {parties.map((party) => (
        <PartyRowForm key={party.id} id={party.id} name={party.name} source={party.source_name} areaId={party.area_id} active={party.is_active} areas={areas} />
      ))}
    </>
  );
}
