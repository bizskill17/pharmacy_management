<?php
declare(strict_types=1);

/** Idempotent MySQL schema, applied automatically on first use. */
return [
    "CREATE TABLE IF NOT EXISTS users (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(120) NOT NULL,
      phone VARCHAR(15) NOT NULL UNIQUE,
      email VARCHAR(150) NULL,
      password_hash VARCHAR(255) NOT NULL,
      role ENUM('ADMIN','SUPERVISOR','UPLOADER') NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      last_login_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS login_attempts (
      phone VARCHAR(15) PRIMARY KEY,
      failures INT NOT NULL DEFAULT 0,
      locked_until DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS areas (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(100) NOT NULL UNIQUE,
      is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS parties (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      place VARCHAR(120) NULL,
      area_id BIGINT NULL,
      is_customer TINYINT(1) NOT NULL DEFAULT 0,
      is_supplier TINYINT(1) NOT NULL DEFAULT 0,
      source_name VARCHAR(200) NULL UNIQUE,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_party_area FOREIGN KEY (area_id) REFERENCES areas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS import_batches (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      doc_type ENUM('SALE','CF_SALE','PURCHASE') NOT NULL,
      source_kind ENUM('TEMPLATE_UPLOAD','HISTORY') NOT NULL,
      file_name VARCHAR(255) NOT NULL,
      rows_total INT NOT NULL DEFAULT 0,
      rows_created INT NOT NULL DEFAULT 0,
      rows_skipped INT NOT NULL DEFAULT 0,
      rows_failed INT NOT NULL DEFAULT 0,
      error_report LONGTEXT NULL,
      imported_by BIGINT NOT NULL,
      imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_batch_user FOREIGN KEY (imported_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS documents (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      doc_type ENUM('SALE','CF_SALE','PURCHASE') NOT NULL,
      doc_no VARCHAR(30) NOT NULL,
      doc_prefix VARCHAR(10) NOT NULL,
      doc_seq INT NOT NULL,
      doc_date DATE NOT NULL,
      financial_year CHAR(7) NOT NULL,
      party_id BIGINT NULL,
      party_name_raw VARCHAR(200) NULL,
      amount DECIMAL(14,2) NOT NULL,
      item_count INT NULL,
      receipt_ref VARCHAR(50) NULL,
      mr_name VARCHAR(120) NULL,
      area_id BIGINT NULL,
      address VARCHAR(255) NULL,
      status ENUM('PENDING','UPLOADED') NOT NULL DEFAULT 'PENDING',
      remarks TEXT NULL,
      uploaded_by BIGINT NULL,
      legacy_uploaded_by VARCHAR(120) NULL,
      uploaded_at DATETIME NULL,
      import_batch_id BIGINT NULL,
      created_by BIGINT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL,
      active_flag TINYINT GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED,
      UNIQUE KEY documents_unique_active (doc_type, doc_no, financial_year, active_flag),
      KEY documents_pending (doc_type, status, doc_date),
      KEY documents_series (doc_type, doc_prefix, financial_year, doc_seq),
      KEY documents_party (party_id, doc_date),
      KEY documents_uploaded_at (uploaded_at),
      CONSTRAINT fk_doc_party FOREIGN KEY (party_id) REFERENCES parties(id),
      CONSTRAINT fk_doc_area FOREIGN KEY (area_id) REFERENCES areas(id),
      CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id),
      CONSTRAINT fk_doc_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches(id),
      CONSTRAINT fk_doc_creator FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS attachments (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      document_id BIGINT NOT NULL,
      kind ENUM('RECEIPT_COPY','INVOICE_PHOTO','CHALLAN_PHOTO','GOODS_PHOTO') NOT NULL,
      file_key VARCHAR(500) NOT NULL,
      original_name VARCHAR(255) NULL,
      mime_type VARCHAR(100) NOT NULL,
      size_bytes BIGINT NOT NULL,
      sort_order SMALLINT NOT NULL,
      legacy_path VARCHAR(500) NULL,
      uploaded_by BIGINT NULL,
      uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL,
      KEY attachments_document (document_id, kind),
      CONSTRAINT fk_att_doc FOREIGN KEY (document_id) REFERENCES documents(id),
      CONSTRAINT fk_att_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS audit_log (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT NULL,
      action VARCHAR(40) NOT NULL,
      entity_type VARCHAR(30) NOT NULL,
      entity_id BIGINT NULL,
      before_data LONGTEXT NULL,
      after_data LONGTEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
