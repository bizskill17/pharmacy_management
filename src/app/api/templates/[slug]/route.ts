import { apiUser, MANAGERS } from "@/lib/auth";
import { templateWorkbook } from "@/lib/bulk-server";
import { registerBySlug } from "@/lib/registers";

export async function GET(request: Request, context: { params: Promise<{ slug: string }> }) {
  const user = await apiUser(request, MANAGERS);
  if (user instanceof Response) return user;
  const register = registerBySlug((await context.params).slug);
  if (!register) return new Response("Not found", { status: 404 });
  return new Response(await templateWorkbook(register.type), {
    headers: {
      "Content-Type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "Content-Disposition": `attachment; filename="${register.slug}-template.xlsx"`,
    },
  });
}
