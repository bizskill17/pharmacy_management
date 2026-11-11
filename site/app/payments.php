<?php
declare(strict_types=1);

/* Payment Tracker: outstanding uploaded from the billing software, and the calls made to collect it. */

const PT_MAX_ROWS = 100000;
const PT_HOURS = ['10-11', '11-12', '12-13', '13-14', '14-15', '15-16', '16-17', '17-18', '18-19', '19-20', '20-21', '21-22'];
const PT_BANDS = [
    'over5' => ['More than 5 Lakh', 500000, null],
    '3to5' => ['3 to 5 Lakh', 300000, 500000],
    '2to3' => ['2 to 3 Lakh', 200000, 300000],
    '1to2' => ['1 to 2 Lakh', 100000, 200000],
    'under1' => ['Less than 1 Lakh', null, 100000],
];
const PT_UPLOAD_HEADERS = ['party name' => true, 'outstanding' => false, 'balance' => true, 'invoice no' => true, 'invoice amount' => true, 'invoice date' => true, 'mr' => true, 'area' => true, 'phone' => true, 'closing balance' => true];

/** Amounts that may be negative or have commas, such as "-4,082.00". */
function pt_amount(mixed $value): ?float
{
    if ($value === null || $value instanceof DateTimeInterface) {
        return null;
    }
    $text = str_replace([',', "\u{20B9}", ' '], '', trim((string) $value));
    if ($text === '' || !preg_match('/^-?\d+(\.\d+)?$/', $text) || abs((float) $text) >= 1e13) {
        return null;
    }
    return round((float) $text, 2);
}

/** Dates as Excel dates, DD/MM/YYYY or YYYY-MM-DD. */
function pt_date(mixed $value): ?string
{
    if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($value), $m)) {
        return valid_date((int) $m[1], (int) $m[2], (int) $m[3]);
    }
    return parse_date($value);
}

/** Money for a table cell; negative amounts are red with a minus sign. */
function signed_money(mixed $amount): string
{
    $number = (float) $amount;
    return $number < 0 ? '<span class="neg">-' . e(money(abs($number))) . '</span>' : e(money($number));
}

function pt_text(mixed $value, int $limit): string
{
    return (string) (cell_text($value, $limit) ?? '');
}

/** A phone number ready for WhatsApp, or '' when it is not a phone number. */
function wa_number(?string $phone): string
{
    $digits = preg_replace('/\D/', '', (string) $phone);
    if (strlen($digits) === 10) {
        return '91' . $digits;
    }
    return strlen($digits) === 12 && str_starts_with($digits, '91') ? $digits : '';
}

function wa_link(?string $phone, string $message): string
{
    $number = wa_number($phone);
    return $number === '' ? '' : 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}

function pt_party_message(string $party, float $amount): string
{
    return "Dear $party,\nYour payment of " . money($amount) . " is due. Kindly arrange the payment at the earliest.\nThank you.";
}

function pt_icon(string $name): string
{
    $icons = [
        'wa' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.2.1.6-.1 1.2z"/></svg>',
        'call' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
        'ok' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h18v10H3z"/><path d="M8 12l2.5 2.5L16 9"/></svg>',
    ];
    return $icons[$name];
}

/** Call and WhatsApp buttons for a phone number; nothing when the number is not usable. */
function pt_contact(?string $phone, string $who, string $message): string
{
    if (wa_number($phone) === '') {
        return '';
    }
    return '<a class="pt-icon call" href="tel:' . e(preg_replace('/[^\d+]/', '', (string) $phone)) . '" aria-label="Call ' . e($who) . '" title="Call ' . e($who) . '">' . pt_icon('call') . '</a>'
        . '<a class="pt-icon wa" href="' . e(wa_link($phone, $message)) . '" target="_blank" rel="noopener" aria-label="WhatsApp ' . e($who) . '" title="WhatsApp ' . e($who) . '">' . pt_icon('wa') . '</a>';
}

function pt_slot(string $time): string
{
    $hour = (int) date('G', strtotime($time));
    return $hour . '-' . ($hour + 1);
}

function pt_last_upload(): ?array
{
    return row('SELECT u.*, s.full_name FROM pt_uploads u JOIN users s ON s.id = u.uploaded_by ORDER BY u.id DESC LIMIT 1');
}

function pt_mr_phone_map(): array
{
    $map = [];
    foreach (rows('SELECT mr, phone FROM pt_mr_phones') as $row) {
        $map[mb_strtolower($row['mr'])] = $row['phone'];
    }
    return $map;
}

function pt_back(string $fallback): string
{
    $back = (string) ($_POST['back'] ?? $_GET['back'] ?? '');
    return str_starts_with($back, '/payment-tracker/') && !preg_match('#//|\\\\|[\x00-\x1F]#', $back) ? $back : $fallback;
}

/* ---------- Payment Due ---------- */

function pt_due_filter(): array
{
    $date = function (string $name) {
        $text = trim((string) ($_GET[$name] ?? ''));
        return $text === '' ? '' : (parse_date($text) ?? '');
    };
    $group = (string) ($_GET['group'] ?? '');
    $band = (string) ($_GET['band'] ?? '');
    return [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'from' => $date('from'),
        'to' => $date('to'),
        'area' => trim((string) ($_GET['area'] ?? '')),
        'mr' => trim((string) ($_GET['mr'] ?? '')),
        'band' => isset(PT_BANDS[$band]) ? $band : '',
        'group' => in_array($group, ['mr', 'area'], true) ? $group : 'none',
    ];
}

/** One row per party: balance up to the To date, minus money received after it. Only parties still owing are kept. */
function pt_due_sql(array $filter): array
{
    $params = [];
    $inRange = [];
    if ($filter['from'] !== '') {
        $inRange[] = 'invoice_date >= ?';
        $params[] = $filter['from'];
    }
    if ($filter['to'] !== '') {
        $inRange[] = 'invoice_date <= ?';
        $params[] = $filter['to'];
    }
    $inside = $inRange ? implode(' AND ', $inRange) : '1';
    $after = '0';
    if ($filter['to'] !== '') {
        $after = 'invoice_date > ? AND balance < 0';
        $params[] = $filter['to'];
    }
    $where = ['closing_balance > 0'];
    if ($filter['area'] !== '') {
        $where[] = 'area = ?';
        $params[] = $filter['area'];
    }
    if ($filter['mr'] !== '') {
        $where[] = 'mr = ?';
        $params[] = $filter['mr'];
    }
    $inner = "SELECT party_name, MAX(area) AS area, MAX(mr) AS mr, MAX(phone) AS phone,
                     SUM(CASE WHEN $inside THEN balance ELSE 0 END) AS total,
                     SUM(CASE WHEN $after THEN balance ELSE 0 END) AS negative
              FROM pt_invoices WHERE " . implode(' AND ', $where) . ' GROUP BY party_name';
    $outer = ['d.total + d.negative > 0'];
    if ($filter['q'] !== '') {
        $outer[] = '(d.party_name LIKE ? OR d.phone LIKE ?)';
        $pattern = like_pattern($filter['q']);
        array_push($params, $pattern, $pattern);
    }
    if ($filter['band'] !== '') {
        [, $low, $high] = PT_BANDS[$filter['band']];
        if ($low !== null) {
            $outer[] = 'd.total + d.negative > ' . (int) $low;
        }
        if ($high !== null) {
            $outer[] = 'd.total + d.negative <= ' . (int) $high;
        }
    }
    $sql = "SELECT d.*, d.total + d.negative AS net FROM ($inner) d WHERE " . implode(' AND ', $outer);
    return [$sql, $params];
}

function pt_group_column(string $group): ?string
{
    return ['mr' => 'mr', 'area' => 'area'][$group] ?? null;
}

function pt_due_list(array $filter, bool $all = false): array
{
    [$sql, $params] = pt_due_sql($filter);
    $summary = row("SELECT COUNT(*) AS parties, COALESCE(SUM(net), 0) AS net FROM ($sql) x", $params);
    $total = (int) $summary['parties'];
    $page = clamp_page(requested_page(), $total);
    $column = pt_group_column($filter['group']);
    $order = ($column ? "COALESCE(x.$column, ''), " : '') . 'x.net DESC, x.party_name';
    $limit = $all ? '' : ' LIMIT ' . PAGE_SIZE . ' OFFSET ' . page_offset($page);
    $list = rows(
        "SELECT x.*, f.followup_date AS last_date, f.amount_promised AS last_promised, f.expected_date AS last_expected, f.followup_by_name AS last_by, f.remarks AS last_remarks
         FROM ($sql) x
         LEFT JOIN (SELECT party_name, MAX(id) AS id FROM pt_followups GROUP BY party_name) l ON l.party_name = x.party_name
         LEFT JOIN pt_followups f ON f.id = l.id
         ORDER BY $order$limit",
        $params
    );
    $groups = [];
    if ($column) {
        foreach (rows("SELECT COALESCE($column, '') AS group_key, COUNT(*) AS parties, SUM(net) AS net FROM ($sql) x GROUP BY group_key ORDER BY group_key", $params) as $group) {
            $groups[(string) $group['group_key']] = ['parties' => (int) $group['parties'], 'net' => (float) $group['net']];
        }
    }
    return [$list, $total, $page, $groups, (float) $summary['net']];
}

function pt_choices(string $column): array
{
    return array_column(rows("SELECT DISTINCT $column AS v FROM pt_invoices WHERE $column IS NOT NULL AND $column <> '' ORDER BY v"), 'v');
}

function export_due(array $filter): never
{
    [$list] = pt_due_list($filter, true);
    $sheet = [['Party Name', 'Total Balance', 'Negative After Due Date', 'Net Balance', 'Area', 'MR', 'Phone', 'Last Follow Up', 'Amount Promised', 'Expected Payment Date', 'Remarks']];
    foreach ($list as $party) {
        $sheet[] = [$party['party_name'], (float) $party['total'], (float) $party['negative'], (float) $party['net'], $party['area'], $party['mr'], $party['phone'], display_date($party['last_date']), $party['last_promised'] === null ? '' : (float) $party['last_promised'], display_date($party['last_expected']), (string) $party['last_remarks']];
    }
    send_xlsx('payment-due-' . date('d-m-Y') . '.xlsx', ['Payment Due' => $sheet]);
}

/* ---------- One party ---------- */

function pt_party(string $name): array
{
    $invoices = rows('SELECT * FROM pt_invoices WHERE party_name = ? ORDER BY invoice_date IS NULL, invoice_date, id', [$name]);
    $history = rows('SELECT * FROM pt_followups WHERE party_name = ? ORDER BY id DESC LIMIT 200', [$name]);
    $first = $invoices[0] ?? $history[0] ?? null;
    $balance = array_sum(array_map(fn ($invoice) => (float) $invoice['balance'], $invoices));
    return [
        'name' => $name,
        'invoices' => $invoices,
        'history' => $history,
        'balance' => round($balance, 2),
        'area' => (string) ($first['area'] ?? ''),
        'mr' => (string) ($first['mr'] ?? ''),
        'phone' => (string) ($first['phone'] ?? ''),
    ];
}

function save_followup(array $party, array $user): void
{
    $promisedText = trim((string) ($_POST['amount_promised'] ?? ''));
    $promised = $promisedText === '' ? null : pt_amount($promisedText);
    $expectedText = trim((string) ($_POST['expected_date'] ?? ''));
    $expected = $expectedText === '' ? null : parse_date($expectedText);
    $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if ($promisedText !== '' && ($promised === null || $promised <= 0)) {
        throw new UserError('Amount Promised must be a number above 0.');
    }
    if ($expectedText !== '' && $expected === null) {
        throw new UserError('Expected Payment Date must be like 25/11/2026.');
    }
    if ($expected !== null && $expected < date('Y-m-d')) {
        throw new UserError('Expected Payment Date cannot be before today.');
    }
    if ($promised !== null && $expected === null) {
        throw new UserError('Enter the Expected Payment Date for the promised amount.');
    }
    if ($expected === null && $remarks === '') {
        throw new UserError('Enter the Expected Payment Date or Remarks.');
    }
    $now = date('Y-m-d H:i:s');
    run(
        'INSERT INTO pt_followups (party_name, amount_due, followup_at, followup_date, amount_promised, expected_date, followup_by, followup_by_name, remarks, time_slot, area, mr, phone)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$party['name'], $party['balance'], $now, substr($now, 0, 10), $promised, $expected, $user['id'], $user['full_name'], $remarks === '' ? null : $remarks, pt_slot($now), $party['area'] ?: null, $party['mr'] ?: null, $party['phone'] ?: null]
    );
    audit($user['id'], 'PT_FOLLOW_UP', 'pt_followup', (int) db()->lastInsertId(), null, ['party' => $party['name'], 'promised' => $promised, 'expected' => $expected]);
}

/* ---------- Follow up lists ---------- */

function pt_followup_filter(string $mode): array
{
    $date = function (string $name) {
        $text = trim((string) ($_GET[$name] ?? ''));
        return $text === '' ? '' : (parse_date($text) ?? '');
    };
    $group = (string) ($_GET['group'] ?? '');
    $groups = $mode === 'all' ? ['date', 'caller', 'mr', 'none'] : ['mr', 'caller', 'none'];
    return [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'from' => $date('from'),
        'to' => $date('to'),
        'group' => in_array($group, $groups, true) ? $group : $groups[0],
    ];
}

function pt_followup_where(string $mode, array $filter): array
{
    $where = [];
    $params = [];
    $today = date('Y-m-d');
    if ($mode === 'today' || $mode === 'tomorrow') {
        $where[] = 'f.expected_date = ?';
        $params[] = $mode === 'today' ? $today : date('Y-m-d', strtotime('+1 day'));
    } elseif ($mode === 'broken') {
        $where[] = 'f.expected_date < ? AND f.received_amount IS NULL AND NOT EXISTS (SELECT 1 FROM pt_followups n WHERE n.party_name = f.party_name AND n.id > f.id)';
        $params[] = $today;
    }
    if ($filter['q'] !== '') {
        $where[] = '(f.party_name LIKE ? OR f.followup_by_name LIKE ? OR f.mr LIKE ? OR f.remarks LIKE ? OR f.phone LIKE ?)';
        $pattern = like_pattern($filter['q']);
        array_push($params, $pattern, $pattern, $pattern, $pattern, $pattern);
    }
    if ($filter['from'] !== '') {
        $where[] = 'f.followup_date >= ?';
        $params[] = $filter['from'];
    }
    if ($filter['to'] !== '') {
        $where[] = 'f.followup_date <= ?';
        $params[] = $filter['to'];
    }
    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function pt_followup_key(string $group): ?string
{
    return ['date' => 'f.followup_date', 'caller' => 'f.followup_by_name', 'mr' => "COALESCE(f.mr, '')"][$group] ?? null;
}

function pt_followup_list(string $mode, array $filter): array
{
    [$clause, $params] = pt_followup_where($mode, $filter);
    $summary = row("SELECT COUNT(*) AS entries, COALESCE(SUM(f.amount_promised), 0) AS promised, COALESCE(SUM(f.received_amount), 0) AS received FROM pt_followups f $clause", $params);
    $total = (int) $summary['entries'];
    $page = clamp_page(requested_page(), $total);
    $key = pt_followup_key($filter['group']);
    $descending = $filter['group'] === 'date' ? ' DESC' : '';
    $order = ($key ? "$key$descending, " : '') . ($mode === 'all' ? 'f.id DESC' : 'f.expected_date, f.party_name');
    $list = rows("SELECT f.* FROM pt_followups f $clause ORDER BY $order LIMIT " . PAGE_SIZE . ' OFFSET ' . page_offset($page), $params);
    $groups = [];
    if ($key) {
        foreach (rows("SELECT $key AS group_key, COUNT(*) AS entries, COALESCE(SUM(f.amount_promised), 0) AS promised FROM pt_followups f $clause GROUP BY group_key ORDER BY group_key$descending", $params) as $group) {
            $groups[(string) $group['group_key']] = ['entries' => (int) $group['entries'], 'promised' => (float) $group['promised']];
        }
    }
    return [$list, $total, $page, $groups, $summary];
}

/** WhatsApp text for an MR with every party to collect from in this list. */
function pt_mr_messages(string $mode, array $filter): array
{
    [$clause, $params] = pt_followup_where($mode, $filter);
    $byMr = [];
    foreach (rows("SELECT f.party_name, f.amount_promised, f.mr FROM pt_followups f $clause " . ($clause ? 'AND' : 'WHERE') . ' f.received_amount IS NULL ORDER BY f.party_name LIMIT 2000', $params) as $entry) {
        $byMr[(string) $entry['mr']][] = $entry;
    }
    $title = ['today' => "Today's collection", 'tomorrow' => "Tomorrow's collection", 'broken' => 'Pending promised payments'][$mode] ?? 'Collection';
    $messages = [];
    foreach ($byMr as $mr => $entries) {
        $lines = ["$title - $mr"];
        $sum = 0.0;
        foreach ($entries as $number => $entry) {
            $lines[] = ($number + 1) . '. ' . preg_replace('/\s+/', ' ', $entry['party_name']) . ' - ' . money((float) $entry['amount_promised']);
            $sum += (float) $entry['amount_promised'];
        }
        $lines[] = 'Total: ' . money($sum);
        $messages[$mr] = implode("\n", $lines);
    }
    return $messages;
}

function save_received(int $id, array $user): void
{
    $amount = pt_amount((string) ($_POST['received_amount'] ?? ''));
    $date = parse_date(trim((string) ($_POST['received_date'] ?? '')));
    if ($amount === null || $amount <= 0) {
        throw new UserError('Received Amount must be a number above 0.');
    }
    if ($date === null) {
        throw new UserError('Amount Received Date must be like 25/11/2026.');
    }
    if ($date > date('Y-m-d')) {
        throw new UserError('Amount Received Date cannot be in the future.');
    }
    tx(function () use ($id, $amount, $date, $user) {
        $before = row('SELECT received_amount, received_date FROM pt_followups WHERE id = ? FOR UPDATE', [$id]) ?? not_found();
        run('UPDATE pt_followups SET received_amount = ?, received_date = ?, received_by = ? WHERE id = ?', [$amount, $date, $user['id'], $id]);
        audit($user['id'], 'PT_RECEIVED', 'pt_followup', $id, $before, ['received_amount' => $amount, 'received_date' => $date]);
    });
}

/* ---------- Reports ---------- */

function pt_reports(string $day): array
{
    $calls = [];
    foreach (rows('SELECT followup_by_name AS caller, time_slot, COUNT(*) AS total FROM pt_followups WHERE followup_date = ? GROUP BY caller, time_slot ORDER BY caller', [$day]) as $row) {
        $slot = in_array($row['time_slot'], PT_HOURS, true) ? $row['time_slot'] : 'Other';
        $calls[$row['caller']][$slot] = ($calls[$row['caller']][$slot] ?? 0) + (int) $row['total'];
    }
    [$sql, $params] = pt_due_sql(pt_due_filter_blank());
    $byMr = rows("SELECT COALESCE(mr, '') AS name, COUNT(*) AS parties, SUM(net) AS net FROM ($sql) x GROUP BY name ORDER BY net DESC", $params);
    $byArea = rows("SELECT COALESCE(area, '') AS name, COUNT(*) AS parties, SUM(net) AS net FROM ($sql) x GROUP BY name ORDER BY net DESC", $params);
    $bands = [];
    foreach (PT_BANDS as $key => [$label, $low, $high]) {
        $where = [];
        if ($low !== null) {
            $where[] = 'net > ' . (int) $low;
        }
        if ($high !== null) {
            $where[] = 'net <= ' . (int) $high;
        }
        $band = row("SELECT COUNT(*) AS parties, COALESCE(SUM(net), 0) AS net FROM ($sql) x WHERE " . implode(' AND ', $where), $params);
        $bands[$key] = ['label' => $label, 'parties' => (int) $band['parties'], 'net' => (float) $band['net']];
    }
    $promises = row('SELECT COUNT(*) AS entries, COALESCE(SUM(amount_promised), 0) AS promised, COALESCE(SUM(received_amount), 0) AS received FROM pt_followups WHERE followup_date = ?', [$day]);
    return compact('calls', 'byMr', 'byArea', 'bands', 'promises');
}

function pt_due_filter_blank(): array
{
    return ['q' => '', 'from' => '', 'to' => '', 'area' => '', 'mr' => '', 'band' => '', 'group' => 'none'];
}

/* ---------- Upload Outstanding ---------- */

function pt_read_table(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new UserError('Choose a file to upload.');
    }
    if (($file['size'] ?? 0) > 31457280) {
        throw new UserError('The file must be under 30 MB.');
    }
    $name = strtolower((string) ($file['name'] ?? ''));
    try {
        $table = str_ends_with($name, '.csv') ? read_csv($file['tmp_name']) : read_xlsx($file['tmp_name']);
    } catch (UserError $error) {
        throw $error;
    } catch (Throwable) {
        throw new UserError('The file could not be read. Use an Excel (.xlsx) or CSV file.');
    }
    if (count($table) < 2) {
        throw new UserError('The file has no rows.');
    }
    if (count($table) > PT_MAX_ROWS) {
        throw new UserError('A file can have at most ' . number_format(PT_MAX_ROWS) . ' rows.');
    }
    $header = array_shift($table);
    $columns = [];
    foreach ($header['cells'] as $index => $cell) {
        $label = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($cell instanceof DateTimeInterface ? '' : $cell))));
        if ($label !== '' && !isset($columns[$label])) {
            $columns[$label] = $index;
        }
    }
    return [$columns, $table];
}

function pt_tmp_path(string $token): string
{
    $dir = storage_dir() . '/tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . '/pt-' . $token . '.json';
}

/** Reads the outstanding file and keeps the checked rows in a private file until Save. */
function check_outstanding(array $file): array
{
    [$columns, $table] = pt_read_table($file);
    $missing = [];
    foreach (PT_UPLOAD_HEADERS as $label => $required) {
        if ($required && !isset($columns[$label])) {
            $missing[] = ucwords($label);
        }
    }
    if ($missing) {
        throw new UserError('These columns are missing: ' . implode(', ', $missing) . '. Use the Invoice Receipts file from the billing software.');
    }
    $cell = fn (array $row, string $label) => isset($columns[$label]) ? ($row['cells'][$columns[$label]] ?? null) : null;
    $ready = [];
    $failed = [];
    $parties = [];
    $total = 0.0;
    $noDate = 0;
    foreach ($table as $row) {
        $party = pt_text($cell($row, 'party name'), 200);
        if ($party === '') {
            continue;
        }
        $balance = pt_amount($cell($row, 'balance'));
        if ($balance === null) {
            $failed[] = ['row' => $row['number'], 'party' => $party, 'reason' => 'Balance is not a number'];
            continue;
        }
        $date = pt_date($cell($row, 'invoice date'));
        if ($date === null) {
            $noDate++;
        }
        $closing = pt_amount($cell($row, 'closing balance')) ?? 0.0;
        $ready[] = [
            $party, pt_amount($cell($row, 'outstanding')), $balance, pt_text($cell($row, 'invoice no'), 40) ?: null,
            pt_amount($cell($row, 'invoice amount')), $date, pt_text($cell($row, 'mr'), 120) ?: null,
            pt_text($cell($row, 'area'), 120) ?: null, pt_text($cell($row, 'phone'), 40) ?: null, $closing,
        ];
        $parties[$party] = true;
        $total += $balance;
    }
    if (!$ready) {
        throw new UserError('No rows with a Party Name and Balance were found.');
    }
    $token = bin2hex(random_bytes(12));
    if (file_put_contents(pt_tmp_path($token), json_encode($ready)) === false) {
        throw new UserError('The file could not be kept for saving. Please try again.');
    }
    $summary = ['token' => $token, 'file' => mb_substr((string) $file['name'], 0, 255), 'rows' => count($ready), 'parties' => count($parties), 'total' => round($total, 2), 'no_date' => $noDate, 'failed' => array_slice($failed, 0, 100), 'failed_count' => count($failed), 'preview' => array_slice($ready, 0, 10)];
    $_SESSION['pt_upload'] = $summary;
    return $summary;
}

/** Replaces all outstanding with the checked file. Follow ups are kept. */
function save_outstanding(array $user): int
{
    $summary = $_SESSION['pt_upload'] ?? null;
    unset($_SESSION['pt_upload']);
    $path = $summary ? pt_tmp_path($summary['token']) : '';
    if (!$summary || !is_file($path)) {
        throw new UserError('Check the file again before saving.');
    }
    $ready = json_decode((string) file_get_contents($path), true);
    @unlink($path);
    tx(function () use ($ready, $summary, $user) {
        run('DELETE FROM pt_invoices');
        foreach (array_chunk($ready, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
            run("INSERT INTO pt_invoices (party_name, outstanding, balance, invoice_no, invoice_amount, invoice_date, mr, area, phone, closing_balance) VALUES $marks", array_merge(...$chunk));
        }
        run('INSERT INTO pt_uploads (uploaded_at, uploaded_by, file_name, row_count, party_count, total_balance) VALUES (?, ?, ?, ?, ?, ?)', [date('Y-m-d H:i:s'), $user['id'], $summary['file'], $summary['rows'], $summary['parties'], $summary['total']]);
        audit($user['id'], 'PT_UPLOAD', 'pt_upload', null, null, ['rows' => $summary['rows'], 'parties' => $summary['parties']]);
    });
    return (int) $summary['rows'];
}

/* ---------- MR phone numbers ---------- */

function pt_mr_list(): array
{
    $names = [];
    foreach (rows("SELECT DISTINCT mr FROM pt_invoices WHERE mr IS NOT NULL AND mr <> '' UNION SELECT DISTINCT mr FROM pt_followups WHERE mr IS NOT NULL AND mr <> '' UNION SELECT mr FROM pt_mr_phones") as $row) {
        $names[mb_strtolower($row['mr'])] = $row['mr'];
    }
    $phones = pt_mr_phone_map();
    $list = [];
    foreach ($names as $key => $name) {
        $list[] = ['mr' => $name, 'phone' => $phones[$key] ?? ''];
    }
    usort($list, fn ($a, $b) => strcasecmp($a['mr'], $b['mr']));
    return $list;
}

function save_mr_phone(string $mr, string $phone): void
{
    $mr = mb_substr(trim($mr), 0, 120);
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if ($mr === '') {
        return;
    }
    if ($phone === '') {
        run('DELETE FROM pt_mr_phones WHERE mr = ?', [$mr]);
        return;
    }
    run('INSERT INTO pt_mr_phones (mr, phone) VALUES (?, ?) ON DUPLICATE KEY UPDATE phone = VALUES(phone)', [$mr, mb_substr($phone, 0, 20)]);
}

function handle_mr_phones(array $user): void
{
    require_csrf();
    if (($_POST['action'] ?? '') === 'upload') {
        try {
            [$columns, $table] = pt_read_table($_FILES['file'] ?? []);
            if (!isset($columns['mr']) || !isset($columns['phone number'])) {
                throw new UserError('Columns must be: MR, Phone Number.');
            }
            $count = 0;
            tx(function () use ($table, $columns, &$count) {
                foreach ($table as $row) {
                    $mr = pt_text($row['cells'][$columns['mr']] ?? null, 120);
                    $phone = pt_text($row['cells'][$columns['phone number']] ?? null, 20);
                    if ($mr !== '' && $phone !== '') {
                        save_mr_phone($mr, $phone);
                        $count++;
                    }
                }
            });
            audit($user['id'], 'PT_MR_PHONES', 'setting', null, null, ['uploaded' => $count]);
            flash(number_format($count) . ' MR phone numbers saved.');
        } catch (UserError $error) {
            flash($error->getMessage(), false);
        }
        redirect('/payment-tracker/mr-phones');
    }
    $names = (array) ($_POST['mr'] ?? []);
    $phones = (array) ($_POST['phone'] ?? []);
    tx(function () use ($names, $phones) {
        foreach ($names as $index => $mr) {
            save_mr_phone((string) $mr, (string) ($phones[$index] ?? ''));
        }
    });
    audit($user['id'], 'PT_MR_PHONES', 'setting', null, null, ['saved' => count($names)]);
    flash('MR phone numbers saved.');
    redirect('/payment-tracker/mr-phones');
}

/* ---------- Addresses ---------- */

function pt_counts(): array
{
    try {
        [$sql, $params] = pt_due_sql(pt_due_filter_blank());
        $today = date('Y-m-d');
        return [
            'Payment Due' => (int) value("SELECT COUNT(*) FROM ($sql) x", $params),
            'Today Collection' => (int) value('SELECT COUNT(*) FROM pt_followups WHERE expected_date = ? AND received_amount IS NULL', [$today]),
            'Tomorrow' => (int) value('SELECT COUNT(*) FROM pt_followups WHERE expected_date = ?', [date('Y-m-d', strtotime('+1 day'))]),
            'Broken Promises' => (int) value('SELECT COUNT(*) FROM pt_followups f WHERE f.expected_date < ? AND f.received_amount IS NULL AND NOT EXISTS (SELECT 1 FROM pt_followups n WHERE n.party_name = f.party_name AND n.id > f.id)', [$today]),
            'Follow Ups' => (int) value('SELECT COUNT(*) FROM pt_followups'),
        ];
    } catch (Throwable $error) {
        error_log('Payment counts: ' . $error->getMessage());
        return [];
    }
}

function route_payment_tracker(bool $get, bool $post, string $path): void
{
    if ($path !== '/payment-tracker' && !str_starts_with($path, '/payment-tracker/')) {
        return;
    }
    $user = require_user();
    $m = [];
    if ($get && $path === '/payment-tracker') {
        redirect('/payment-tracker/due');
    }
    if ($get && $path === '/payment-tracker/due') {
        $filter = pt_due_filter();
        if (isset($_GET['export'])) {
            export_due($filter);
        }
        [$parties, $total, $page, $groups, $net] = pt_due_list($filter);
        render('pt_due', ['parties' => $parties, 'total' => $total, 'page' => $page, 'groups' => $groups, 'net' => $net, 'filter' => $filter, 'areas' => pt_choices('area'), 'mrs' => pt_choices('mr'), 'upload' => pt_last_upload()]);
    }
    if ($path === '/payment-tracker/party') {
        $name = trim((string) ($_POST['name'] ?? $_GET['name'] ?? ''));
        if ($name === '') {
            redirect('/payment-tracker/due');
        }
        $party = pt_party($name);
        if (!$party['invoices'] && !$party['history']) {
            not_found();
        }
        $error = null;
        $back = pt_back('/payment-tracker/due');
        if ($post) {
            require_csrf();
            try {
                save_followup($party, $user);
                flash('Follow up saved for ' . preg_replace('/\s+/', ' ', $name) . '.');
                redirect($back);
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        }
        render('pt_party', ['party' => $party, 'error' => $error, 'old' => $post ? $_POST : [], 'back' => $back, 'mrPhone' => pt_mr_phone_map()[mb_strtolower($party['mr'])] ?? '']);
    }
    $modes = ['/payment-tracker/today' => 'today', '/payment-tracker/tomorrow' => 'tomorrow', '/payment-tracker/broken' => 'broken', '/payment-tracker/followups' => 'all'];
    if ($get && isset($modes[$path])) {
        $mode = $modes[$path];
        $filter = pt_followup_filter($mode);
        [$entries, $total, $page, $groups, $summary] = pt_followup_list($mode, $filter);
        $messages = $filter['group'] === 'mr' && $mode !== 'all' ? pt_mr_messages($mode, $filter) : [];
        render('pt_followups', compact('mode', 'filter', 'entries', 'total', 'page', 'groups', 'summary', 'messages', 'path') + ['mrPhones' => pt_mr_phone_map()]);
    }
    if (preg_match('#^/payment-tracker/followups/(\d+)/received$#', $path, $m)) {
        $id = (int) $m[1];
        $entry = row('SELECT * FROM pt_followups WHERE id = ?', [$id]) ?? not_found();
        $error = null;
        $back = pt_back('/payment-tracker/today');
        if ($post) {
            require_csrf();
            try {
                save_received($id, $user);
                flash('Received amount saved for ' . preg_replace('/\s+/', ' ', $entry['party_name']) . '.');
                redirect($back);
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        }
        render('pt_received', ['entry' => $entry, 'error' => $error, 'back' => $back, 'old' => $post ? $_POST : []]);
    }
    if ($get && $path === '/payment-tracker/reports') {
        require_user(MANAGERS);
        $dayText = trim((string) ($_GET['day'] ?? ''));
        $day = ($dayText === '' ? null : parse_date($dayText)) ?? date('Y-m-d');
        render('pt_reports', ['day' => $day] + pt_reports($day));
    }
    if ($path === '/payment-tracker/upload') {
        $manager = require_user(MANAGERS);
        $error = null;
        $summary = null;
        if ($post) {
            require_csrf();
            try {
                if (($_POST['action'] ?? '') === 'save') {
                    $saved = save_outstanding($manager);
                    flash(number_format($saved) . ' outstanding rows saved. Payment Due is updated.');
                    redirect('/payment-tracker/due');
                }
                $summary = check_outstanding($_FILES['file'] ?? []);
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        } else {
            unset($_SESSION['pt_upload']);
        }
        render('pt_upload', ['error' => $error, 'summary' => $summary, 'last' => pt_last_upload(), 'history' => rows('SELECT u.*, s.full_name FROM pt_uploads u JOIN users s ON s.id = u.uploaded_by ORDER BY u.id DESC LIMIT 10')]);
    }
    if ($path === '/payment-tracker/mr-phones') {
        $manager = require_user(MANAGERS);
        if ($post) {
            handle_mr_phones($manager);
        }
        render('pt_mr_phones', ['list' => pt_mr_list()]);
    }
    not_found();
}
