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
      doc_type ENUM('SALE','CF_SALE','PURCHASE','EXPIRY') NOT NULL,
      source_kind ENUM('TEMPLATE_UPLOAD','HISTORY') NOT NULL,
      file_name VARCHAR(255) NOT NULL,
      rows_total INT NOT NULL DEFAULT 0,
      rows_created INT NOT NULL DEFAULT 0,
      rows_skipped INT NOT NULL DEFAULT 0,
      rows_failed INT NOT NULL DEFAULT 0,
      imported_by BIGINT NOT NULL,
      imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_batch_user FOREIGN KEY (imported_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS documents (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      doc_type ENUM('SALE','CF_SALE','PURCHASE','EXPIRY') NOT NULL,
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
      reference_no VARCHAR(50) NULL,
      receipt_date DATE NULL,
      received_by VARCHAR(120) NULL,
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
      kind ENUM('RECEIPT_COPY','INVOICE_PHOTO','CHALLAN_PHOTO','GOODS_PHOTO','EXPIRY_PHOTO') NOT NULL,
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
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS audit_changes (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      audit_id BIGINT NOT NULL,
      field_name VARCHAR(60) NOT NULL,
      old_value TEXT NULL,
      new_value TEXT NULL,
      KEY audit_changes_audit (audit_id),
      CONSTRAINT fk_change_audit FOREIGN KEY (audit_id) REFERENCES audit_log(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS import_errors (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      import_batch_id BIGINT NOT NULL,
      row_no INT NOT NULL,
      doc_no VARCHAR(100) NULL,
      outcome ENUM('FAILED','SKIPPED') NOT NULL,
      reason VARCHAR(255) NOT NULL,
      KEY import_errors_batch (import_batch_id),
      CONSTRAINT fk_error_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS app_settings (
      setting_key VARCHAR(60) PRIMARY KEY,
      setting_value VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('purchase_in_last_sl', '0')",

    "CREATE TABLE IF NOT EXISTS purchase_in (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      entry_date DATE NOT NULL,
      entry_time DATETIME NOT NULL,
      sl_no INT NOT NULL UNIQUE,
      party_id BIGINT NULL,
      party_name VARCHAR(200) NOT NULL,
      packets INT NOT NULL,
      bill_count SMALLINT NOT NULL,
      reference_no VARCHAR(100) NULL,
      bill_amount DECIMAL(14,2) NOT NULL,
      challan_time DATETIME NULL,
      challan_no VARCHAR(50) NULL,
      status ENUM('PENDING','DONE') NOT NULL DEFAULT 'PENDING',
      entry_by BIGINT NOT NULL,
      photo_1 VARCHAR(500) NULL,
      photo_2 VARCHAR(500) NULL,
      photo_3 VARCHAR(500) NULL,
      photo_4 VARCHAR(500) NULL,
      photo_5 VARCHAR(500) NULL,
      party_no VARCHAR(500) NOT NULL,
      KEY purchase_in_status (status, sl_no),
      CONSTRAINT fk_pin_party FOREIGN KEY (party_id) REFERENCES parties(id),
      CONSTRAINT fk_pin_user FOREIGN KEY (entry_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS challan_series (
      fy CHAR(5) PRIMARY KEY,
      prefix VARCHAR(20) NOT NULL,
      width TINYINT NOT NULL,
      next_seq INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS sales_challans (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      challan_no VARCHAR(30) NOT NULL,
      challan_date DATE NOT NULL,
      financial_year CHAR(7) NOT NULL,
      party_id BIGINT NULL,
      party_text VARCHAR(200) NULL,
      amount DECIMAL(14,2) NOT NULL,
      item_count INT NOT NULL,
      made_by BIGINT NOT NULL,
      pick_time DATETIME NULL,
      picked_by BIGINT NULL,
      match_time DATETIME NULL,
      matched_by BIGINT NULL,
      match_quantity INT NULL,
      photo_1 VARCHAR(500) NULL,
      photo_2 VARCHAR(500) NULL,
      photo_3 VARCHAR(500) NULL,
      photo_4 VARCHAR(500) NULL,
      photo_5 VARCHAR(500) NULL,
      status ENUM('PACKING','MATCHING','DONE') NOT NULL DEFAULT 'PACKING',
      created_by BIGINT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL,
      deleted_by BIGINT NULL,
      active_flag TINYINT GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED,
      UNIQUE KEY sales_challan_unique_active (challan_no, active_flag),
      KEY sales_challan_status (status, challan_date),
      CONSTRAINT fk_sc_party FOREIGN KEY (party_id) REFERENCES parties(id),
      CONSTRAINT fk_sc_made FOREIGN KEY (made_by) REFERENCES users(id),
      CONSTRAINT fk_sc_picked FOREIGN KEY (picked_by) REFERENCES users(id),
      CONSTRAINT fk_sc_matched FOREIGN KEY (matched_by) REFERENCES users(id),
      CONSTRAINT fk_sc_creator FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS challan_numbers (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      fy CHAR(5) NOT NULL,
      challan_no VARCHAR(30) NOT NULL UNIQUE,
      seq INT NOT NULL,
      used_by BIGINT NULL,
      KEY challan_numbers_pick (fy, used_by, seq),
      CONSTRAINT fk_cn_challan FOREIGN KEY (used_by) REFERENCES sales_challans(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS pt_invoices (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      party_name VARCHAR(200) NOT NULL,
      outstanding DECIMAL(16,2) NULL,
      balance DECIMAL(14,2) NOT NULL,
      invoice_no VARCHAR(40) NULL,
      invoice_amount DECIMAL(14,2) NULL,
      invoice_date DATE NULL,
      mr VARCHAR(120) NULL,
      area VARCHAR(120) NULL,
      phone VARCHAR(40) NULL,
      closing_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
      KEY pt_inv_party (party_name),
      KEY pt_inv_date (invoice_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS pt_uploads (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      uploaded_at DATETIME NOT NULL,
      uploaded_by BIGINT NOT NULL,
      file_name VARCHAR(255) NULL,
      row_count INT NOT NULL,
      party_count INT NOT NULL,
      total_balance DECIMAL(16,2) NOT NULL,
      CONSTRAINT fk_pt_upload_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS pt_followups (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      legacy_id VARCHAR(20) NULL UNIQUE,
      party_name VARCHAR(200) NOT NULL,
      amount_due DECIMAL(14,2) NULL,
      followup_at DATETIME NOT NULL,
      followup_date DATE NOT NULL,
      amount_promised DECIMAL(14,2) NULL,
      expected_date DATE NULL,
      followup_by BIGINT NULL,
      followup_by_name VARCHAR(120) NOT NULL,
      remarks VARCHAR(1000) NULL,
      time_slot VARCHAR(10) NULL,
      area VARCHAR(120) NULL,
      mr VARCHAR(120) NULL,
      phone VARCHAR(40) NULL,
      received_amount DECIMAL(14,2) NULL,
      received_date DATE NULL,
      received_by BIGINT NULL,
      KEY pt_fu_party (party_name, id),
      KEY pt_fu_expected (expected_date),
      KEY pt_fu_date (followup_date),
      CONSTRAINT fk_pt_fu_user FOREIGN KEY (followup_by) REFERENCES users(id),
      CONSTRAINT fk_pt_fu_received FOREIGN KEY (received_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS pt_mr_phones (
      mr VARCHAR(120) PRIMARY KEY,
      phone VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
