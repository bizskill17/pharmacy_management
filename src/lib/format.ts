/** Converts a stored YYYY-MM-DD date to DD/MM/YYYY. */
export function displayDate(iso: string | null | undefined): string {
  if (!iso) return "-";
  const [year, month, day] = iso.slice(0, 10).split("-");
  return `${day}/${month}/${year}`;
}

export function money(amount: number): string {
  return `₹${Number(amount).toLocaleString("en-IN")}`;
}
