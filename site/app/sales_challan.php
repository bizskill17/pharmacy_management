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
    $party = row('SELECT id, name FROM parties WHERE name = ? AND is_active = 1 LIMIT 1', [clean_name((string) ($_POST['party_name'] ?? ''))]);
    if (!$party && $current && clean_name((string) ($_POST['party_name'] ?? '')) === $current['party_name']) {
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
    if (!$party) {
        throw new UserError('Choose the Party Name from the list.');
    }
    if ($amount === null) {
        throw new UserError('Amount must be a number.');
    }
    if ($items === null) {
        throw new UserError('No. of Items must be a whole number.');
    }
    $madeBy = posted_user('made_by', 'Challan Made By', $current ? (int) $current['made_by'] : null);
    return ['number' => $number, 'date' => $date, 'party' => (int) $party['id'], 'amount' => $amount, 'items' => $items, 'made_by' => $madeBy];
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
            "INSERT INTO sales_challans (challan_no, challan_date, financial_year, party_id, amount, item_count, made_by, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$input['number'], $input['date'], financial_year($input['date']), $input['party'], $input['amount'], $input['items'], $input['made_by'], $user['id']]
        );
        $id = (int) db()->lastInsertId();
        mark_challan_used($number, $id);
        audit($user['id'], 'SALES_CHALLAN_ADD', 'sales_challan', $id, null, ['challan_no' => $input['number']]);
        return $input['number'];
    });
}

function sales_challan_select(): string
{
    return 'SELECT c.*, p.name AS party_name, mu.full_name AS made_name, pu.full_name AS picked_name, xu.full_name AS matched_name
            FROM sales_challans c
            JOIN parties p ON p.id = c.party_id
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
    if ($status) {
        $where[] = 'c.status = ?';
        $params[] = $status;
    }
    if ($query !== '') {
        $pattern = like_pattern($query);
        $where[] = '(c.challan_no LIKE ? OR p.name LIKE ?)';
        array_push($params, $pattern, $pattern);
    }
    $clause = 'WHERE ' . implode(' AND ', $where);
    $total = (int) value("SELECT COUNT(*) FROM sales_challans c JOIN parties p ON p.id = c.party_id $clause", $params);
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
                'UPDATE sales_challans SET challan_no = ?, challan_date = ?, financial_year = ?, party_id = ?, amount = ?, item_count = ?, made_by = ?, picked_by = ?, matched_by = ?, match_quantity = ?, photo_1 = ?, photo_2 = ?, photo_3 = ?, photo_4 = ?, photo_5 = ? WHERE id = ?',
                [$input['number'], $input['date'], financial_year($input['date']), $input['party'], $input['amount'], $input['items'], $input['made_by'], $picked, $matched, $quantity, $photos[0] ?? null, $photos[1] ?? null, $photos[2] ?? null, $photos[3] ?? null, $photos[4] ?? null, $id]
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
    header('Content-Type: ' . (detect_image((string) file_get_contents($path, false, null, 0, 16)) ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
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
    $numbers = rows("SELECT n.*, c.challan_date, p.name AS party_name FROM challan_numbers n LEFT JOIN sales_challans c ON c.id = n.used_by LEFT JOIN parties p ON p.id = c.party_id $clause ORDER BY n.fy DESC, n.seq LIMIT " . PAGE_SIZE . ' OFFSET ' . page_offset($page), $params);
    render('admin_challan_numbers', compact('summary', 'numbers', 'total', 'page', 'fy', 'state', 'query'));
}

/** Parties bulk upload: the checked rows wait in the session until they are saved. */
function check_party_file(): void
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
    $existing = [];
    foreach (rows('SELECT name, source_name FROM parties') as $party) {
        $existing[mb_strtolower(clean_name((string) $party['name']))] = true;
        $existing[mb_strtolower(clean_name((string) $party['source_name']))] = true;
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
        } elseif (isset($existing[$key])) {
            [$outcome, $reason] = ['skipped', 'Already exists'];
        } elseif (isset($seen[$key])) {
            [$outcome, $reason] = ['skipped', 'Repeated in this file'];
        }
        $seen[$key] = true;
        $checked[] = ['row' => $row['number'], 'name' => $name, 'area' => $area, 'outcome' => $outcome, 'reason' => $reason];
    }
    $_SESSION['party_bulk'] = $checked;
}

function save_party_file(array $admin): int
{
    $checked = $_SESSION['party_bulk'] ?? [];
    $ready = array_filter($checked, fn ($row) => $row['outcome'] === 'ready');
    tx(function () use ($ready, $admin) {
        foreach ($ready as $row) {
            upsert_party($row['name'], 'SALE', $row['area'] === '' ? null : upsert_area($row['area']));
        }
        audit($admin['id'], 'PARTY_BULK_UPLOAD', 'party', null, null, ['created' => count($ready)]);
    });
    unset($_SESSION['party_bulk']);
    return count($ready);
}

function route_masters_additions(bool $get, bool $post, string $path): void
{
    if ($get && $path === '/admin/parties/template') {
        require_user(ADMINS);
        send_xlsx('parties-template.xlsx', ['Parties' => [['Party Name', 'Area']]]);
    }
    if ($path === '/admin/parties/bulk') {
        $admin = require_user(ADMINS);
        $error = null;
        if ($post) {
            require_csrf();
            try {
                if (($_POST['action'] ?? '') === 'save') {
                    $created = save_party_file($admin);
                    flash(number_format($created) . ' parties added.');
                    redirect('/admin/parties');
                }
                check_party_file();
            } catch (UserError $failure) {
                unset($_SESSION['party_bulk']);
                $error = $failure->getMessage();
            }
        } else {
            unset($_SESSION['party_bulk']);
        }
        render('admin_parties_bulk', ['rows' => $_SESSION['party_bulk'] ?? null, 'error' => $error]);
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
