<?php
declare(strict_types=1);

/** Reports belong to one section; without a section they use the first one, like the left menu. */
function report_register(): array
{
    return register_by_slug((string) ($_GET['type'] ?? 'sale')) ?? not_found();
}

function pending_by_date(array $register): array
{
    $result = [];
    foreach (rows("SELECT doc_date, COUNT(*) AS total FROM documents WHERE doc_type = ? AND status = 'PENDING' AND deleted_at IS NULL GROUP BY doc_date ORDER BY doc_date DESC LIMIT 1000", [$register['type']]) as $row) {
        $result[] = ['date' => display_date($row['doc_date']), 'count' => (int) $row['total']];
    }
    return $result;
}

function uploaded_today(array $register): int
{
    return (int) value("SELECT COUNT(*) FROM documents WHERE doc_type = ? AND deleted_at IS NULL AND status = 'UPLOADED' AND DATE(uploaded_at) = CURDATE()", [$register['type']]);
}

function missing_numbers(array $register): array
{
    $result = [];
    $sql = "SELECT * FROM (
              SELECT doc_type, doc_prefix, doc_seq, financial_year, LENGTH(doc_no) - LENGTH(doc_prefix) AS width,
                     LAG(doc_seq) OVER (PARTITION BY doc_type, doc_prefix, financial_year ORDER BY doc_seq) AS previous_seq
              FROM documents WHERE doc_type = ? AND deleted_at IS NULL
            ) numbered
            WHERE previous_seq IS NOT NULL AND doc_seq - previous_seq > 1
            ORDER BY financial_year DESC, doc_prefix, doc_seq LIMIT 1000";
    foreach (rows($sql, [$register['type']]) as $row) {
        $width = (int) $row['width'];
        $result[] = [
            'label' => $row['financial_year'],
            'from' => $row['doc_prefix'] . str_pad((string) ($row['previous_seq'] + 1), $width, '0', STR_PAD_LEFT),
            'to' => $row['doc_prefix'] . str_pad((string) ($row['doc_seq'] - 1), $width, '0', STR_PAD_LEFT),
            'count' => (int) $row['doc_seq'] - (int) $row['previous_seq'] - 1,
        ];
    }
    return $result;
}
