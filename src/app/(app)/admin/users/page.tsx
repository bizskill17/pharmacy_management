import { sql } from "@/lib/db";
import { NewUserForm, UserRowForm } from "../Forms";

export default async function UsersPage() {
  const users = await sql<{ id: number; full_name: string; phone: string; role: string; is_active: boolean }>(
    "SELECT id, full_name, phone, role, is_active FROM users ORDER BY full_name",
  );
  return (
    <>
      <NewUserForm />
      {users.map((user) => <UserRowForm key={user.id} id={user.id} name={user.full_name} phone={user.phone} role={user.role} active={user.is_active} />)}
    </>
  );
}
