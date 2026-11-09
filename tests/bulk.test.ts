import { describe, expect, it } from "vitest";
import { checkHeaders, checkRow, csvCell, financialYear, parseAmount, parseDate, parseDocNumber } from "../src/lib/bulk";
import { isComplete } from "../src/lib/registers";

describe("parseDate", () => {
  it("reads the accepted formats", () => {
    expect(parseDate("17/09/2024")).toBe("2024-09-17");
    expect(parseDate("11-May-26")).toBe("2026-05-11");
    expect(parseDate(new Date(Date.UTC(2026, 10, 9)))).toBe("2026-11-09");
  });
  it("rejects blank, epoch and impossible dates", () => {
    expect(parseDate("")).toBeNull();
    expect(parseDate(null)).toBeNull();
    expect(parseDate("01/01/1970")).toBeNull();
    expect(parseDate("31/02/2026")).toBeNull();
  });
});

describe("numbers", () => {
  it("accepts amounts with commas and rejects text or negatives", () => {
    expect(parseAmount("1,25,000.50")).toBe(125000.5);
    expect(parseAmount(0)).toBe(0);
    expect(parseAmount("abc")).toBeNull();
    expect(parseAmount("-5")).toBeNull();
  });
  it("removes challan date suffixes and splits the series", () => {
    expect(parseDocNumber("CP003584    18/09")).toEqual({ no: "CP003584", prefix: "CP", seq: 3584 });
    expect(parseDocNumber("A042016")).toEqual({ no: "A042016", prefix: "A", seq: 42016 });
    expect(parseDocNumber("ABC")).toBeNull();
  });
  it("uses the April to March financial year", () => {
    expect(financialYear("2026-04-01")).toBe("2026-27");
    expect(financialYear("2026-03-31")).toBe("2025-26");
    expect(financialYear("2000-01-01")).toBe("1999-00");
  });
});

describe("templates", () => {
  it("requires the exact columns in order", () => {
    expect(checkHeaders("CF_SALE", ["Invoice No", "Invoice Date", "Customer", "Invoice Amount"])).toBeNull();
    expect(checkHeaders("CF_SALE", ["Customer", "Invoice No", "Invoice Date", "Invoice Amount"])).toContain("Columns must be");
  });
  it("validates a row", () => {
    const areas = new Map([["panbazar", 7]]);
    const good = checkRow("PURCHASE", ["Supplier A", "CP1", "09/11/2026", "100", "4", "", "Panbazar", ""], areas);
    expect(good.outcome).toBe("ready");
    if (good.outcome === "ready") expect(good.value).toMatchObject({ items: 4, areaId: 7, year: "2026-27" });
    expect(checkRow("PURCHASE", ["Supplier A", "CP1", "", "100"], areas)).toMatchObject({ outcome: "failed", reason: "Challan Date is blank" });
    expect(checkRow("PURCHASE", ["Supplier A", "CP1", "09/11/2026", "100", "", "", "Nowhere", ""], areas)).toMatchObject({ outcome: "failed", reason: "Area not found" });
    expect(checkRow("PURCHASE", ["Supplier A", "CP1", "09/11/2026", "100", "1.5", "", "", ""], areas)).toMatchObject({ outcome: "failed" });
  });
  it("neutralizes formulas in downloaded CSV files", () => {
    expect(csvCell("=SUM(A1)")).toBe("'=SUM(A1)");
    expect(csvCell('a,"b"')).toBe('"a,""b"""');
  });
});

describe("status rules", () => {
  it("needs every required photo group", () => {
    expect(isComplete("SALE", { RECEIPT_COPY: 1 })).toBe(true);
    expect(isComplete("CF_SALE", { RECEIPT_COPY: 1 })).toBe(false);
    expect(isComplete("CF_SALE", { RECEIPT_COPY: 1, INVOICE_PHOTO: 2 })).toBe(true);
    expect(isComplete("PURCHASE", { GOODS_PHOTO: 3 })).toBe(false);
    expect(isComplete("PURCHASE", { CHALLAN_PHOTO: 1 })).toBe(true);
  });
});
