<?php
declare(strict_types=1);

const PURCHASE_IN_PHOTOS = 5;
const PURCHASE_IN_MAX_BILLS = 52;
const PURCHASE_IN_LAST_SL = 'purchase_in_last_sl';

/** 1 becomes A, 26 becomes Z, 27 becomes AA. */
function bill_letter(int $position): string
{
    $letter = '';
    while ($position > 0) {
        $position--;
        $letter = chr(65 + $position % 26) . $letter;
        $position = intdiv($position, 26);
    }
    return $letter;
}

/** Sl. No. 4204 with 2 bills gives 4204A,4204B. */
function party_numbers(int $slNo, int $bills): string
{
    $numbers = [];
    for ($position = 1; $position <= $bills; $position++) {
        $numbers[] = $slNo . bill_letter($position);
    }
    return implode(',', $numbers);
}

function purchase_in_last_sl(): int
{
    return (int) value('SELECT setting_value FROM app_settings WHERE setting_key = ?', [PURCHASE_IN_LAST_SL]);
}

function time_text(?string $value): string
{
    return $value ? date('d/m/Y h:i A', strtotime($value)) : '';
}

function save_purchase_in_photos(): array
{
    $saved = [];
    try {
        for ($slot = 1; $slot <= PURCHASE_IN_PHOTOS; $slot++) {
            $file = null;
            foreach (["photo$slot", "photo{$slot}_cam"] as $field) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES[$field];
                    break;
                }
            }
            if (!$file) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_IMAGE_BYTES) {
                throw new UserError("Photo $slot must be under 10 MB.");
            }
            $data = (string) file_get_contents($file['tmp_name']);
            $mime = detect_image($data);
            if (!$mime) {
                throw new UserError("Photo $slot must be a JPG, PNG or WebP photo.");
            }
            $saved[] = save_upload($data, $mime);
        }
    } catch (Throwable $error) {
        remove_employee_files($saved);
        throw $error;
    }
    return $saved;
}

/** Saves one entry. The Sl. No. is taken inside the lock, so two people can never get the same number. */
function create_purchase_in(array $user): array
{
    $party = clean_name((string) ($_POST['party_name'] ?? ''));
    $packets = parse_whole((string) ($_POST['packets'] ?? ''));
    $bills = parse_whole((string) ($_POST['bill_count'] ?? ''));
    $amount = parse_amount((string) ($_POST['bill_amount'] ?? ''));
    $reference = mb_substr(trim((string) ($_POST['reference_no'] ?? '')), 0, 100);
    if ($party === '') {
        throw new UserError('Enter the Party Name.');
    }
    if ($packets === null) {
        throw new UserError('No. of Packets must be a whole number.');
    }
    if ($bills === null || $bills < 1 || $bills > PURCHASE_IN_MAX_BILLS) {
        throw new UserError('Number Of Bill must be between 1 and ' . PURCHASE_IN_MAX_BILLS . '.');
    }
    if ($amount === null) {
        throw new UserError('Bill Amount must be a number.');
    }
    $photos = save_purchase_in_photos();
    try {
        return tx(function () use ($user, $party, $packets, $bills, $amount, $reference, $photos) {
            $slNo = (int) value('SELECT setting_value FROM app_settings WHERE setting_key = ? FOR UPDATE', [PURCHASE_IN_LAST_SL]) + 1;
            $partyNo = party_numbers($slNo, $bills);
            $now = date('Y-m-d H:i:s');
            $known = row('SELECT id, name FROM parties WHERE name = ? AND is_active = 1 AND is_supplier = 1 LIMIT 1', [$party]);
            $partyId = $known ? (int) $known['id'] : null;
            run(
                'INSERT INTO purchase_in (entry_date, entry_time, sl_no, party_id, party_name, packets, bill_count, reference_no, bill_amount, entry_by, photo_1, photo_2, photo_3, photo_4, photo_5, party_no)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [substr($now, 0, 10), $now, $slNo, $partyId, mb_substr($party, 0, 200), $packets, $bills, $reference === '' ? null : $reference, $amount, $user['id'], $photos[0] ?? null, $photos[1] ?? null, $photos[2] ?? null, $photos[3] ?? null, $photos[4] ?? null, $partyNo]
            );
            $id = (int) db()->lastInsertId();
            run('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?', [(string) $slNo, PURCHASE_IN_LAST_SL]);
            audit($user['id'], 'PURCHASE_IN_ADD', 'purchase_in', $id, null, ['sl_no' => $slNo, 'party_no' => $partyNo]);
            return ['id' => $id, 'sl_no' => $slNo, 'party_no' => $partyNo];
        });
    } catch (Throwable $error) {
        remove_employee_files($photos);
        throw $error;
    }
}

function purchase_in_filter(): array
{
    $dateOnly = function (string $name) {
        $text = trim((string) ($_GET[$name] ?? ''));
        return $text === '' ? '' : (parse_date($text) ?? '');
    };
    return [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'from' => $dateOnly('from'),
        'to' => $dateOnly('to'),
        'group' => ($_GET['group'] ?? '') === 'supplier' ? 'supplier' : 'date',
    ];
}

function list_purchase_in(bool|string $pendingOnly, array $filter): array
{
    $where = [];
    $params = [];
    if ($pendingOnly === 'NO_PARTY') {
        $where[] = 'e.party_id IS NULL';
    } elseif ($pendingOnly) {
        $where[] = "e.status = 'PENDING'";
    }
    if ($filter['q'] !== '') {
        $pattern = like_pattern($filter['q']);
        $where[] = '(e.party_name LIKE ? OR e.party_no LIKE ? OR e.reference_no LIKE ? OR e.challan_no LIKE ? OR CAST(e.sl_no AS CHAR) LIKE ?)';
        array_push($params, $pattern, $pattern, $pattern, $pattern, $pattern);
    }
    if ($filter['from'] !== '') {
        $where[] = 'e.entry_date >= ?';
        $params[] = $filter['from'];
    }
    if ($filter['to'] !== '') {
        $where[] = 'e.entry_date <= ?';
        $params[] = $filter['to'];
    }
    $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $supplier = 'COALESCE(p.name, e.party_name)';
    $bySupplier = $filter['group'] === 'supplier';
    $key = $bySupplier ? $supplier : 'e.entry_date';
    $order = $bySupplier ? "$supplier, e.sl_no DESC" : 'e.entry_date DESC, e.sl_no DESC';
    $from = 'FROM purchase_in e LEFT JOIN parties p ON p.id = e.party_id';
    $total = (int) value("SELECT COUNT(*) $from $clause", $params);
    $page = clamp_page(requested_page(), $total);
    $rows = rows("SELECT e.*, u.full_name AS entry_name, $supplier AS supplier_name $from JOIN users u ON u.id = e.entry_by $clause ORDER BY $order LIMIT " . PAGE_SIZE . ' OFFSET ' . page_offset($page), $params);
    $groups = [];
    foreach (rows("SELECT $key AS group_key, COUNT(*) AS entries $from $clause GROUP BY group_key ORDER BY group_key" . ($bySupplier ? '' : ' DESC'), $params) as $group) {
        $groups[(string) $group['group_key']] = (int) $group['entries'];
    }
    return [$rows, $total, $page, $groups];
}

function serve_purchase_in_photo(int $id, int $slot): never
{
    api_user();
    $key = value("SELECT photo_$slot FROM purchase_in WHERE id = ?", [$id]);
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

/** Matches a typed party name to Party Master. */
function set_purchase_in_party(int $id, array $user): void
{
    $party = row('SELECT id, name FROM parties WHERE name = ? AND is_active = 1 AND is_supplier = 1 LIMIT 1', [clean_name((string) ($_POST['party_name'] ?? ''))]);
    if (!$party) {
        throw new UserError('Select the Party Name from the list.');
    }
    tx(function () use ($id, $party, $user) {
        $before = row('SELECT party_name FROM purchase_in WHERE id = ? AND party_id IS NULL FOR UPDATE', [$id]);
        if (!$before) {
            throw new UserError('This entry already has a party from Party Master.');
        }
        run('UPDATE purchase_in SET party_id = ?, party_name = ? WHERE id = ?', [$party['id'], mb_substr($party['name'], 0, 200), $id]);
        audit($user['id'], 'PURCHASE_IN_PARTY', 'purchase_in', $id, ['party_name' => $before['party_name']], ['party' => $party['name']]);
    });
}

function handle_purchase_in_settings(array $user): void
{
    require_csrf();
    $text = trim((string) ($_POST['last_sl'] ?? ''));
    $highest = (int) value('SELECT COALESCE(MAX(sl_no), 0) FROM purchase_in');
    if (!preg_match('/^\d{1,9}$/', $text)) {
        flash('Enter the last Sl. No. as a whole number.', false);
    } elseif ((int) $text < $highest) {
        flash("Last Sl. No. cannot be lower than $highest, the highest number already saved.", false);
    } else {
        $before = purchase_in_last_sl();
        run('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?', [(string) (int) $text, PURCHASE_IN_LAST_SL]);
        audit($user['id'], 'PURCHASE_IN_SETTINGS', 'setting', null, ['last_sl' => $before], ['last_sl' => (int) $text]);
        flash('Saved. The next Sl. No. is ' . ((int) $text + 1) . '.');
    }
    redirect('/purchase-in/settings');
}

function handle_challan(int $id, array $user): void
{
    require_csrf();
    $challan = mb_substr(trim((string) ($_POST['challan_no'] ?? '')), 0, 50);
    if ($challan === '') {
        flash('Enter the Challan No.', false);
        redirect("/purchase-in/$id?update=1");
    }
    $done = tx(function () use ($id, $challan, $user) {
        $entry = row('SELECT id, status FROM purchase_in WHERE id = ? FOR UPDATE', [$id]);
        if (!$entry) {
            not_found();
        }
        if ($entry['status'] !== 'PENDING') {
            return false;
        }
        run("UPDATE purchase_in SET challan_no = ?, challan_time = ?, status = 'DONE' WHERE id = ?", [$challan, date('Y-m-d H:i:s'), $id]);
        audit($user['id'], 'PURCHASE_IN_CHALLAN', 'purchase_in', $id, null, ['challan_no' => $challan]);
        return true;
    });
    flash($done ? 'Challan saved.' : 'This entry already has a challan.', $done);
    redirect('/purchase-in/master');
}

/** Handles every Purchase In address, and returns for any other address. */
function route_purchase_in(bool $get, bool $post, string $path): void
{
    $m = [];
    if ($get && preg_match('#^/api/purchase-in/(\d+)/([1-5])$#', $path, $m)) {
        serve_purchase_in_photo((int) $m[1], (int) $m[2]);
    }
    if ($path !== '/purchase-in' && !str_starts_with($path, '/purchase-in/')) {
        return;
    }
    $user = require_user();
    if ($get && $path === '/purchase-in') {
        redirect('/purchase-in/add');
    }
    if ($path === '/purchase-in/add') {
        $error = null;
        if ($post) {
            require_csrf();
            try {
                $saved = create_purchase_in($user);
                flash("Saved. Sl. No. {$saved['sl_no']}, Party No {$saved['party_no']}.");
                redirect('/purchase-in/master');
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        }
        $parties = array_column(rows('SELECT name FROM parties WHERE is_active = 1 AND is_supplier = 1 ORDER BY name LIMIT 5000'), 'name');
        render('purchase_in_add', ['nextSl' => purchase_in_last_sl() + 1, 'parties' => $parties, 'error' => $error, 'old' => $post ? $_POST : []]);
    }
    if ($get && ($path === '/purchase-in/pending' || $path === '/purchase-in/master')) {
        $pending = $path === '/purchase-in/pending';
        $filter = purchase_in_filter();
        [$entries, $total, $page, $groups] = list_purchase_in($pending, $filter);
        render('purchase_in_list', compact('entries', 'total', 'page', 'filter', 'groups', 'path'));
    }
    if ($get && $path === '/purchase-in/pending-party') {
        $filter = purchase_in_filter();
        [$entries, $total, $page] = list_purchase_in('NO_PARTY', $filter);
        render('purchase_in_pending_party', compact('entries', 'total', 'page', 'filter'));
    }
    if (preg_match('#^/purchase-in/(\d+)/party$#', $path, $m)) {
        $id = (int) $m[1];
        $entry = row('SELECT * FROM purchase_in WHERE id = ?', [$id]) ?? not_found();
        $error = null;
        if ($post) {
            require_csrf();
            try {
                set_purchase_in_party($id, $user);
                flash("Party updated for Sl. No. {$entry['sl_no']}.");
                redirect('/purchase-in/pending-party');
            } catch (UserError $failure) {
                $error = $failure->getMessage();
            }
        }
        if ($entry['party_id']) {
            redirect('/purchase-in/pending-party');
        }
        render('purchase_in_party', compact('entry', 'error'));
    }
    if ($path === '/purchase-in/settings') {
        $user = require_user(MANAGERS);
        if ($post) {
            handle_purchase_in_settings($user);
        }
        render('purchase_in_settings', ['lastSl' => purchase_in_last_sl()]);
    }
    if ($post && preg_match('#^/purchase-in/(\d+)/challan$#', $path, $m)) {
        handle_challan((int) $m[1], $user);
    }
    if ($get && preg_match('#^/purchase-in/(\d+)$#', $path, $m)) {
        $entry = row('SELECT e.*, u.full_name AS entry_name FROM purchase_in e JOIN users u ON u.id = e.entry_by WHERE e.id = ?', [(int) $m[1]]) ?? not_found();
        render('purchase_in_entry', compact('entry'));
    }
    not_found();
}
