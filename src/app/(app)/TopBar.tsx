import Link from "next/link";
import { logout } from "@/app/actions/auth";
import type { User } from "@/lib/auth";
import { HomeLink } from "./HomeLink";

export function TopBar({ user }: { user: User }) {
  return (
    <header className="topbar">
      <nav className="topbar-group" aria-label="Main">
        <HomeLink />
        {user.role === "ADMIN" && <Link className="btn" href="/admin/users">Masters</Link>}
      </nav>
      <div className="topbar-group">
        <span className="signed-in">Signed in as {user.full_name}</span>
        <form action={logout}><button className="btn">Log out</button></form>
      </div>
    </header>
  );
}
