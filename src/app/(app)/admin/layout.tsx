import Link from "next/link";
import { ADMINS, requireUser } from "@/lib/auth";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  await requireUser(ADMINS);
  return (
    <main className="page">
      <h1>Masters</h1>
      <nav className="row" aria-label="Masters">
        <Link className="toggle" href="/admin/users">Users</Link>
        <Link className="toggle" href="/admin/parties">Parties</Link>
        <Link className="toggle" href="/admin/areas">Areas</Link>
      </nav>
      {children}
    </main>
  );
}
