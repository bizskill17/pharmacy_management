export type DocType = "SALE" | "CF_SALE" | "PURCHASE";
export type Kind = "RECEIPT_COPY" | "INVOICE_PHOTO" | "CHALLAN_PHOTO" | "GOODS_PHOTO";
export type TemplateKey = "party" | "no" | "date" | "amount" | "receipt" | "mr" | "area" | "address" | "items";

export interface PhotoGroup {
  kind: Kind;
  label: string;
  max: number;
  required: boolean;
}

export interface TemplateColumn {
  header: string;
  key: TemplateKey;
  required: boolean;
}

export type TextField =
  | "party"
  | "no"
  | "date"
  | "amount"
  | "receipt"
  | "mr"
  | "area"
  | "address"
  | "items"
  | "uploadDate"
  | "uploader"
  | "remarks";

export type Column =
  | { type: "text"; label: string; field: TextField; right?: boolean }
  | { type: "link"; label: string }
  | { type: "photo"; label: string; kind: Kind; index: number }
  | { type: "status"; label: string };

export type FormSection = { type: "photos"; kind: Kind } | { type: "auto" } | { type: "remarks" };

export interface Register {
  type: DocType;
  slug: string;
  label: string;
  numberLabel: string;
  dateLabel: string;
  partyLabel: string;
  amountLabel: string;
  groups: PhotoGroup[];
  template: TemplateColumn[];
  columns: Column[];
  form: FormSection[];
}

function photoColumns(kind: Kind, label: string, count: number): Column[] {
  return Array.from({ length: count }, (_, position) => ({
    type: "photo" as const,
    kind,
    index: position + 1,
    label: position === 0 ? label : `${label} ${position + 1}`,
  }));
}

const uploadColumns: Column[] = [
  { type: "text", label: "Upload Date", field: "uploadDate" },
  { type: "text", label: "Uploaded By (Employee)", field: "uploader" },
];

const SALE: Register = {
  type: "SALE",
  slug: "sale",
  label: "Sale",
  numberLabel: "Invoice No",
  dateLabel: "Invoice Date",
  partyLabel: "Customer",
  amountLabel: "Invoice Amount",
  groups: [{ kind: "RECEIPT_COPY", label: "Receipt Copy", max: 1, required: true }],
  template: [
    { header: "Customer", key: "party", required: true },
    { header: "Invoice No", key: "no", required: true },
    { header: "Invoice Date", key: "date", required: true },
    { header: "Invoice Amount", key: "amount", required: true },
    { header: "Receipt", key: "receipt", required: false },
    { header: "MR", key: "mr", required: false },
    { header: "Area", key: "area", required: false },
    { header: "Address", key: "address", required: false },
  ],
  columns: [
    { type: "text", label: "Customer", field: "party" },
    { type: "link", label: "Invoice No" },
    { type: "text", label: "Invoice Date", field: "date" },
    { type: "text", label: "Invoice Amount", field: "amount", right: true },
    { type: "text", label: "Receipt", field: "receipt" },
    { type: "text", label: "MR", field: "mr" },
    { type: "text", label: "Area", field: "area" },
    { type: "text", label: "Address", field: "address" },
    ...photoColumns("RECEIPT_COPY", "Receipt Copy", 1),
    ...uploadColumns,
    { type: "status", label: "Status" },
  ],
  form: [{ type: "photos", kind: "RECEIPT_COPY" }, { type: "auto" }],
};

const CF_SALE: Register = {
  type: "CF_SALE",
  slug: "cf-sales",
  label: "C&F Sales",
  numberLabel: "Invoice No",
  dateLabel: "Invoice Date",
  partyLabel: "Customer",
  amountLabel: "Invoice Amount",
  groups: [
    { kind: "RECEIPT_COPY", label: "Receipt Copy", max: 1, required: true },
    { kind: "INVOICE_PHOTO", label: "Invoice Photo", max: 10, required: true },
  ],
  template: [
    { header: "Invoice No", key: "no", required: true },
    { header: "Invoice Date", key: "date", required: true },
    { header: "Customer", key: "party", required: true },
    { header: "Invoice Amount", key: "amount", required: true },
  ],
  columns: [
    { type: "link", label: "Invoice No" },
    { type: "text", label: "Invoice Date", field: "date" },
    { type: "text", label: "Customer", field: "party" },
    { type: "text", label: "Invoice Amount", field: "amount", right: true },
    ...photoColumns("RECEIPT_COPY", "Receipt Copy", 1),
    ...photoColumns("INVOICE_PHOTO", "Invoice Photo", 10),
    ...uploadColumns,
    { type: "status", label: "Status" },
  ],
  form: [{ type: "photos", kind: "RECEIPT_COPY" }, { type: "photos", kind: "INVOICE_PHOTO" }, { type: "auto" }],
};

const PURCHASE: Register = {
  type: "PURCHASE",
  slug: "purchase",
  label: "Purchase",
  numberLabel: "Challan No",
  dateLabel: "Challan Date",
  partyLabel: "Supplier",
  amountLabel: "Amount",
  groups: [
    { kind: "CHALLAN_PHOTO", label: "Challan Photo", max: 40, required: true },
    { kind: "GOODS_PHOTO", label: "Goods Photo", max: 40, required: false },
  ],
  template: [
    { header: "Supplier", key: "party", required: true },
    { header: "Challan No", key: "no", required: true },
    { header: "Challan Date", key: "date", required: true },
    { header: "Amount", key: "amount", required: true },
    { header: "No of Item", key: "items", required: false },
    { header: "MR", key: "mr", required: false },
    { header: "Area", key: "area", required: false },
    { header: "Address", key: "address", required: false },
  ],
  columns: [
    { type: "text", label: "Supplier", field: "party" },
    { type: "link", label: "Challan No" },
    { type: "text", label: "Challan Date", field: "date" },
    { type: "text", label: "Amount", field: "amount", right: true },
    { type: "text", label: "No of Item", field: "items" },
    { type: "text", label: "MR", field: "mr" },
    { type: "text", label: "Area", field: "area" },
    { type: "text", label: "Address", field: "address" },
    ...photoColumns("CHALLAN_PHOTO", "Challan Photo", 40),
    ...photoColumns("GOODS_PHOTO", "Goods Photo", 40),
    ...uploadColumns,
    { type: "status", label: "Upload Status" },
    { type: "text", label: "Remarks", field: "remarks" },
  ],
  form: [{ type: "photos", kind: "CHALLAN_PHOTO" }, { type: "photos", kind: "GOODS_PHOTO" }, { type: "auto" }, { type: "remarks" }],
};

export const REGISTERS: Register[] = [SALE, CF_SALE, PURCHASE];

export function registerBySlug(slug: string): Register | undefined {
  return REGISTERS.find((register) => register.slug === slug);
}

export function registerByType(type: DocType): Register {
  return REGISTERS.find((register) => register.type === type) as Register;
}

/** True when every required photo group has at least one photo. */
export function isComplete(type: DocType, counts: Partial<Record<Kind, number>>): boolean {
  return registerByType(type).groups.every((group) => !group.required || (counts[group.kind] ?? 0) > 0);
}

export function slotLabel(label: string, position: number): string {
  return position === 1 ? label : `${label} ${position}`;
}
