# Pharmacy Management

A phone-friendly web app for managing Sale, C&F Sales and Purchase documents and their photos. It has its own database and no connection to Google Sheets.

## What it does

- Home screen with Sale, C&F Sales and Purchase buttons.
- Register tables with every column, photo thumbnails, search and filters.
- Update forms where each photo slot opens after the one before it is filled, with Upload photo and Click photo.
- Excel or CSV bulk upload with a template for each register, a preview and a list of rows to fix.
- Reports for pending by date, uploaded today and missing numbers, with Excel export.
- Uploader, Supervisor and Admin roles, an audit log, and delete and restore.

## Run on your computer

Requires Node.js 20.9 or newer.

1. Run `npm install`.
2. Copy `.env.example` to `.env` and set `SESSION_SECRET`, `ADMIN_PHONE` and `ADMIN_PASSWORD`.
3. Run `npm run dev` and open http://localhost:3000.
4. Sign in with the Admin phone and password.

With `DATABASE_URL` blank, data is kept in the local `data` folder.

## Deploy

1. Create a PostgreSQL database and set `DATABASE_URL`.
2. Set `SESSION_SECRET`, `ADMIN_PHONE` and `ADMIN_PASSWORD`.
3. Set `UPLOAD_DIR` to a folder that is kept between deployments, and include it in backups.
4. Serve the app over HTTPS.
5. Run `npm ci`, `npm run build` and `npm start`.

The database tables and the first Admin are created automatically on first use.

## First steps after signing in

1. Open Masters and add your areas and staff.
2. Open a register, choose Bulk upload, and download its template.
3. Upload the filled template, check the rows, and save.

## Checks

Run `npm test` and `npm run typecheck`.

## Design

- [Database design and build plan](docs/database-design-and-build-plan.md)
- `design/app-preview/` holds the source of the design preview, which uses sample data and needs the Claude Design canvas to run.
