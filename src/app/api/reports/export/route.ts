import ExcelJS from "exceljs";
import { apiUser, MANAGERS } from "@/lib/auth";
import { missingNumbers, pendingByDate, uploadedToday } from "@/lib/reports";

export async function GET(request: Request) {
  const user = await apiUser(request, MANAGERS);
  if (user instanceof Response) return user;
  const [pending, today, missing] = await Promise.all([pendingByDate(), uploadedToday(), missingNumbers()]);
  const workbook = new ExcelJS.Workbook();
  const addSheet = (name: string, headers: string[], rows: (string | number)[][]) => {
    const sheet = workbook.addWorksheet(name);
    sheet.addRow(headers).font = { bold: true };
    sheet.addRows(rows);
    sheet.columns.forEach((column) => { column.width = 24; });
  };
  addSheet("Pending by date", ["Date", "Register", "Count"], pending.map((row) => [row.date, row.label, row.count]));
  addSheet("Uploaded today", ["Register", "Count"], today.map((row) => [row.label, row.count]));
  addSheet("Missing numbers", ["Register and year", "From", "To", "Count"], missing.map((row) => [row.label, row.from, row.to, row.count]));
  return new Response((await workbook.xlsx.writeBuffer()) as unknown as ArrayBuffer, {
    headers: {
      "Content-Type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "Content-Disposition": 'attachment; filename="reports.xlsx"',
    },
  });
}
