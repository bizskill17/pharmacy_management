import { toggleArea } from "@/app/actions/admin";
import { sql } from "@/lib/db";
import { NewAreaForm } from "../Forms";

export default async function AreasPage() {
  const areas = await sql<{ id: number; name: string; is_active: boolean }>("SELECT id, name, is_active FROM areas ORDER BY name");
  return (
    <>
      <NewAreaForm />
      <section className="card" style={{ gap: 0 }}>
        <h2 style={{ marginBottom: 8 }}>Areas</h2>
        {areas.length === 0 && <p className="muted">No areas yet.</p>}
        {areas.map((area) => (
          <form key={area.id} action={toggleArea} className="kv" style={{ alignItems: "center" }}>
            <input type="hidden" name="id" value={area.id} />
            <span>{area.name}{area.is_active ? "" : " (inactive)"}</span>
            <button className="btn">{area.is_active ? "Deactivate" : "Activate"}</button>
          </form>
        ))}
      </section>
    </>
  );
}
