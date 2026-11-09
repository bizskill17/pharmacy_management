# Sale, C&F and Purchase App: Database Design and Build Plan

Last updated: 9 November 2026

## Summary

Build one phone-friendly web app with its own database and file storage, and no connection to Google Sheets. All three registers go into one `documents` table, and every photo becomes one row in an `attachments` table.

The design rests on four decisions:

- **One table, three types.** Sale, C&F Sales and Purchase share almost the same fields, so `doc_type` separates them. Pending, uploaded-today and missing-number reports each become one query.
- **No photo columns.** 102 of the 132 sheet columns only hold photo paths. They become rows, so a challan with 3 photos stores 3 rows instead of 80 mostly empty columns.
- **Real data types.** Dates are true dates and amounts are numbers, which removes the mixed date formats and text sorting in the sheet.
- **Linked masters.** Parties, areas and users are chosen from lists. The exact name from the upload file is kept so imports can match it.

Stack: PHP 8.1 or newer and MySQL or MariaDB on Hostinger web hosting, with photos stored privately on the hosting account and deployment by FTP from GitHub. Expiry is out of scope for now, but can be added later as a fourth `doc_type`.

## Database structure

The database has seven tables, with `documents` at the centre. Every table uses a `bigint` primary key called `id`.

### documents

One row per invoice or challan.

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| doc_type | enum | Yes | SALE, CF_SALE, PURCHASE |
| doc_no | varchar(30) | Yes | Clean number, e.g. A042016, CS000083, CP003584 |
| doc_prefix | varchar(10) | Yes | Series letters, e.g. A, RR, CS, CP |
| doc_seq | integer | Yes | Number part, used to find gaps |
| doc_date | date | Yes | Invoice or challan date |
| financial_year | char(7) | Yes | Set from doc_date, e.g. 2026-27 |
| party_id | FK parties | No | Customer or supplier; blank until matched |
| party_name_raw | varchar(200) | No | Exact name from the source |
| amount | numeric(14,2) | Yes | Zero is allowed |
| item_count | integer | No | Purchase: No of Item |
| receipt_ref | varchar(50) | No | Sale: Receipt, meaning to be confirmed |
| mr_name | varchar(120) | No | Medical representative |
| area_id | FK areas | No | |
| address | varchar(255) | No | |
| status | enum | Yes | PENDING, UPLOADED; default PENDING |
| remarks | text | No | |
| uploaded_by | FK users | No | Set by the server |
| legacy_uploaded_by | varchar(120) | No | Old employee names from the sheet |
| uploaded_at | timestamptz | No | Set by the server |
| import_batch_id | FK import_batches | No | Which file created the row |
| created_by | FK users | No | |
| created_at, updated_at | timestamptz | Yes | |
| deleted_at | timestamptz | No | Hides a row without erasing it |

### attachments

One row per file.

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| document_id | FK documents | Yes | |
| kind | enum | Yes | RECEIPT_COPY, INVOICE_PHOTO, CHALLAN_PHOTO, GOODS_PHOTO |
| file_key | varchar(500) | Yes | Location in file storage |
| original_name | varchar(255) | No | |
| mime_type | varchar(100) | Yes | |
| size_bytes | bigint | Yes | |
| sort_order | smallint | Yes | Photo 1, 2, 3 and so on |
| legacy_path | varchar(500) | No | Old path, e.g. Sale_Images/... |
| uploaded_by | FK users | No | |
| uploaded_at | timestamptz | Yes | |
| deleted_at | timestamptz | No | |

### parties

Customers and suppliers in one list, because some parties appear on both sides.

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| name | varchar(120) | Yes | Clean display name |
| place | varchar(120) | No | |
| area_id | FK areas | No | |
| is_customer | boolean | Yes | |
| is_supplier | boolean | Yes | |
| source_name | varchar(200) | No | Unique; exact text from the upload file |
| is_active | boolean | Yes | |
| created_at, updated_at | timestamptz | Yes | |

### areas

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| name | varchar(100) | Yes | Unique |
| is_active | boolean | Yes | |

### users

Login accounts only. HR data such as salary, PAN and Aadhaar stays out of this app.

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| full_name | varchar(120) | Yes | |
| phone | varchar(15) | Yes | Unique; used to log in |
| email | varchar(150) | No | |
| password_hash | varchar(255) | Yes | Never a plain passcode |
| role | enum | Yes | ADMIN, SUPERVISOR, UPLOADER |
| is_active | boolean | Yes | |
| last_login_at | timestamptz | No | |
| created_at, updated_at | timestamptz | Yes | |

### import_batches

One row per uploaded template or history file.

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| doc_type | enum | Yes | |
| source_kind | enum | Yes | TEMPLATE_UPLOAD, HISTORY |
| file_name | varchar(255) | Yes | |
| rows_total, rows_created, rows_skipped, rows_failed | integer | Yes | |
| error_report | jsonb | No | Failed rows and reasons |
| imported_by | FK users | Yes | |
| imported_at | timestamptz | Yes | |

### Audit trail

Use `audit_log` as a supporting table for sensitive changes: user_id, action, entity_type, entity_id, before_data (jsonb), after_data (jsonb) and created_at.

### Key rules and indexes

| Rule or index | Purpose |
| --- | --- |
| Unique (doc_type, doc_no, financial_year), active rows only | Blocks duplicate entries |
| Index (doc_type, status, doc_date) | Pending reports |
| Index (doc_type, doc_prefix, financial_year, doc_seq) | Missing-number report |
| Index (party_id, doc_date) | Party history |
| Index (uploaded_at) | Uploaded-today report |
| Index (document_id, kind) on attachments | Loading a document's files |

## Field mapping

All sheet fields have a place in the new database, except the 10 C&F Sales Goods Photo columns, which are dropped. A dash means that register has no such field.

| New field | Sale | C&F Sales | Purchase | Change needed |
| --- | --- | --- | --- | --- |
| doc_no, doc_prefix, doc_seq | Invoice No | Invoice No | Challan No | Remove suffixes such as `18/09` from Purchase |
| doc_date, financial_year | Invoice Date | Invoice Date | Challan Date | Read both date formats; send blanks and 01/01/1970 for review |
| party_name_raw, party_id | Customer | Customer | Supplier | Match or create the party |
| amount | Invoice Amount | Invoice Amount | Amount | Convert to number |
| item_count | - | - | No of Item | |
| receipt_ref | Receipt | - | - | Confirm meaning first |
| mr_name | MR | - | MR | |
| area_id | Area | - | Area | Link to areas |
| address | Address | - | Address | |
| status | Status | Status | Upload Status | `PENDING RC/CN` becomes PENDING |
| remarks | - | - | Remarks | |
| uploaded_at | Upload Date | Upload Date | Upload Date | Date only for old rows |
| uploaded_by or legacy_uploaded_by | Uploaded By (Employee) | Uploaded By (Employee) | Uploaded By (Employee) | Link to a user where the name matches |
| attachments: RECEIPT_COPY | Receipt Copy | Receipt Copy | - | One row per file |
| attachments: INVOICE_PHOTO | - | Invoice Photo, 2 to 10 | - | One row per file |
| attachments: CHALLAN_PHOTO | - | - | Challan Photo, 2 to 40 | One row per file |
| attachments: GOODS_PHOTO | - | - | Goods Photo, 2 to 40 | One row per file |

## App features, roles and rules

The first version covers login, master lists, document upload, bulk uploads and three reports.

| Role | Can do |
| --- | --- |
| Uploader | Find pending documents, add photos, see their own uploads |
| Supervisor | Everything an Uploader can do, plus correct documents, bulk upload files and view all reports |
| Admin | Everything a Supervisor can do, plus manage users, parties and areas |

**Main screens**

- **Home and registers:** the app opens with three buttons: Sale, C&F Sales and Purchase. There is no left menu; a Home button returns to them, and Bulk upload and Reports open from each register. Each register table shows all its columns, with a thumbnail in each photo column, and can be filtered by date, party and area.
- **Document page:** details, photos and upload history.
- **Upload:** each photo slot offers Upload photo (from the device) or Click photo (camera), then submit.
- **Bulk upload:** download a template, upload the filled file, preview it, then see created, skipped and failed rows.
- **Reports:** pending by date, uploaded today, and missing numbers, each exportable to Excel.
- **Masters:** users, parties and areas.

**Business rules**

1. A document becomes UPLOADED when its required files are present. Proposed minimum: receipt copy for Sale; receipt copy and invoice photo for C&F Sales; challan photo for Purchase.
2. The server records who uploaded and when, so staff cannot type those values.
3. The photo limits stay configurable: 10 invoice photos for C&F Sales and 40 per kind for Purchase.
4. Photos are compressed on the phone before upload.
5. A duplicate document number is rejected and shown in the import report.
6. Corrections and deletions are logged in `audit_log`, and deleted documents can be restored.
7. The missing-number report checks for gaps in `doc_seq` within each type, series and financial year.

## Bulk upload templates

Each register gets its own downloadable template, and every valid row becomes a PENDING document ready for photos. This uses the existing `documents` and `import_batches` tables, so no new table is needed.

### Sale template

| Order | Column | Required | Saved to |
| --- | --- | --- | --- |
| 1 | Customer | Yes | party_name_raw, party_id |
| 2 | Invoice No | Yes | doc_no |
| 3 | Invoice Date | Yes | doc_date |
| 4 | Invoice Amount | Yes | amount |
| 5 | Receipt | No | receipt_ref |
| 6 | MR | No | mr_name |
| 7 | Area | No | area_id |
| 8 | Address | No | address |

### C&F Sales template

| Order | Column | Required | Saved to |
| --- | --- | --- | --- |
| 1 | Invoice No | Yes | doc_no |
| 2 | Invoice Date | Yes | doc_date |
| 3 | Customer | Yes | party_name_raw, party_id |
| 4 | Invoice Amount | Yes | amount |

### Purchase template

| Order | Column | Required | Saved to |
| --- | --- | --- | --- |
| 1 | Supplier | Yes | party_name_raw, party_id |
| 2 | Challan No | Yes | doc_no |
| 3 | Challan Date | Yes | doc_date |
| 4 | Amount | Yes | amount |
| 5 | No of Item | No | item_count |
| 6 | MR | No | mr_name |
| 7 | Area | No | area_id |
| 8 | Address | No | address |

The Required column is proposed; change any row that should work differently.

### Upload steps

1. A Supervisor or Admin chooses Sale, C&F Sales or Purchase and downloads the Excel template.
2. They fill it in and upload it as Excel or CSV.
3. The preview shows valid, duplicate and failed rows before anything is saved.
4. On confirmation, valid rows are saved as PENDING and the result is recorded in `import_batches`.
5. Failed rows can be downloaded with the row number and reason, corrected and uploaded again.

### Upload checks

- **Columns:** headings must match the template and stay in the same order.
- **Dates:** Excel dates, `DD/MM/YYYY` and `DD-Mon-YY` are accepted. Blank dates and 01/01/1970 fail.
- **Numbers:** amounts must be zero or more, and No of Item must be a whole number.
- **Document numbers:** suffixes such as `18/09` are removed. `doc_prefix`, `doc_seq` and `financial_year` are filled in automatically.
- **Duplicates:** a number repeated in the file, or already saved for that register and financial year, is skipped.
- **Parties:** Customer and Supplier names are matched to `parties`. A new name creates a new party marked customer or supplier.
- **Area:** a filled Area must already exist in `areas`, otherwise the row fails. This stops spelling mistakes from creating new areas.

## Update forms

Each register has its own update form, and a photo slot opens only after the slot before it has a photo. The fields use the existing `attachments` and `documents` tables, so no new table is needed.

| Form | Fields, in order |
| --- | --- |
| Sale | Receipt Copy; Upload Date; Uploaded By (Employee) |
| C&F Sales | Receipt Copy; Invoice Photo to Invoice Photo 10; Upload Date; Uploaded By (Employee) |
| Purchase | Challan Photo to Challan Photo 40; Goods Photo to Goods Photo 40; Upload Date; Uploaded By (Employee); Remarks |

- **Photo order:** Invoice Photo 2 stays hidden until Invoice Photo is added. The same rule applies to every Invoice, Goods and Challan photo.
- **Removing photos:** only the last added photo in a group can be removed, so the sequence never has a gap.
- **Upload Date and Uploaded By:** both are shown on the form and filled automatically from today's date and the signed-in employee.

## Build plan

The build takes roughly 8 to 10 weeks for one full-time developer. These durations are planning estimates and depend on the answers below.

1. **Confirm and check the data (2 to 3 days).** Answer the open questions, then check all rows for duplicates, bad dates and status values.
2. **Foundation (1 week).** Set up the app, database, file storage, login, roles and master screens.
3. **Documents and uploads (2 weeks).** Build the pending list, document page, phone photo upload, status rules and audit log.
4. **Bulk upload templates (1 week).** Build the three templates, file upload, preview, party matching, duplicate checks and the error report.
5. **Reports (1 week).** Build pending by date, uploaded today, missing numbers and Excel export.
6. **History migration (1 to 2 weeks).** Export the sheet once to CSV, copy the photo folders, load both, then reconcile counts and totals. This is a one-time copy, so no link to the sheet remains. Skip it if you start fresh.
7. **Trial and go-live (2 weeks).** Train staff, run the app alongside the sheet, compare results, then stop using the sheet.

Go-live checks: row counts and amount totals match by register, a sample of photos opens correctly, and daily backups of the database and files are tested with a restore.

## Questions to confirm before building

These answers can change the import, status rules or schema. They come from sample rows, not a check of every row.

- [ ] **History:** do old records and photos move to the app, or does it start fresh?
- [ ] **Sale `Receipt`:** what do the small numbers on recent `RR` rows mean?
- [ ] **`PENDING RC/CN`:** what do RC and CN stand for, and does a credit note need its own file type?
- [ ] **Required files:** is the proposed minimum in business rule 1 correct?
- [ ] **Numbering:** do invoice and challan numbers restart each April?
- [ ] **MR:** is there a fixed list of representatives, and should they log in?
- [ ] **Hosting:** where will the app run, and is PostgreSQL available there?
