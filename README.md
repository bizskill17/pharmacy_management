# Pharmacy Management

A phone-friendly PHP and MySQL web app for managing Sale, C&F Sales and Purchase documents and their photos. It runs on ordinary Hostinger web hosting, needs no installed libraries, and has no connection to Google Sheets.

## What it does

- Home screen with Sale, C&F Sales and Purchase buttons.
- Register tables with every column, photo thumbnails, search and filters.
- Update forms where each photo slot opens after the one before it is filled, with Upload photo and Click photo.
- Excel or CSV bulk upload with a template for each register, a preview and a list of rows to fix.
- Reports for pending by date, uploaded today and missing numbers, with Excel export.
- Uploader, Supervisor and Admin roles, an audit log, and delete and restore.

## Requirements

- PHP 8.1 or newer with the `pdo_mysql`, `mbstring`, `zip` and `SimpleXML` extensions.
- MySQL 8 or MariaDB 10.2 or newer.

## Deployment

Each push to the `php-app` branch that changes `site/` is uploaded by FTP through GitHub Actions.

Create these repository secrets under **Settings > Secrets and variables > Actions**:

| Secret | Value |
| --- | --- |
| `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_PORT`, `FTP_REMOTE_DIR` | Hostinger FTP details |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Hostinger MySQL database |
| `ADMIN_PHONE`, `ADMIN_PASSWORD` | First Admin login; password of at least 8 characters |

Nothing is uploaded until every database and Admin secret exists. Database tables and the first Admin are created on the first visit.

Uploaded photos are kept in `site/storage/uploads` on the server and are never replaced by a deployment. Include that folder and the database in backups.

## First steps after signing in

1. Open Masters and add your areas and staff.
2. Open a register, choose Bulk upload, and download its template.
3. Upload the filled template, check the rows, and save.

## Run locally

Create `site/app/config.local.php` from `site/app/config.example.php`, then run:

```
cd site
php -S 127.0.0.1:8000 index.php
```

## Design

- [Database design and build plan](docs/database-design-and-build-plan.md)
- `design/app-preview/` holds the source of the design preview, which uses sample data and needs the Claude Design canvas to run.
