<?php
declare(strict_types=1);

const MAX_BULK_ROWS = 10000;
const MAX_BULK_BYTES = 10485760;

function valid_date(int $year, int $month, int $day): ?string
{
    return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
}

/** Accepts Excel dates, DD/MM/YYYY and DD-Mon-YY. Blank and 01/01/1970 are invalid. */
function parse_date(mixed $value): ?string
{
    $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    $iso = null;
    if ($value instanceof DateTimeInterface) {
        $iso = $value->format('Y-m-d');
    } elseif (is_string($value)) {
        $text = trim($value);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text, $m)) {
            $iso = valid_date((int) $m[3], (int) $m[2], (int) $m[1]);
        } elseif (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{2}|\d{4})$/', $text, $m) && isset($months[strtolower($m[2])])) {
            $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            $iso = valid_date($year, $months[strtolower($m[2])], (int) $m[1]);
        }
    }
    return $iso === '1970-01-01' ? null : $iso;
}

function parse_amount(mixed $value): ?float
{
    if (!is_string($value)) {
        return null;
    }
    $text = str_replace(',', '', trim($value));
    if (!preg_match('/^\d+(\.\d+)?$/', $text) || (float) $text >= 1e12) {
        return null;
    }
    return round((float) $text, 2);
}

function parse_whole(mixed $value): ?int
{
    return is_string($value) && preg_match('/^\d{1,7}$/', trim($value)) ? (int) trim($value) : null;
}

/** Removes suffixes such as "18/09" and splits the series from the number. */
function parse_doc_number(mixed $value): ?array
{
    $parts = preg_split('/\s+/', trim((string) ($value instanceof DateTimeInterface ? '' : $value)));
    $no = $parts[0] ?? '';
    if (strlen($no) > 30 || !preg_match('/^(.*?)(\d{1,9})$/', $no, $m) || strlen($m[1]) > 10) {
        return null;
    }
    return ['no' => $no, 'prefix' => $m[1], 'seq' => (int) $m[2]];
}

/** Indian financial year, April to March, e.g. 2026-27. */
function financial_year(string $iso): string
{
    $year = (int) substr($iso, 0, 4);
    $start = (int) substr($iso, 5, 2) >= 4 ? $year : $year - 1;
    return sprintf('%d-%02d', $start, ($start + 1) % 100);
}

function cell_text(mixed $value, int $limit): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim($value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value);
    return $text === '' ? null : mb_substr($text, 0, $limit);
}

function check_headers(array $register, array $headers): ?string
{
    $expected = array_map(fn ($column) => $column[0], $register['template']);
    $actual = array_map(fn ($header) => trim((string) ($header instanceof DateTimeInterface ? '' : $header)), $headers);
    while (count($actual) > count($expected) && end($actual) === '') {
        array_pop($actual);
    }
    if (array_map('strtolower', $actual) === array_map('strtolower', $expected)) {
        return null;
    }
    return 'Columns must be, in this order: ' . implode(', ', $expected) . '.';
}

/** Validates one data row. Duplicate checks need other rows, so they run separately. */
function check_row(array $register, array $cells): array
{
    $value = [];
    foreach ($register['template'] as $index => $column) {
        $value[$column[1]] = $cells[$index] ?? null;
        if ($column[2] && cell_text($value[$column[1]], 500) === null) {
            return ['outcome' => 'failed', 'reason' => "{$column[0]} is blank"];
        }
    }
    $number = parse_doc_number($value['no']);
    if (!$number) {
        return ['outcome' => 'failed', 'reason' => 'Number format is not valid'];
    }
    $date = parse_date($value['date']);
    if (!$date) {
        return ['outcome' => 'failed', 'reason' => 'Date is blank or not valid'];
    }
    $amount = parse_amount($value['amount']);
    if ($amount === null) {
        return ['outcome' => 'failed', 'reason' => 'Amount is not a valid number'];
    }
    $items = null;
    if (cell_text($value['items'] ?? null, 20) !== null) {
        $items = parse_whole($value['items']);
        if ($items === null) {
            return ['outcome' => 'failed', 'reason' => 'No of Item is not a whole number'];
        }
    }
    return ['outcome' => 'ready', 'value' => $number + [
        'date' => $date,
        'year' => financial_year($date),
        'party' => cell_text($value['party'], 200),
        'amount' => $amount,
        'items' => $items,
        'receipt' => cell_text($value['receipt'] ?? null, 50),
        'mr' => cell_text($value['mr'] ?? null, 120),
        'area' => cell_text($value['area'] ?? null, 100),
        'address' => cell_text($value['address'] ?? null, 255),
    ]];
}

function read_csv(string $path): array
{
    $handle = fopen($path, 'r');
    $table = [];
    $number = 0;
    while (($record = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $number++;
        if ($number === 1 && isset($record[0])) {
            $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $record[0]);
        }
        $table[] = ['number' => $number, 'cells' => $record === [null] ? [] : $record];
    }
    fclose($handle);
    return $table;
}

function read_upload(array $file): array
{
    $name = strtolower((string) ($file['name'] ?? ''));
    try {
        $table = str_ends_with($name, '.csv') ? read_csv($file['tmp_name']) : read_xlsx($file['tmp_name']);
    } catch (UserError $error) {
        throw $error;
    } catch (Throwable) {
        throw new UserError('The file could not be read. Use the Excel template or a CSV file.');
    }
    if (!$table) {
        throw new UserError('The file is empty.');
    }
    $header = array_shift($table);
    $data = array_values(array_filter($table, function ($row) {
        foreach ($row['cells'] as $cell) {
            if ($cell instanceof DateTimeInterface || trim((string) $cell) !== '') {
                return true;
            }
        }
        return false;
    }));
    if (count($data) > MAX_BULK_ROWS) {
        throw new UserError('A file can have at most ' . number_format(MAX_BULK_ROWS) . ' rows.');
    }
    return ['headers' => $header['cells'], 'rows' => $data];
}

function display_cell(mixed $cell): string
{
    return $cell instanceof DateTimeInterface ? $cell->format('d/m/Y') : trim((string) $cell);
}

function check_sheet(array $register, array $sheet): array
{
    $problem = check_headers($register, $sheet['headers']);
    if ($problem) {
        throw new UserError($problem);
    }
    $positions = [];
    foreach ($register['template'] as $index => $column) {
        $positions[$column[1]] = $index;
    }
    $checks = [];
    $numbers = [];
    foreach ($sheet['rows'] as $row) {
        $check = check_row($register, $row['cells']);
        $checks[] = [$row, $check];
        if ($check['outcome'] === 'ready') {
            $numbers[$check['value']['no']] = true;
        }
    }
    $existing = [];
    foreach (array_chunk(array_map('strval', array_keys($numbers)), 500) as $chunk) {
        $marks = implode(',', array_fill(0, count($chunk), '?'));
        foreach (rows("SELECT doc_no, financial_year FROM documents WHERE doc_type = ? AND deleted_at IS NULL AND doc_no IN ($marks)", array_merge([$register['type']], $chunk)) as $found) {
            $existing[$found['doc_no'] . '|' . $found['financial_year']] = true;
        }
    }
    $seen = [];
    $result = [];
    foreach ($checks as [$row, $check]) {
        if ($check['outcome'] === 'ready') {
            $key = $check['value']['no'] . '|' . $check['value']['year'];
            if (isset($existing[$key])) {
                $check = ['outcome' => 'skipped', 'reason' => 'Already exists'];
            } elseif (isset($seen[$key])) {
                $check = ['outcome' => 'skipped', 'reason' => 'Repeated in this file'];
            }
            $seen[$key] = true;
        }
        $cells = [];
        foreach ($register['template'] as $index => $column) {
            $cell = $row['cells'][$index] ?? null;
            $iso = $column[1] === 'date' ? parse_date($cell) : null;
            $cells[] = $iso ? display_date($iso) : display_cell($cell);
        }
        $result[] = [
            'row' => $row['number'],
            'no' => $cells[$positions['no']],
            'date' => $cells[$positions['date']],
            'party' => $cells[$positions['party']],
            'amount' => $cells[$positions['amount']],
            'outcome' => $check['outcome'],
            'reason' => $check['reason'] ?? '',
            'cells' => $cells,
            'value' => $check['value'] ?? null,
        ];
    }
    return $result;
}

function public_rows(array $rows): array
{
    return array_map(function ($row) {
        unset($row['value']);
        return $row;
    }, $rows);
}

/** Validates the file again inside one transaction, then saves the valid rows. */
function commit_upload(array $register, array $file, int $userId): array
{
    $sheet = read_upload($file);
    return tx(function () use ($register, $file, $sheet, $userId) {
        $rows = check_sheet($register, $sheet);
        run("INSERT INTO import_batches (doc_type, source_kind, file_name, rows_total, imported_by) VALUES (?, 'TEMPLATE_UPLOAD', ?, ?, ?)", [$register['type'], mb_substr((string) $file['name'], 0, 255), count($rows), $userId]);
        $batch = (int) db()->lastInsertId();
        $created = $skipped = $failed = 0;
        foreach ($rows as $row) {
            if ($row['outcome'] === 'failed') {
                $failed++;
            } elseif ($row['outcome'] === 'skipped') {
                $skipped++;
            } else {
                $v = $row['value'];
                $area = $v['area'] === null ? null : upsert_area($v['area']);
                $party = upsert_party($v['party'], $register['type'], $area);
                $count = run(
                    'INSERT INTO documents (doc_type, doc_no, doc_prefix, doc_seq, doc_date, financial_year, party_id, party_name_raw, amount, item_count, receipt_ref, mr_name, area_id, address, import_batch_id, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE id = id',
                    [$register['type'], $v['no'], $v['prefix'], $v['seq'], $v['date'], $v['year'], $party, $v['party'], $v['amount'], $v['items'], $v['receipt'], $v['mr'], $area, $v['address'], $batch, $userId]
                )->rowCount();
                $count === 1 ? $created++ : $skipped++;
            }
            if ($row['outcome'] !== 'ready') {
                run('INSERT INTO import_errors (import_batch_id, row_no, doc_no, outcome, reason) VALUES (?, ?, ?, ?, ?)', [$batch, $row['row'], mb_substr($row['no'], 0, 100), strtoupper($row['outcome']), mb_substr($row['reason'], 0, 255)]);
            }
        }
        run('UPDATE import_batches SET rows_created = ?, rows_skipped = ?, rows_failed = ? WHERE id = ?', [$created, $skipped, $failed, $batch]);
        audit($userId, 'BULK_UPLOAD', 'import_batch', $batch, null, ['type' => $register['type'], 'created' => $created, 'skipped' => $skipped, 'failed' => $failed]);
        return ['created' => $created, 'skipped' => $skipped, 'failed' => $failed, 'rows' => public_rows($rows)];
    });
}
