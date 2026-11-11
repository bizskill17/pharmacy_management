<?php
declare(strict_types=1);

const CHALLAN_POOL = 500;

/** Short year used in challan numbers: a date in 2026-27 gives 26-27. */
function challan_fy(string $iso): string
{
    return substr(financial_year($iso), 2);
}

/** Creates numbers until the year has CHALLAN_POOL unused. Call inside a transaction. */
function top_up_challan_numbers(string $fy): void
{
    $series = row('SELECT * FROM challan_series WHERE fy = ? FOR UPDATE', [$fy]);
    if (!$series) {
        return;
    }
    $unused = (int) value('SELECT COUNT(*) FROM challan_numbers WHERE fy = ? AND used_by IS NULL', [$fy]);
    $seq = (int) $series['next_seq'];
    for ($left = CHALLAN_POOL - $unused; $left > 0; $left--, $seq++) {
        run('INSERT INTO challan_numbers (fy, challan_no, seq) VALUES (?, ?, ?)', [$fy, $series['prefix'] . str_pad((string) $seq, (int) $series['width'], '0', STR_PAD_LEFT), $seq]);
    }
    run('UPDATE challan_series SET next_seq = ? WHERE fy = ?', [$seq, $fy]);
}

function ensure_challan_pool(string $fy): void
{
    if (!value('SELECT fy FROM challan_series WHERE fy = ?', [$fy])) {
        return;
    }
    if ((int) value('SELECT COUNT(*) FROM challan_numbers WHERE fy = ? AND used_by IS NULL', [$fy]) < CHALLAN_POOL) {
        tx(fn () => top_up_challan_numbers($fy));
    }
}

/** Unused numbers for the year of a date. Null means the year's first number has not been set. */
function challan_choices(string $iso): ?array
{
    $fy = challan_fy($iso);
    if (!value('SELECT fy FROM challan_series WHERE fy = ?', [$fy])) {
        return null;
    }
    ensure_challan_pool($fy);
    return array_column(rows('SELECT challan_no FROM challan_numbers WHERE fy = ? AND used_by IS NULL ORDER BY seq', [$fy]), 'challan_no');
}

function active_user_list(?array $keep = null): array
{
    $ids = array_values(array_filter($keep ?? []));
    $extra = $ids ? ' OR id IN (' . implode(',', array_map('intval', $ids)) . ')' : '';
    return rows("SELECT id, full_name FROM users WHERE is_active = 1$extra ORDER BY full_name");
}

function posted_user(string $field, string $label, ?int $current = null): int
{
    $id = (int) ($_POST[$field] ?? 0);
    if ($id < 1 || (!value('SELECT id FROM users WHERE id = ? AND is_active = 1', [$id]) && $id !== $current)) {
        throw new UserError("Choose $label from the list.");
    }
    return $id;
}

/** Reads the fields common to the Add and Edit forms. */
function sales_challan_input(?array $current = null): array
{
    $number = trim((string) ($_POST['challan_no'] ?? ''));
    $date = parse_date(trim((string) ($_POST['challan_date'] ?? '')));
    $typed = mb_substr(clean_name((string) ($_POST['party_name'] ?? '')), 0, 200);
    $party = $typed === '' ? null : row('SELECT id, name FROM parties WHERE name = ? AND is_active = 1 AND is_customer = 1 LIMIT 1', [$typed]);
    if (!$party && $current && $current['party_id'] && $typed === $current['party_name']) {
        $party = ['id' => $current['party_id'], 'name' => $current['party_name']];
    }
    $amount = parse_amount((string) ($_POST['amount'] ?? ''));
    $items = parse_whole((string) ($_POST['item_count'] ?? ''));
    if ($number === '') {
        throw new UserError('Choose a Challan No.');
    }
    if (!$date) {
        throw new UserError('Enter Challan Date as dd/mm/yyyy.');
    }
    if ($typed === '') {
        throw new UserError('Select or type the Party Name.');
    }
    if ($amount === null) {
        throw new UserError('Amount must be a number.');
    }
    if ($items === null) {
        throw new UserError('No. of Items must be a whole number.');
    }
    $madeBy = posted_user('made_by', 'Challan Made By', $current ? (int) $current['made_by'] : null);
    return ['number' => $number, 'date' => $date, 'party' => $party ? (int) $party['id'] : null, 'party_text' => $party ? null : $typed, 'amount' => $amount, 'items' => $items, 'made_by' => $madeBy];
}

/** Locks a number and checks it is free for this challan. Call inside a transaction. */
function lock_challan_number(string $number, string $date, int $challanId): array
{
    $row = row('SELECT id, fy, used_by FROM challan_numbers WHERE challan_no = ? FOR UPDATE', [$number]);
    if (!$row) {
        throw new UserError('That Challan No. is not in the list.');
    }
    if ($row['used_by'] !== null && (int) $row['used_by'] !== $challanId) {
        throw new UserError("Challan No. $number has just been used. Choose another.");
    }
    if ($row['fy'] !== challan_fy($date)) {
        throw new UserError("Challan No. $number belongs to FY {$row['fy']}, but the Challan Date is in FY " . challan_fy($date) . '.');
    }
    return $row;
}

function mark_challan_used(array $number, int $challanId): void
{
    run('UPDATE challan_numbers SET used_by = ? WHERE id = ?', [$challanId, $number['id']]);
    top_up_challan_numbers($number['fy']);
}

function create_sales_challan(array $user): string
{
    $input = sales_challan_input();
    return tx(function () use ($input, $user) {
        $number = lock_challan_number($input['number'], $input['date'], 0);
        run(
            "INSERT INTO sales_challans (challan_no, challan_date, financial_year, party_id, party_text, amount, item_count, made_by, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$input['number'], $input['date'], financial_year($input['date']), $input['party'], $input['party_text'], $input['amount'], $input['items'], $input['made_by'], $user['id']]
        );
        $id = (int) db()->lastInsertId();
        mark_challan_used($number, $id);
        audit($user['id'], 'SALES_CHALLAN_ADD', 'sales_challan', $id, null, ['challan_no' => $input['number']]);
        return $input['number'];
    });
}

function sales_challan_select(): string
{
    return 'SELECT c.*, COALESCE(p.name, c.party_text) AS party_name, mu.full_name AS made_name, pu.full_name AS picked_name, xu.full_name AS matched_name
            FROM sales_challans c
            LEFT JOIN parties p ON p.id = c.party_id
            JOIN users mu ON mu.id = c.made_by
            LEFT JOIN users pu ON pu.id = c.picked_by
            LEFT JOIN users xu ON xu.id = c.matched_by';
}

function find_sales_challan(int $id): array
{
    return row(sales_challan_select() . ' WHERE c.id = ? AND c.deleted_at IS NULL', [$id]) ?? not_found();
}

function list_sales_challans(?string $status, string $query): array
{
    $where = ['c.deleted_at IS NULL'];
    $params = [];
    if ($status === 'NO_PARTY') {
        $where[] = 'c.party_id IS NULL';
    } elseif ($status) {
        $where[] = 'c.status = ?';
        $params[] = $status;
    }
    if ($query !== '') {
        $pattern = like_pattern($query);
        $where[] = '(c.challan_no LIKE ? OR COALESCE(p.name, c.party_text) LIKE ?)';
        array_push($params, $pattern, $pattern);
    }
    $clause = 'WHERE ' . implode(' AND ', $where);
    $total = (int) value("SELECT COUNT(*) FROM sales_challans c LEFT JOIN parties p ON p.id = c.party_id $clause", $params);
    $page = clamp_page(requested_page(), $total);
    $rows = rows(sales_challan_select() . " $clause ORDER BY c.challan_date DESC, c.id DESC LIMIT " . PAGE_SIZE . ' OFFSET ' . page_offset($page), $params);
    return [$rows, $total, $page];
}

function save_pick(int $id, array $user): void
{
    $picked = posted_user('picked_by', 'Picked By');
    $done = tx(function () use ($id, $picked, $user) {
        $status = value('SELECT status FROM sales_challans WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]);
        if ($status !== 'PACKING') {
            return false;
        }
        run("UPDATE sales_challans SET pick_time = ?, picked_by = ?, status = 'MATCHING' WHERE id = ?", [date('Y-m-d H:i:s'), $picked, $id]);
        audit($user['id'], 'SALES_CHALLAN_PICK', 'sales_challan', $id, null, ['picked_by' => $picked]);
        return true;
    });
    flash($done ? 'Moved to Matching.' : 'This challan has already been picked.', $done);
    redirect('/sales-challan/master');
}

function save_match(int $id, array $user): void
{
    $matched = posted_user('matched_by', 'Matched By');
    $quantity = parse_whole((string) ($_POST['match_quantity'] ?? ''));
    if ($quantity === null) {
        throw new UserError('Match Quantity must be a whole number.');
    }
    $photos = save_purchase_in_photos();
    if (!$photos) {
        throw new UserError('Add Photo 1.');
    }
    try {
        $result = tx(function () use ($id, $matched, $quantity, $photos, $user) {
            $challan = row('SELECT status, item_count FROM sales_challans WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]);
            if (!$challan || $challan['status'] !== 'MATCHING') {
                throw new UserError('This challan is not waiting for matching.');
            }
            run(
                "UPDATE sales_challans SET match_time = ?, matched_by = ?, match_quantity = ?, photo_1 = ?, photo_2 = ?, photo_3 = ?, photo_4 = ?, photo_5 = ?, status = 'DONE' WHERE id = ?",
                [date('Y-m-d H:i:s'), $matched, $quantity, $photos[0] ?? null, $photos[1] ?? null, $photos[2] ?? null, $photos[3] ?? null, $photos[4] ?? null, $id]
            );
            audit($user['id'], 'SALES_CHALLAN_MATCH', 'sales_challan', $id, null, ['matched_by' => $matched, 'match_quantity' => $quantity]);
            return (int) $challan['item_count'];
        });
    } catch (Throwable $error) {
        remove_employee_files($photos);
        throw $error;
    }
    flash($result === $quantity ? 'Matching saved.' : "Matching saved. Match Quantity $quantity is different from No. of Items $result.", $result === $quantity);
    redirect('/sales-challan/master');
}

function update_sales_challan(array $current, array $user): void
{
    $id = (int) $current['id'];
    $input = sales_challan_input($current);
    $picked = $current['pick_time'] ? posted_user('picked_by', 'Picked By', (int) $current['picked_by']) : null;
    $matched = $current['match_time'] ? posted_user('matched_by', 'Matched By', (int) $current['matched_by']) : null;
    $quantity = null;
    if ($current['match_time']) {
        $quantity = parse_whole((string) ($_POST['match_quantity'] ?? ''));
        if ($quantity === null) {
            throw new UserError('Match Quantity must be a whole number.');
        }
    }
    $kept = [];
    $removed = [];
    for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++) {
        $key = $current["photo_$slot"];
        if ($key && isset($_POST["keep_$slot"])) {
            $kept[] = $key;
        } elseif ($key) {
            $removed[] = $key;
        }
    }
    $added = $current['match_time'] ? save_purchase_in_photos() : [];
    $photos = array_slice(array_merge($kept, $added), 0, PURCHASE_IN_PHOTOS);
    if ($current['match_time'] && !$photos) {
        remove_employee_files($added);
        throw new UserError('Keep or add at least Photo 1.');
    }
    try {
        tx(function () use ($current, $id, $input, $picked, $matched, $quantity, $photos, $user) {
            if ($input['number'] !== $current['challan_no']) {
                run('UPDATE challan_numbers SET used_by = NULL WHERE challan_no = ? AND used_by = ?', [$current['challan_no'], $id]);
            }
            mark_challan_used(lock_challan_number($input['number'], $input['date'], $id), $id);
            run(
                'UPDATE sales_challans SET challan_no = ?, challan_date = ?, financial_year = ?, party_id = ?, party_text = ?, amount = ?, item_count = ?, made_by = ?, picked_by = ?, matched_by = ?, match_quantity = ?, photo_1 = ?, photo_2 = ?, photo_3 = ?, photo_4 = ?, photo_5 = ? WHERE id = ?',
                [$input['number'], $input['date'], financial_year($input['date']), $input['party'], $input['party_text'], $input['amount'], $input['items'], $input['made_by'], $picked, $matched, $quantity, $photos[0] ?? null, $photos[1] ?? null, $photos[2] ?? null, $photos[3] ?? null, $photos[4] ?? null, $id]
            );
            audit($user['id'], 'SALES_CHALLAN_EDIT', 'sales_challan', $id, ['challan_no' => $current['challan_no'], 'amount' => $current['amount']], ['challan_no' => $input['number'], 'amount' => $input['amount']]);
        });
    } catch (Throwable $error) {
        remove_employee_files($added);
        throw $error;
    }
    remove_employee_files($removed);
}

function delete_sales_challan(int $id, array $user): void
{
    tx(function () use ($id, $user) {
        $challan = row('SELECT challan_no FROM sales_challans WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]) ?? not_found();
        run('UPDATE challan_numbers SET used_by = NULL WHERE used_by = ?', [$id]);
        run('UPDATE sales_challans SET deleted_at = NOW(), deleted_by = ? WHERE id = ?', [$user['id'], $id]);
        audit($user['id'], 'SALES_CHALLAN_DELETE', 'sales_challan', $id, ['challan_no' => $challan['challan_no']]);
    });
}

function serve_sales_challan_photo(int $id, int $slot): never
{
    api_user();
    $key = value("SELECT photo_$slot FROM sales_challans WHERE id = ? AND deleted_at IS NULL", [$id]);
    $path = $key ? storage_dir() . '/uploads/' . $key : '';
    if (!$key || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    send_image($path, 'private, no-store');
}

/** Matches a typed party name to Party Master. */
function set_sales_challan_party(int $id, array $user): void
{
    $party = row('SELECT id, name FROM parties WHERE name = ? AND is_active = 1 AND is_customer = 1 LIMIT 1', [clean_name((string) ($_POST['party_name'] ?? ''))]);
    if (!$party) {
        throw new UserError('Select the Party Name from the list.');
    }
    tx(function () use ($id, $party, $user) {
        $before = row('SELECT party_text FROM sales_challans WHERE id = ? AND deleted_at IS NULL AND party_id IS NULL FOR UPDATE', [$id]);
        if (!$before) {
            throw new UserError('This challan already has a party from Party Master.');
        }
        run('UPDATE sales_challans SET party_id = ?, party_text = NULL WHERE id = ?', [$party['id'], $id]);
        audit($user['id'], 'SALES_CHALLAN_PARTY', 'sales_challan', $id, ['party_text' => $before['party_text']], ['party' => $party['name']]);
    });
}

/** Numbers skipped in each year: unused, but lower than the highest number already used. */
function missing_challans(string $query): array
{
    $where = 'n.used_by IS NULL AND n.seq < (SELECT MAX(u.seq) FROM challan_numbers u WHERE u.fy = n.fy AND u.used_by IS NOT NULL)';
    $params = [];
    if ($query !== '') {
        $where .= ' AND n.challan_no LIKE ?';
        $params[] = like_pattern($query);
    }
    $total = (int) value("SELECT COUNT(*) FROM challan_numbers n WHERE $where", $params);
    $page = clamp_page(requested_page(), $total);
    $rows = rows("SELECT n.challan_no, n.fy FROM challan_numbers n WHERE $where ORDER BY n.fy DESC, n.seq LIMIT " . PAGE_SIZE . ' OFFSET ' . page_offset($page), $params);
    return [$rows, $total, $page];
}

/** Sets a year's first number. A year with used numbers cannot be changed. */
function start_challan_series(array $admin): void
{
    require_csrf();
    $first = strtoupper(trim((string) ($_POST['first_no'] ?? '')));
    $m = [];
    if (!preg_match('/^(FY(\d{2})-(\d{2})[A-Z]*)(\d{1,9})$/', $first, $m) || ((int) $m[2] + 1) % 100 !== (int) $m[3]) {
        flash('Enter the first number like FY27-28CA000001.', false);
        redirect('/admin/challan-numbers');
    }
    $fy = "$m[2]-$m[3]";
    try {
        tx(function () use ($fy, $m, $first, $admin) {
            $used = (int) value('SELECT COUNT(*) FROM challan_numbers WHERE fy = ? AND used_by IS NOT NULL', [$fy]);
            if ($used > 0) {
                throw new UserError("FY $fy already has $used used numbers, so its first number cannot be changed.");
            }
            run('DELETE FROM challan_numbers WHERE fy = ?', [$fy]);
            run('INSERT INTO challan_series (fy, prefix, width, next_seq) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE prefix = VALUES(prefix), width = VALUES(width), next_seq = VALUES(next_seq)', [$fy, $m[1], strlen($m[4]), (int) $m[4]]);
            top_up_challan_numbers($fy);
            audit($admin['id'], 'CHALLAN_SERIES_START', 'challan_series', null, null, ['first_no' => $first]);
        });
        flash("FY $fy starts at $first. " . CHALLAN_POOL . ' unused numbers are ready.');
    } catch (UserError $error) {
        flash($error->getMessage(), false);
    }
    redirect('/admin/challan-numbers?' . http_build_query(['fy' => $fy]));
}

function show_challan_numbers(): never
{
    foreach (array_column(rows('SELECT fy FROM challan_series'), 'fy') as $fy) {
        ensure_challan_pool($fy);
    }
    $summary = rows('SELECT s.fy, s.prefix, COALESCE(SUM(n.used_by IS NULL), 0) AS unused, COALESCE(SUM(n.used_by IS NOT NULL), 0) AS used FROM challan_series s LEFT JOIN challan_numbers n ON n.fy = s.fy GROUP BY s.fy, s.prefix ORDER BY s.fy DESC');
    $fy = (string) ($_GET['fy'] ?? '');
    $state = (string) ($_GET['state'] ?? '');
    $query = trim((string) ($_GET['q'] ?? ''));
    $where = [];
    $params = [];
    if ($fy !== '') {
        $where[] = 'n.fy = ?';
        $params[] = $fy;
    }
    if ($state === 'unused') {
        $where[] = 'n.used_by IS NULL';
    } elseif ($state === 'used') {
        $where[] = 'n.used_by IS NOT NULL';
    }
    if ($query !== '') {
        $where[] = 'n.challan_no LIKE ?';
        $params[] = like_pattern($query);
    }
    $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int) value("SELECT COUNT(*) FROM challan_numbers n $clause", $params);
    $page = clamp_page(requested_page(), $total);
    $numbers = rows("SELECT n.*, c.challan_date, COALESCE(p.name, c.party_text) AS party_name FROM challan_numbers n LEFT JOIN sales_challans c ON c.id = n.used_by LEFT JOIN parties p ON p.id = c.party_id $clause ORDER BY n.fy DESC, n.seq LIMIT " . PAGE_SIZE . ' OFFSET ' . page_offset($page), $params);
    render('admin_challan_numbers', compact('summary', 'numbers', 'total', 'page', 'fy', 'state', 'query'));
}

/** Parties bulk upload: the checked rows wait in the session until they are saved. */
const PARTY_KINDS = ['sales' => ['label' => 'Sales Parties', 'flag' => 'is_customer', 'type' => 'SALE'], 'purchase' => ['label' => 'Purchase Parties', 'flag' => 'is_supplier', 'type' => 'PURCHASE']];

function check_party_file(string $kind): void
{
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new UserError('Choose a file to upload.');
    }
    $table = read_upload($file);
    $headers = array_map(fn ($cell) => strtolower(trim((string) ($cell instanceof DateTimeInterface ? '' : $cell))), array_slice($table['headers'], 0, 2));
    if ($headers !== ['party name', 'area']) {
        throw new UserError('Columns must be, in this order: Party Name, Area.');
    }
    $flag = PARTY_KINDS[$kind]['flag'];
    $existing = [];
    foreach (rows("SELECT name, source_name, $flag AS listed FROM parties") as $party) {
        foreach ([$party['name'], $party['source_name']] as $known) {
            $existing[mb_strtolower(clean_name((string) $known))] = (bool) $party['listed'] || !empty($existing[mb_strtolower(clean_name((string) $known))]);
        }
    }
    $seen = [];
    $checked = [];
    foreach ($table['rows'] as $row) {
        $name = clean_name((string) (cell_text($row['cells'][0] ?? null, 120) ?? ''));
        $area = clean_name((string) (cell_text($row['cells'][1] ?? null, 100) ?? ''));
        $key = mb_strtolower($name);
        $outcome = 'ready';
        $reason = '';
        if ($name === '') {
            [$outcome, $reason] = ['failed', 'Party Name is blank'];
        } elseif (!empty($existing[$key])) {
            [$outcome, $reason] = ['skipped', 'Already exists'];
        } elseif (isset($existing[$key])) {
            $reason = 'In the other party list, will be added here too';
        } elseif (isset($seen[$key])) {
            [$outcome, $reason] = ['skipped', 'Repeated in this file'];
        }
        $seen[$key] = true;
        $checked[] = ['row' => $row['number'], 'name' => $name, 'area' => $area, 'outcome' => $outcome, 'reason' => $reason];
    }
    $_SESSION['party_bulk'] = ['kind' => $kind, 'rows' => $checked];
}

function save_party_file(string $kind, array $admin): int
{
    $stored = $_SESSION['party_bulk'] ?? [];
    $checked = ($stored['kind'] ?? '') === $kind ? $stored['rows'] : [];
    $ready = array_filter($checked, fn ($row) => $row['outcome'] === 'ready');
    $flag = PARTY_KINDS[$kind]['flag'];
    tx(function () use ($ready, $admin, $kind, $flag) {
        foreach ($ready as $row) {
            $area = $row['area'] === '' ? null : upsert_area($row['area']);
            $found = value('SELECT id FROM parties WHERE name = ? OR source_name = ? LIMIT 1', [$row['name'], $row['name']]);
            if ($found) {
                run("UPDATE parties SET $flag = 1, area_id = COALESCE(area_id, ?) WHERE id = ?", [$area, $found]);
            } else {
                upsert_party($row['name'], PARTY_KINDS[$kind]['type'], $area);
            }
        }
        audit($admin['id'], 'PARTY_BULK_UPLOAD', 'party', null, null, ['created' => count($ready)]);
    });
    unset($_SESSION['party_bulk']);
    return count($ready);
}

const USER_BULK_HEADERS = ['employee name', 'phone no', 'email', 'address', 'passcode', 'salary', 'status'];

/** Users bulk upload: only hashed passcodes wait in the session until the rows are saved. */
function check_user_file(): void
{
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new UserError('Choose a file to upload.');
    }
    $table = read_upload($file);
    $headers = array_map(fn ($cell) => strtolower(trim((string) ($cell instanceof DateTimeInterface ? '' : $cell))), array_slice($table['headers'], 0, count(USER_BULK_HEADERS)));
    if ($headers !== USER_BULK_HEADERS) {
        throw new UserError('Columns must be, in this order: Employee Name, Phone No, Email, Address, Passcode, Salary, Status.');
    }
    if (count($table['rows']) > 300) {
        throw new UserError('A file can have at most 300 employees.');
    }
    $existing = array_flip(array_column(rows('SELECT phone FROM users'), 'phone'));
    $seen = [];
    $checked = [];
    foreach ($table['rows'] as $row) {
        $cell = fn (int $index, int $limit) => (string) (cell_text($row['cells'][$index] ?? null, $limit) ?? '');
        $name = clean_name($cell(0, 120));
        $phone = $cell(1, 20);
        $email = $cell(2, 150);
        $address = $cell(3, 255);
        $passcode = $cell(4, 200);
        $salaryText = $cell(5, 20);
        $salary = $salaryText === '' ? null : parse_amount($salaryText);
        $status = strtolower($cell(6, 20));
        $reason = match (true) {
            $name === '' => 'Employee Name is blank',
            !preg_match('/^\d{6,15}$/', $phone) => 'Phone No is not valid',
            $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Email is not valid',
            strlen($passcode) < MIN_PASSWORD_LENGTH => 'Passcode must have at least ' . MIN_PASSWORD_LENGTH . ' characters',
            $salaryText !== '' && $salary === null => 'Salary is not a number',
            !in_array($status, ['', 'active', 'inactive'], true) => 'Status must be Active or Inactive',
            default => '',
        };
        $outcome = $reason === '' ? 'ready' : 'failed';
        if ($outcome === 'ready' && isset($existing[$phone])) {
            [$outcome, $reason] = ['skipped', 'Phone No already exists'];
        } elseif ($outcome === 'ready' && isset($seen[$phone])) {
            [$outcome, $reason] = ['skipped', 'Repeated in this file'];
        }
        $seen[$phone] = true;
        $checked[] = [
            'row' => $row['number'], 'name' => $name, 'phone' => $phone, 'email' => $email, 'address' => $address, 'salary' => $salary, 'active' => $status === 'inactive' ? 0 : 1,
            'hash' => $outcome === 'ready' ? password_hash($passcode, PASSWORD_DEFAULT) : '', 'outcome' => $outcome, 'reason' => $reason,
        ];
    }
    $_SESSION['user_bulk'] = $checked;
}

function save_user_file(array $admin): int
{
    $ready = array_filter($_SESSION['user_bulk'] ?? [], fn ($row) => $row['outcome'] === 'ready');
    $created = tx(function () use ($ready, $admin) {
        $count = 0;
        foreach ($ready as $row) {
            $count += run(
                "INSERT IGNORE INTO users (full_name, phone, email, address, salary, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 'UPLOADER', ?)",
                [$row['name'], $row['phone'], $row['email'] === '' ? null : $row['email'], $row['address'] === '' ? null : $row['address'], $row['salary'], $row['hash'], $row['active']]
            )->rowCount();
        }
        audit($admin['id'], 'USER_BULK_UPLOAD', 'user', null, null, ['created' => $count]);
        return $count;
    });
    unset($_SESSION['user_bulk']);
    return $created;
}

const CLEAR_TABLES = [
    'audit_changes' => 'History of changes', 'audit_log' => 'Activity log', 'import_errors' => 'Upload errors', 'attachments' => 'Photos on records',
    'documents' => 'Invoices, PROHUB C&F, Purchase Challan and Expiry records', 'import_batches' => 'Bulk upload history', 'challan_numbers' => 'Challan No. list',
    'sales_challans' => 'Sales Challan records', 'challan_series' => 'First Challan No. of each FY', 'purchase_in' => 'Purchase In records',
    'pt_followups' => 'Payment follow ups', 'pt_invoices' => 'Uploaded outstanding', 'pt_uploads' => 'Outstanding upload history',
    'parties' => 'Sales Parties and Purchase Parties', 'areas' => 'Areas',
];

function clear_counts(): array
{
    $counts = [];
    foreach (CLEAR_TABLES as $table => $label) {
        $counts[$label] = (int) value("SELECT COUNT(*) FROM $table");
    }
    return $counts;
}

/** Removes every record except users, then the photo files those records used. */
function clear_all_data(array $admin): void
{
    $files = array_column(rows('SELECT file_key FROM attachments'), 'file_key');
    foreach (['purchase_in', 'sales_challans'] as $table) {
        foreach (rows("SELECT photo_1, photo_2, photo_3, photo_4, photo_5 FROM $table") as $row) {
            array_push($files, ...array_values(array_filter($row)));
        }
    }
    $counts = clear_counts();
    tx(function () {
        run('UPDATE challan_numbers SET used_by = NULL');
        foreach (array_keys(CLEAR_TABLES) as $table) {
            run("DELETE FROM $table");
        }
        run("UPDATE app_settings SET setting_value = '0' WHERE setting_key = ?", [PURCHASE_IN_LAST_SL]);
    });
    remove_employee_files($files);
    audit($admin['id'], 'CLEAR_ALL_DATA', 'system', null, $counts, null);
}

function route_masters_additions(bool $get, bool $post, string $path): void
{
    if ($path === '/admin/clear-data') {
        $admin = require_user(['ADMIN']);
        $error = null;
        if ($post) {
            require_csrf();
            if (trim((string) ($_POST['confirm'] ?? '')) !== 'CLEAR') {
                $error = 'Type CLEAR in capitals to confirm.';
            } else {
                clear_all_data($admin);
                flash('All data cleared. Users are kept.');
                redirect('/admin/clear-data');
            }
        }
        render('admin_clear_data', ['counts' => clear_counts(), 'users' => (int) value('SELECT COUNT(*) FROM users'), 'error' => $error]);
    }
    if ($get && $path === '/admin/users/template') {
        require_user(ADMINS);
        send_xlsx('users-template.xlsx', ['Users' => [['Employee Name', 'Phone No', 'Email', 'Address', 'Passcode', 'Salary', 'Status']]]);
    }
    if ($path === '/admin/users/bulk') {
        $admin = require_user(ADMINS);
        $error = null;
        if ($post) {
            require_csrf();
            try {
                if (($_POST['action'] ?? '') === 'save') {
                    $created = save_user_file($admin);
                    flash(number_format($created) . ' employees added.');
                    redirect('/admin/users');
                }
                check_user_file();
            } catch (UserError $failure) {
                unset($_SESSION['user_bulk']);
                $error = $failure->getMessage();
            }
        } else {
            unset($_SESSION['user_bulk']);
        }
        render('admin_users_bulk', ['rows' => $_SESSION['user_bulk'] ?? null, 'error' => $error]);
    }
    if ($get && $path === '/admin/parties') {
        redirect('/admin/parties/sales');
    }
    if ($get && preg_match('#^/admin/parties/(sales|purchase)/template$#', $path)) {
        require_user(ADMINS);
        send_xlsx('parties-template.xlsx', ['Parties' => [['Party Name', 'Area']]]);
    }
    if ($get && preg_match('#^/admin/parties/(sales|purchase)$#', $path, $m)) {
        require_user(ADMINS);
        $kind = $m[1];
        $flag = PARTY_KINDS[$kind]['flag'];
        $query = trim((string) ($_GET['q'] ?? ''));
        $pattern = like_pattern($query);
        $total = (int) value("SELECT COUNT(*) FROM parties WHERE $flag = 1 AND (name LIKE ? OR source_name LIKE ?)", [$pattern, $pattern]);
        $page = clamp_page(requested_page(), $total);
        render('admin_parties', [
            'kind' => $kind, 'query' => $query, 'total' => $total, 'page' => $page,
            'parties' => rows("SELECT id, name, source_name, area_id, is_active FROM parties WHERE $flag = 1 AND (name LIKE ? OR source_name LIKE ?) ORDER BY name LIMIT " . PAGE_SIZE . ' OFFSET ' . page_offset($page), [$pattern, $pattern]),
            'areas' => rows('SELECT id, name FROM areas WHERE is_active = 1 ORDER BY name'),
        ]);
    }
    if (preg_match('#^/admin/parties/(sales|purchase)/bulk$#', $path, $m)) {
        $admin = require_user(ADMINS);
        $kind = $m[1];
        $error = null;
        if ($post) {
            require_csrf();
            try {
                if (($_POST['action'] ?? '') === 'save') {
                    $created = save_party_file($kind, $admin);
                    flash(number_format($created) . ' parties added to ' . PARTY_KINDS[$kind]['label'] . '.');
                    redirect("/admin/parties/$kind");
                }
                check_party_file($kind);
            } catch (UserError $failure) {
                unset($_SESSION['party_bulk']);
                $error = $failure->getMessage();
            }
        } else {
            unset($_SESSION['party_bulk']);
        }
        $stored = $_SESSION['party_bulk'] ?? null;
        render('admin_parties_bulk', ['kind' => $kind, 'rows' => $stored && $stored['kind'] === $kind ? $stored['rows'] : null, 'error' => $error]);
    }
    if ($path === '/admin/challan-numbers') {
        $admin = require_user(ADMINS);
        if ($post) {
            start_challan_series($admin);
        }
        show_challan_numbers();
    }
}

/** Handles every Sales Challan address, and returns for any other address. */
function route_sales_challan(bool $get, bool $post, string $path): void
{
    $m = [];
    if ($get && preg_match('#^/api/sales-challan/(\d+)/([1-5])$#', $path, $m)) {
        serve_sales_challan_photo((int) $m[1], (int) $m[2]);
    }
    if ($get && $path === '/api/sales-challan/numbers') {
        api_user();
        $iso = parse_date(trim((string) ($_GET['date'] ?? '')));
        if (!$iso) {
            json_response(['error' => 'Enter Challan Date as dd/mm/yyyy.'], 400);
        }
        json_response(['fy' => challan_fy($iso), 'numbers' => challan_choices($iso)]);
    }
    if ($path !== '/sales-challan' && !str_starts_with($path, '/sales-challan/')) {
        return;
    }
    $user = require_user();
    $manager = in_array($user['role'], MANAGERS, true);
    if ($get && $path === '/sales-challan') {
        redirect('/sales-challan/add');
    }
    if ($path === '/sales-challan/add') {
        $error = null;
        if ($post) {
            require_csrf();
            try {
                $number = create_sales_challan($user);
                flash("Challan $number saved. It is now in Pending Packing.");
                redirect('/sales-challan/master');
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        }
        render('sales_challan_add', ['old' => $post ? $_POST : [], 'error' => $error, 'users' => active_user_list()]);
    }
    if ($get && $path === '/sales-challan/pending-party') {
        $query = trim((string) ($_GET['q'] ?? ''));
        [$challans, $total, $page] = list_sales_challans('NO_PARTY', $query);
        render('sales_challan_pending_party', compact('challans', 'total', 'page', 'query'));
    }
    if (preg_match('#^/sales-challan/(\d+)/party$#', $path, $m)) {
        $id = (int) $m[1];
        $challan = find_sales_challan($id);
        $error = null;
        if ($post) {
            require_csrf();
            try {
                set_sales_challan_party($id, $user);
                flash("Party updated for {$challan['challan_no']}.");
                redirect('/sales-challan/pending-party');
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        }
        if ($challan['party_id']) {
            redirect('/sales-challan/pending-party');
        }
        render('sales_challan_party', compact('challan', 'error'));
    }
    if ($get && $path === '/sales-challan/missing') {
        $query = trim((string) ($_GET['q'] ?? ''));
        [$numbers, $total, $page] = missing_challans($query);
        render('sales_challan_missing', compact('numbers', 'total', 'page', 'query'));
    }
    $views = ['/sales-challan/packing' => 'PACKING', '/sales-challan/matching' => 'MATCHING', '/sales-challan/master' => null];
    if ($get && array_key_exists($path, $views)) {
        $query = trim((string) ($_GET['q'] ?? ''));
        [$challans, $total, $page] = list_sales_challans($views[$path], $query);
        render('sales_challan_list', compact('challans', 'total', 'page', 'query', 'path'));
    }
    if (!preg_match('#^/sales-challan/(\d+)(?:/(pick|match|edit|delete))?$#', $path, $m)) {
        not_found();
    }
    $id = (int) $m[1];
    $action = $m[2] ?? '';
    if ($post && $action === 'pick') {
        require_csrf();
        try {
            save_pick($id, $user);
        } catch (UserError $failure) {
            flash($failure->getMessage(), false);
            redirect("/sales-challan/$id?update=1");
        }
    }
    if ($post && $action === 'match') {
        require_csrf();
        try {
            save_match($id, $user);
        } catch (UserError $failure) {
            flash($failure->getMessage(), false);
            redirect("/sales-challan/$id?update=1");
        }
    }
    if ($post && $action === 'delete') {
        require_user(MANAGERS);
        require_csrf();
        delete_sales_challan($id, $user);
        flash('Challan deleted. Its number is unused again.');
        redirect('/sales-challan/master');
    }
    if ($action === 'edit') {
        require_user(MANAGERS);
        $challan = find_sales_challan($id);
        $error = null;
        if ($post) {
            require_csrf();
            try {
                update_sales_challan($challan, $user);
                flash('Challan updated.');
                redirect('/sales-challan/master');
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        }
        render('sales_challan_edit', ['challan' => $challan, 'old' => $post ? $_POST : [], 'error' => $error, 'users' => active_user_list([$challan['made_by'], $challan['picked_by'], $challan['matched_by']])]);
    }
    if ($get && $action === '') {
        render('sales_challan_entry', ['challan' => find_sales_challan($id), 'users' => active_user_list(), 'manager' => $manager]);
    }
    not_found();
}
