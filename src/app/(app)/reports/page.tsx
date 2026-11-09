import { MANAGERS, requireUser } from "@/lib/auth";
import { missingNumbers, pendingByDate, uploadedToday } from "@/lib/reports";

export default async function ReportsPage() {
  await requireUser(MANAGERS);
  const [pending, today, missing] = await Promise.all([pendingByDate(), uploadedToday(), missingNumbers()]);
  return (
    <main className="page">
      <div className="row between">
        <h1>Reports</h1>
        <a className="btn" href="/api/reports/export">Export to Excel</a>
      </div>
      <div className="grid-auto">
        <section className="card" style={{ gap: 0 }}>
          <h2 style={{ marginBottom: 8 }}>Pending by date</h2>
          {pending.length === 0 && <p className="muted">Nothing pending.</p>}
          {pending.map((row) => <div key={row.date + row.label} className="kv"><span>{row.date} &middot; {row.label}</span><span>{row.count}</span></div>)}
        </section>
        <section className="card" style={{ gap: 0 }}>
          <h2 style={{ marginBottom: 8 }}>Uploaded today</h2>
          {today.map((row) => <div key={row.label} className="kv"><span>{row.label}</span><span>{row.count}</span></div>)}
        </section>
        <section className="card" style={{ gap: 0 }}>
          <h2 style={{ marginBottom: 8 }}>Missing numbers</h2>
          {missing.length === 0 && <p className="muted">No gaps found.</p>}
          {missing.map((row) => <div key={row.label + row.from} className="kv"><span>{row.label}: {row.from === row.to ? row.from : `${row.from} to ${row.to}`}</span><span>{row.count}</span></div>)}
        </section>
      </div>
    </main>
  );
}
