import { requireUser } from "@/lib/auth";
import { TopBar } from "./TopBar";

export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser();
  return (
    <>
      <TopBar user={user} />
      {children}
    </>
  );
}
