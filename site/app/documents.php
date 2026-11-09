<?php
declare(strict_types=1);

const PAGE_SIZE = 50;
const MAX_IMAGE_BYTES = 10485760;
const IMAGE_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function document_select(): string
{
    return "SELECT d.*, COALESCE(p.name, d.party_name_raw) AS party, a.name AS area,
                   COALESCE(u.full_name, d.legacy_uploaded_by) AS uploader,
                   DATE(d.uploaded_at) AS upload_date
            FROM documents d
            LEFT JOIN parties p ON p.id = d.party_id
            LEFT JOIN areas a ON a.id = d.area_id
            LEFT JOIN users u ON u.id = d.uploaded_by";
}

function list_documents(array $filter): array
{
    $where = ['d.doc_type = ?'];
    $params = [$filter['type']];
    $where[] = $filter['status'] === 'DELETED' ? 'd.deleted_at IS NOT NULL' : 'd.deleted_at IS NULL';
    if (in_array($filter['status'], ['PENDING', 'UPLOADED'], true)) {
        $where[] = 'd.status = ?';
        $params[] = $filter['status'];
    }
    if ($filter['status'] === 'MINE') {
        $where[] = 'd.uploaded_by = ?';
        $params[] = $filter['user_id'];
    }
    if ($filter['q'] !== '') {
        $pattern = like_pattern($filter['q']);
        $where[] = '(d.doc_no LIKE ? OR d.party_name_raw LIKE ? OR p.name LIKE ?)';
        array_push($params, $pattern, $pattern, $pattern);
    }
    if ($filter['from'] !== '') {
        $where[] = 'd.doc_date >= ?';
        $params[] = $filter['from'];
    }
    if ($filter['to'] !== '') {
        $where[] = 'd.doc_date <= ?';
        $params[] = $filter['to'];
    }
    if ($filter['area']) {
        $where[] = 'd.area_id = ?';
        $params[] = $filter['area'];
    }
    $clause = implode(' AND ', $where);
    $total = (int) value("SELECT COUNT(*) FROM documents d LEFT JOIN parties p ON p.id = d.party_id WHERE $clause", $params);
    $offset = ($filter['page'] - 1) * PAGE_SIZE;
    $rows = rows(document_select() . " WHERE $clause ORDER BY d.doc_date DESC, d.doc_seq DESC, d.id DESC LIMIT " . PAGE_SIZE . " OFFSET $offset", $params);
    return [$rows, $total];
}

function register_counts(string $type): array
{
    $counts = ['PENDING' => 0, 'UPLOADED' => 0];
    foreach (rows('SELECT status, COUNT(*) AS total FROM documents WHERE doc_type = ? AND deleted_at IS NULL GROUP BY status', [$type]) as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }
    return $counts;
}

function attachments_for(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    return rows("SELECT id, document_id, kind, sort_order FROM attachments WHERE document_id IN ($marks) AND deleted_at IS NULL ORDER BY kind, sort_order", $ids);
}

function get_document(int $id): ?array
{
    return row(document_select() . ' WHERE d.id = ?', [$id]);
}

function lock_document(int $id): array
{
    $document = row('SELECT id, doc_type, deleted_at FROM documents WHERE id = ? FOR UPDATE', [$id]);
    if (!$document) {
        throw new UserError('Document not found.');
    }
    if ($document['deleted_at']) {
        throw new UserError('This document has been deleted.');
    }
    return $document;
}

function refresh_status(int $documentId, string $type, int $userId): void
{
    $counts = [];
    foreach (rows('SELECT kind, COUNT(*) AS total FROM attachments WHERE document_id = ? AND deleted_at IS NULL GROUP BY kind', [$documentId]) as $row) {
        $counts[$row['kind']] = (int) $row['total'];
    }
    if (is_complete(registers()[$type], $counts)) {
        run("UPDATE documents SET status = 'UPLOADED', uploaded_by = ?, legacy_uploaded_by = NULL, uploaded_at = NOW() WHERE id = ?", [$userId, $documentId]);
    } else {
        run("UPDATE documents SET status = 'PENDING', uploaded_by = NULL, legacy_uploaded_by = NULL, uploaded_at = NULL WHERE id = ?", [$documentId]);
    }
}

function detect_image(string $data): ?string
{
    if (str_starts_with($data, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }
    if (str_starts_with($data, "\x89PNG\r\n\x1A\n")) {
        return 'image/png';
    }
    if (substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    return null;
}

function save_upload(string $data, string $mime): string
{
    $folder = date('Y/m');
    $directory = storage_dir() . '/uploads/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Upload folder could not be created.');
    }
    $key = $folder . '/' . bin2hex(random_bytes(16)) . '.' . IMAGE_EXTENSIONS[$mime];
    if (file_put_contents(storage_dir() . '/uploads/' . $key, $data) === false) {
        throw new RuntimeException('Photo could not be stored.');
    }
    return $key;
}

/** Adds a photo in the next open slot. A slot opens only after the one before it is filled. */
function add_attachment(int $documentId, string $kind, string $key, string $name, string $mime, int $size, int $userId): array
{
    return tx(function () use ($documentId, $kind, $key, $name, $mime, $size, $userId) {
        $document = lock_document($documentId);
        $register = registers()[$document['doc_type']];
        $group = group_for($register, $kind);
        if (!$group) {
            throw new UserError('This photo type is not used for this register.');
        }
        $last = (int) value('SELECT COALESCE(MAX(sort_order), 0) FROM attachments WHERE document_id = ? AND kind = ? AND deleted_at IS NULL', [$documentId, $kind]);
        if ($last >= $group['max']) {
            throw new UserError("Only {$group['max']} {$group['label']} allowed.");
        }
        run(
            'INSERT INTO attachments (document_id, kind, file_key, original_name, mime_type, size_bytes, sort_order, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$documentId, $kind, $key, mb_substr($name, 0, 255), $mime, $size, $last + 1, $userId]
        );
        $id = (int) db()->lastInsertId();
        refresh_status($documentId, $document['doc_type'], $userId);
        audit($userId, 'ATTACHMENT_ADD', 'document', $documentId, null, ['attachmentId' => $id, 'kind' => $kind, 'position' => $last + 1]);
        return ['id' => $id, 'position' => $last + 1];
    });
}

/** Removes the last photo in its group, so a sequence never has a gap. */
function remove_last_attachment(int $documentId, int $attachmentId, int $userId): void
{
    tx(function () use ($documentId, $attachmentId, $userId) {
        $document = lock_document($documentId);
        $attachment = row('SELECT id, kind, sort_order FROM attachments WHERE id = ? AND document_id = ? AND deleted_at IS NULL', [$attachmentId, $documentId]);
        if (!$attachment) {
            throw new UserError('Photo not found.');
        }
        $last = (int) value('SELECT MAX(sort_order) FROM attachments WHERE document_id = ? AND kind = ? AND deleted_at IS NULL', [$documentId, $attachment['kind']]);
        if ((int) $attachment['sort_order'] !== $last) {
            throw new UserError('Only the last photo in a group can be removed.');
        }
        run('UPDATE attachments SET deleted_at = NOW() WHERE id = ?', [$attachmentId]);
        refresh_status($documentId, $document['doc_type'], $userId);
        audit($userId, 'ATTACHMENT_REMOVE', 'document', $documentId, ['attachmentId' => $attachmentId, 'kind' => $attachment['kind']]);
    });
}

function save_remarks(int $documentId, string $remarks, int $userId): void
{
    tx(function () use ($documentId, $remarks, $userId) {
        $document = lock_document($documentId);
        if ($document['doc_type'] !== 'PURCHASE') {
            throw new UserError('Remarks are only used for Purchase.');
        }
        $before = value('SELECT remarks FROM documents WHERE id = ?', [$documentId]);
        $clean = trim(mb_substr($remarks, 0, 2000));
        run('UPDATE documents SET remarks = ? WHERE id = ?', [$clean === '' ? null : $clean, $documentId]);
        audit($userId, 'REMARKS_UPDATE', 'document', $documentId, ['remarks' => $before], ['remarks' => $clean]);
    });
}

function upsert_party(string $rawName, string $type): int
{
    $supplier = $type === 'PURCHASE' ? 1 : 0;
    run(
        'INSERT INTO parties (name, source_name, is_customer, is_supplier) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE is_customer = GREATEST(is_customer, VALUES(is_customer)), is_supplier = GREATEST(is_supplier, VALUES(is_supplier)), id = LAST_INSERT_ID(id)',
        [mb_substr((string) preg_replace('/\s+/', ' ', $rawName), 0, 120), mb_substr($rawName, 0, 200), 1 - $supplier, $supplier]
    );
    return (int) db()->lastInsertId();
}

function correct_document(int $documentId, array $change, int $userId): void
{
    tx(function () use ($documentId, $change, $userId) {
        $document = lock_document($documentId);
        $before = row('SELECT doc_no, doc_date, party_name_raw, amount, item_count, receipt_ref, mr_name, area_id, address, financial_year FROM documents WHERE id = ?', [$documentId]);
        $year = financial_year($change['date']);
        if (value('SELECT id FROM documents WHERE doc_type = ? AND doc_no = ? AND financial_year = ? AND id <> ? AND deleted_at IS NULL', [$document['doc_type'], $before['doc_no'], $year, $documentId])) {
            throw new UserError('Another document already has this number in that financial year.');
        }
        $party = upsert_party($change['party'], $document['doc_type']);
        run(
            'UPDATE documents SET doc_date = ?, financial_year = ?, party_id = ?, party_name_raw = ?, amount = ?, item_count = ?, receipt_ref = ?, mr_name = ?, area_id = ?, address = ? WHERE id = ?',
            [$change['date'], $year, $party, $change['party'], $change['amount'], $change['items'], $change['receipt'], $change['mr'], $change['area'], $change['address'], $documentId]
        );
        audit($userId, 'DOCUMENT_CORRECT', 'document', $documentId, $before, $change);
    });
}

function delete_document(int $documentId, int $userId): void
{
    tx(function () use ($documentId, $userId) {
        lock_document($documentId);
        run('UPDATE documents SET deleted_at = NOW() WHERE id = ?', [$documentId]);
        audit($userId, 'DOCUMENT_DELETE', 'document', $documentId);
    });
}

function restore_document(int $documentId, int $userId): void
{
    tx(function () use ($documentId, $userId) {
        $document = row('SELECT doc_type, doc_no, financial_year FROM documents WHERE id = ? AND deleted_at IS NOT NULL FOR UPDATE', [$documentId]);
        if (!$document) {
            throw new UserError('Document not found.');
        }
        if (value('SELECT id FROM documents WHERE doc_type = ? AND doc_no = ? AND financial_year = ? AND deleted_at IS NULL', array_values($document))) {
            throw new UserError('An active document already uses this number.');
        }
        run('UPDATE documents SET deleted_at = NULL WHERE id = ?', [$documentId]);
        audit($userId, 'DOCUMENT_RESTORE', 'document', $documentId);
    });
}
