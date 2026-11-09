<?php
declare(strict_types=1);

function pending_by_date(): array
{
    $result = [];
    foreach (rows("SELECT doc_date, doc_type, COUNT(*) AS total FROM documents WHERE status = 'PENDING' AND deleted_at IS NULL GROUP BY doc_date, doc_type ORDER BY doc_date DESC, doc_type LIMIT 1000") as $row) {
        $result[] = ['date' => display_date($row['doc_date']), 'label' => registers()[$row['doc_type']]['label'], 'count' => (int) $row['total']];
    }
    return $result;
}

function uploaded_today(): array
{
    $counts = [];
    foreach (rows("SELECT doc_type, COUNT(*) AS total FROM documents WHERE deleted_at IS NULL AND status = 'UPLOADED' AND DATE(uploaded_at) = CURDATE() GROUP BY doc_type") as $row) {
        $counts[$row['doc_type']] = (int) $row['total'];
    }
    return array_values(array_map(fn ($register) => ['label' => $register['label'], 'count' => $counts[$register['type']] ?? 0], registers()));
}

function missing_numbers(): array
{
    $result = [];
    $sql = "SELECT * FROM (
              SELECT doc_type, doc_prefix, doc_seq, financial_year, LENGTH(doc_no) - LENGTH(doc_prefix) AS width,
                     LAG(doc_seq) OVER (PARTITION BY doc_type, doc_prefix, financial_year ORDER BY doc_seq) AS previous_seq
              FROM documents WHERE deleted_at IS NULL
            ) numbered
            WHERE previous_seq IS NOT NULL AND doc_seq - previous_seq > 1
            ORDER BY financial_year DESC, doc_type, doc_prefix, doc_seq LIMIT 1000";
    foreach (rows($sql) as $row) {
        $width = (int) $row['width'];
        $result[] = [
            'label' => registers()[$row['doc_type']]['label'] . ' ' . $row['financial_year'],
            'from' => $row['doc_prefix'] . str_pad((string) ($row['previous_seq'] + 1), $width, '0', STR_PAD_LEFT),
            'to' => $row['doc_prefix'] . str_pad((string) ($row['doc_seq'] - 1), $width, '0', STR_PAD_LEFT),
            'count' => (int) $row['doc_seq'] - (int) $row['previous_seq'] - 1,
        ];
    }
    return $result;
}
