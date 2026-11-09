<?php
declare(strict_types=1);

const MANAGERS = ['ADMIN', 'SUPERVISOR'];
const ADMINS = ['ADMIN'];
const ROLES = ['ADMIN', 'SUPERVISOR', 'UPLOADER'];
const LOGIN_LIMIT = 5;

function post_text(string $name, int $limit): string
{
    return mb_substr(trim((string) ($_POST[$name] ?? '')), 0, $limit);
}

function optional_text(string $name, int $limit): ?string
{
    $text = post_text($name, $limit);
    return $text === '' ? null : $text;
}

function handle_login(): never
{
    if (current_user()) {
        redirect('/');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        render('login', ['error' => null]);
    }
    require_csrf();
    $phone = post_text('phone', 15);
    $password = (string) ($_POST['password'] ?? '');
    $attempt = row('SELECT failures, locked_until > NOW() AS locked FROM login_attempts WHERE phone = ?', [$phone]);
    if ($attempt && (int) $attempt['failures'] >= LOGIN_LIMIT && (int) $attempt['locked']) {
        render('login', ['error' => 'Too many failed attempts. Try again in 15 minutes.']);
    }
    $user = row('SELECT id, password_hash FROM users WHERE phone = ? AND is_active = 1', [$phone]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        if ($phone !== '') {
            run(
                'INSERT INTO login_attempts (phone, failures, locked_until) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
                 ON DUPLICATE KEY UPDATE failures = IF(locked_until > NOW(), failures + 1, 1), locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)',
                [$phone]
            );
        }
        render('login', ['error' => 'Phone number or password is incorrect.']);
    }
    run('DELETE FROM login_attempts WHERE phone = ?', [$phone]);
    run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    redirect('/');
}

function show_register(array $register): never
{
    $user = require_user();
    $manage = $user['role'] !== 'UPLOADER';
    $statuses = $manage ? ['ALL', 'PENDING', 'UPLOADED', 'MINE', 'DELETED'] : ['ALL', 'PENDING', 'UPLOADED', 'MINE'];
    $status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : 'ALL';
    $dateOnly = fn ($name) => parse_date(trim((string) ($_GET[$name] ?? ''))) ?? '';
    $filter = [
        'type' => $register['type'], 'status' => $status, 'q' => trim((string) ($_GET['q'] ?? '')), 'from' => $dateOnly('from'), 'to' => $dateOnly('to'),
        'area' => (int) ($_GET['area'] ?? 0), 'page' => max(1, (int) ($_GET['page'] ?? 1)), 'user_id' => $user['id'],
        'group' => in_array($_GET['group'] ?? '', group_options($register), true) ? $_GET['group'] : 'date',
    ];
    [$documents, $total, $filter['page'], $groupTotals] = list_documents($filter);
    $photos = [];
    foreach (attachments_for(array_map(fn ($d) => (int) $d['id'], $documents)) as $item) {
        $photos["{$item['document_id']}:{$item['kind']}:{$item['sort_order']}"] = (int) $item['id'];
    }
    render('register', compact('register', 'manage', 'statuses', 'status', 'filter', 'documents', 'total', 'photos', 'groupTotals') + [
        'counts' => register_counts($register['type']),
        'areas' => rows('SELECT id, name FROM areas WHERE is_active = 1 ORDER BY name'),
    ]);
}

function show_document(array $register, int $id): never
{
    $user = require_user();
    $document = get_document($id);
    if (!$document || $document['doc_type'] !== $register['type']) {
        not_found();
    }
    $photos = [];
    foreach (attachments_for([$id]) as $item) {
        $photos[$item['kind']][] = (int) $item['id'];
    }
    $manage = $user['role'] !== 'UPLOADER';
    render('document', compact('register', 'document', 'photos', 'manage', 'user') + [
        'areas' => $manage ? rows('SELECT id, name FROM areas WHERE is_active = 1 ORDER BY name') : [],
    ]);
}

function document_slug(int $id): string
{
    $type = value('SELECT doc_type FROM documents WHERE id = ?', [$id]);
    if (!$type) {
        not_found();
    }
    return registers()[$type]['slug'];
}

function handle_correction(int $id): never
{
    $user = require_user(MANAGERS);
    require_csrf();
    $slug = document_slug($id);
    $back = "/r/$slug/$id";
    $date = parse_date(post_text('date', 10));
    $amount = parse_amount((string) ($_POST['amount'] ?? ''));
    $party = post_text('party', 200);
    $itemsText = post_text('items', 20);
    $items = $itemsText === '' ? null : parse_whole($itemsText);
    $problem = !$date ? 'Enter a valid date as dd/mm/yyyy.' : ($amount === null ? 'Enter a valid amount.' : ($party === '' ? 'Party name is required.' : (($itemsText !== '' && $items === null) ? 'No of Item must be a whole number.' : null)));
    if ($problem) {
        flash($problem, false);
        redirect($back);
    }
    $area = (int) ($_POST['area'] ?? 0);
    try {
        correct_document($id, compact('date', 'party', 'amount', 'items') + ['receipt' => optional_text('receipt', 50), 'mr' => optional_text('mr', 120), 'area' => $area > 0 ? $area : null, 'address' => optional_text('address', 255)], $user['id']);
        flash('Correction saved.');
    } catch (UserError $error) {
        flash($error->getMessage(), false);
    }
    redirect($back);
}

function handle_upload_photo(int $id): never
{
    $user = api_user();
    $kind = (string) ($_POST['kind'] ?? '');
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['error' => 'Choose a photo first.'], 400);
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        json_response(['error' => 'Photo must be under 10 MB.'], 400);
    }
    $data = (string) file_get_contents($file['tmp_name']);
    $mime = detect_image($data);
    if (!$mime) {
        json_response(['error' => 'Only JPG, PNG or WebP photos are allowed.'], 400);
    }
    $key = save_upload($data, $mime);
    try {
        json_response(add_attachment($id, $kind, $key, (string) $file['name'], $mime, strlen($data), $user['id']));
    } catch (UserError $error) {
        @unlink(storage_dir() . '/uploads/' . $key);
        json_response(['error' => $error->getMessage()], 400);
    }
}

function serve_photo(int $id): never
{
    api_user();
    $file = row('SELECT file_key, mime_type FROM attachments WHERE id = ? AND deleted_at IS NULL', [$id]);
    $path = $file ? storage_dir() . '/uploads/' . $file['file_key'] : '';
    if (!$file || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=86400');
    readfile($path);
    exit;
}

function handle_bulk(array $register): never
{
    $user = api_user(MANAGERS);
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['error' => 'Choose a file first.'], 400);
    }
    if ($file['size'] > MAX_BULK_BYTES) {
        json_response(['error' => 'File must be under 10 MB.'], 400);
    }
    try {
        if (($_POST['mode'] ?? '') === 'confirm') {
            json_response(commit_upload($register, $file, $user['id']));
        }
        json_response(['rows' => public_rows(check_sheet($register, read_upload($file)))]);
    } catch (UserError $error) {
        json_response(['error' => $error->getMessage()], 400);
    }
}

function export_reports(): never
{
    api_user(MANAGERS);
    $register = report_register();
    send_xlsx($register['slug'] . '-reports.xlsx', [
        'Pending by date' => array_merge([['Date', 'Count']], array_map(fn ($r) => [$r['date'], $r['count']], pending_by_date($register))),
        'Uploaded today' => [['Section', 'Count'], [$register['label'], uploaded_today($register)]],
        'Missing numbers' => array_merge([['Year', 'From', 'To', 'Count']], array_map(fn ($r) => [$r['label'], $r['from'], $r['to'], $r['count']], missing_numbers($register))),
    ]);
}

function is_duplicate(PDOException $error): bool
{
    return ($error->errorInfo[1] ?? 0) === 1062;
}

const EMPLOYEE_FILES = ['photo' => 'photo_key', 'pan' => 'pan_key', 'aadhaar_front' => 'aadhaar_front_key', 'aadhaar_back' => 'aadhaar_back_key'];
const EMPLOYEE_FILE_LABELS = ['photo' => 'Photo', 'pan' => 'PAN Card', 'aadhaar_front' => 'Aadhar Card Front', 'aadhaar_back' => 'Aadhar Card Back'];

function employee_missing(array $employee): string
{
    $missing = [];
    foreach (EMPLOYEE_FILES as $field => $column) {
        if (empty($employee[$column])) {
            $missing[] = EMPLOYEE_FILE_LABELS[$field];
        }
    }
    return $missing ? implode(', ', $missing) : 'None';
}

/** Reads and validates the employee form. Throws UserError with a message to show. */
function employee_from_post(): array
{
    $name = post_text('name', 120);
    $phone = post_text('phone', 15);
    $email = optional_text('email', 150);
    $salaryText = post_text('salary', 20);
    $salary = $salaryText === '' ? null : parse_amount($salaryText);
    $joining = post_text('joining_date', 10);
    $joiningDate = null;
    if ($joining !== '') {
        $joiningDate = parse_date($joining);
        if ($joiningDate === null) {
            throw new UserError('Enter the joining date as dd/mm/yyyy.');
        }
    }
    $role = (string) ($_POST['role'] ?? '');
    if ($name === '' || $phone === '') {
        throw new UserError('Employee name and phone number are required.');
    }
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new UserError('Enter a valid email address.');
    }
    if ($salaryText !== '' && $salary === null) {
        throw new UserError('Enter a valid salary.');
    }
    if (!in_array($role, ROLES, true)) {
        throw new UserError('Choose a role.');
    }
    return [
        'full_name' => $name, 'phone' => $phone, 'email' => $email, 'address' => optional_text('address', 255),
        'designation' => optional_text('designation', 100), 'salary' => $salary, 'joining_date' => $joiningDate, 'role' => $role,
        'is_active' => ($_POST['status'] ?? 'Active') === 'Inactive' ? 0 : 1,
    ];
}

function employee_passcode(bool $required): ?string
{
    $passcode = (string) ($_POST['password'] ?? '');
    if ($passcode === '' && !$required) {
        return null;
    }
    if (strlen($passcode) < MIN_PASSWORD_LENGTH) {
        throw new UserError('Passcode must have at least ' . MIN_PASSWORD_LENGTH . ' characters.');
    }
    return password_hash($passcode, PASSWORD_DEFAULT);
}

/** Stores uploaded employee documents and returns [column => file key]. */
function save_employee_files(): array
{
    $saved = [];
    try {
        foreach (EMPLOYEE_FILES as $field => $column) {
            $file = $_FILES[$field] ?? null;
            if (!is_array($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_IMAGE_BYTES) {
                throw new UserError(EMPLOYEE_FILE_LABELS[$field] . ' must be a photo under 10 MB.');
            }
            $data = (string) file_get_contents($file['tmp_name']);
            $mime = detect_image($data);
            if (!$mime) {
                throw new UserError(EMPLOYEE_FILE_LABELS[$field] . ' must be a JPG, PNG or WebP photo.');
            }
            $saved[$column] = save_upload($data, $mime);
        }
    } catch (Throwable $error) {
        remove_employee_files($saved);
        throw $error;
    }
    return $saved;
}

function remove_employee_files(array $keys): void
{
    foreach ($keys as $key) {
        if ($key) {
            @unlink(storage_dir() . '/uploads/' . $key);
        }
    }
}

function handle_create_user(): never
{
    $admin = require_user(ADMINS);
    require_csrf();
    $files = [];
    try {
        $employee = employee_from_post();
        $employee['password_hash'] = employee_passcode(true);
        $files = save_employee_files();
        $data = $employee + $files;
        $marks = implode(', ', array_fill(0, count($data), '?'));
        run('INSERT INTO users (' . implode(', ', array_keys($data)) . ") VALUES ($marks)", array_values($data));
        audit($admin['id'], 'USER_CREATE', 'user', (int) db()->lastInsertId(), null, ['name' => $employee['full_name'], 'role' => $employee['role']]);
        flash('User created.');
    } catch (UserError $error) {
        flash($error->getMessage(), false);
    } catch (PDOException $error) {
        remove_employee_files($files);
        if (!is_duplicate($error)) {
            throw $error;
        }
        flash('This phone number is already used.', false);
    }
    redirect('/admin/users');
}

function handle_update_user(int $id): never
{
    $admin = require_user(ADMINS);
    require_csrf();
    $back = "/admin/users/$id";
    $current = row('SELECT * FROM users WHERE id = ?', [$id]) ?? not_found();
    $files = [];
    try {
        $employee = employee_from_post();
        if ($id === $admin['id'] && ($employee['role'] !== 'ADMIN' || !$employee['is_active'])) {
            throw new UserError('You cannot remove your own Admin access.');
        }
        $passcode = employee_passcode(false);
        if ($passcode !== null) {
            $employee['password_hash'] = $passcode;
        }
        $files = save_employee_files();
        $data = $employee + $files;
        $assignments = implode(', ', array_map(fn ($column) => "$column = ?", array_keys($data)));
        run("UPDATE users SET $assignments WHERE id = ?", array_merge(array_values($data), [$id]));
        remove_employee_files(array_map(fn ($column) => $current[$column], array_combine(array_keys($files), array_keys($files))));
        audit($admin['id'], 'USER_UPDATE', 'user', $id, null, ['changed' => array_keys(array_diff_key($data, ['password_hash' => 1])), 'passcodeChanged' => $passcode !== null]);
        flash('Saved.');
    } catch (UserError $error) {
        flash($error->getMessage(), false);
    } catch (PDOException $error) {
        remove_employee_files($files);
        if (!is_duplicate($error)) {
            throw $error;
        }
        flash('This phone number is already used.', false);
    }
    redirect($back);
}

function serve_employee_file(int $id, string $field): never
{
    api_user(ADMINS);
    $key = value('SELECT ' . EMPLOYEE_FILES[$field] . ' FROM users WHERE id = ?', [$id]);
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

function handle_create_area(): never
{
    $admin = require_user(ADMINS);
    require_csrf();
    $name = post_text('name', 100);
    if ($name === '') {
        flash('Enter an area name.', false);
        redirect('/admin/areas');
    }
    try {
        run('INSERT INTO areas (name) VALUES (?)', [$name]);
        audit($admin['id'], 'AREA_CREATE', 'area', (int) db()->lastInsertId(), null, compact('name'));
        flash('Area added.');
    } catch (PDOException $error) {
        if (!is_duplicate($error)) {
            throw $error;
        }
        flash('This area already exists.', false);
    }
    redirect('/admin/areas');
}

function handle_update_party(): never
{
    $admin = require_user(ADMINS);
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $name = post_text('name', 120);
    $area = (int) ($_POST['area'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    if ($name === '') {
        flash('Enter a party name.', false);
    } else {
        run('UPDATE parties SET name = ?, area_id = ?, is_active = ? WHERE id = ?', [$name, $area > 0 ? $area : null, $active, $id]);
        audit($admin['id'], 'PARTY_UPDATE', 'party', $id, null, compact('name', 'area', 'active'));
        flash('Saved.');
    }
    redirect('/admin/parties');
}

function handle_request(string $method, string $path): never
{
    $get = $method === 'GET';
    $post = $method === 'POST';
    $m = [];
    if ($path === '/login') {
        handle_login();
    }
    if ($post && $path === '/logout') {
        require_csrf();
        $_SESSION = [];
        session_destroy();
        redirect('/login');
    }
    if ($get && $path === '/') {
        require_user();
        render('home');
    }
    if ($get && preg_match('#^/r/([a-z-]+)(?:/(\d+))?$#', $path, $m)) {
        $register = register_by_slug($m[1]) ?? not_found();
        isset($m[2]) ? show_document($register, (int) $m[2]) : show_register($register);
    }
    if ($post && preg_match('#^/documents/(\d+)/(correct|delete|restore|remarks|expiry)$#', $path, $m)) {
        $id = (int) $m[1];
        if ($m[2] === 'correct') {
            handle_correction($id);
        }
        if ($m[2] === 'remarks') {
            $user = api_user();
            try {
                save_remarks($id, (string) ($_POST['remarks'] ?? ''), $user['id']);
                json_response(['message' => 'Update saved.']);
            } catch (UserError $error) {
                json_response(['error' => $error->getMessage()], 400);
            }
        }
        if ($m[2] === 'expiry') {
            $user = api_user();
            try {
                save_expiry($id, $_POST, $user['id']);
                json_response(['message' => 'Update saved.']);
            } catch (UserError $error) {
                json_response(['error' => $error->getMessage()], 400);
            }
        }
        $user = require_user(MANAGERS);
        require_csrf();
        $slug = document_slug($id);
        try {
            if ($m[2] === 'delete') {
                delete_document($id, $user['id']);
                redirect("/r/$slug");
            }
            restore_document($id, $user['id']);
        } catch (UserError $error) {
            flash($error->getMessage(), false);
        }
        redirect("/r/$slug/$id");
    }
    if ($post && preg_match('#^/api/documents/(\d+)/attachments$#', $path, $m)) {
        handle_upload_photo((int) $m[1]);
    }
    if ($post && preg_match('#^/api/documents/(\d+)/attachments/(\d+)/delete$#', $path, $m)) {
        $user = api_user();
        try {
            remove_last_attachment((int) $m[1], (int) $m[2], $user['id']);
            json_response(['ok' => true]);
        } catch (UserError $error) {
            json_response(['error' => $error->getMessage()], 400);
        }
    }
    if ($get && preg_match('#^/api/files/(\d+)$#', $path, $m)) {
        serve_photo((int) $m[1]);
    }
    if ($get && preg_match('#^/api/templates/([a-z-]+)$#', $path, $m)) {
        api_user(MANAGERS);
        $register = register_by_slug($m[1]) ?? not_found();
        send_xlsx($register['slug'] . '-template.xlsx', [str_replace('&', 'and', $register['label']) => [array_map(fn ($c) => $c[0], $register['template'])]]);
    }
    if ($post && preg_match('#^/api/bulk/([a-z-]+)$#', $path, $m)) {
        $register = register_by_slug($m[1]);
        if (!$register) {
            json_response(['error' => 'Register not found.'], 404);
        }
        handle_bulk($register);
    }
    if ($get && $path === '/api/reports/export') {
        export_reports();
    }
    if ($get && $path === '/bulk') {
        require_user(MANAGERS);
        $initial = register_by_slug((string) ($_GET['type'] ?? '')) ? $_GET['type'] : 'sale';
        render('bulk', compact('initial'));
    }
    if ($get && $path === '/reports') {
        require_user(MANAGERS);
        $register = report_register();
        render('reports', ['register' => $register, 'pending' => pending_by_date($register), 'today' => uploaded_today($register), 'missing' => missing_numbers($register)]);
    }
    if ($path === '/admin/users') {
        if ($post) {
            handle_create_user();
        }
        require_user(ADMINS);
        $total = (int) value('SELECT COUNT(*) FROM users');
        $page = clamp_page(requested_page(), $total);
        render('admin_users', ['users' => rows('SELECT * FROM users ORDER BY full_name LIMIT ' . PAGE_SIZE . ' OFFSET ' . page_offset($page)), 'total' => $total, 'page' => $page]);
    }
    if (preg_match('#^/admin/users/(\d+)$#', $path, $m)) {
        if ($post) {
            handle_update_user((int) $m[1]);
        }
        require_user(ADMINS);
        render('admin_user_edit', ['employee' => row('SELECT * FROM users WHERE id = ?', [(int) $m[1]]) ?? not_found()]);
    }
    if ($get && preg_match('#^/api/users/(\d+)/(photo|pan|aadhaar_front|aadhaar_back)$#', $path, $m)) {
        serve_employee_file((int) $m[1], $m[2]);
    }
    if ($path === '/admin/areas') {
        if ($post) {
            handle_create_area();
        }
        require_user(ADMINS);
        $total = (int) value('SELECT COUNT(*) FROM areas');
        $page = clamp_page(requested_page(), $total);
        render('admin_areas', ['areas' => rows('SELECT id, name, is_active FROM areas ORDER BY name LIMIT ' . PAGE_SIZE . ' OFFSET ' . page_offset($page)), 'total' => $total, 'page' => $page]);
    }
    if ($post && $path === '/admin/areas/toggle') {
        require_user(ADMINS);
        require_csrf();
        run('UPDATE areas SET is_active = 1 - is_active WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        redirect('/admin/areas');
    }
    if ($get && $path === '/admin/parties') {
        require_user(ADMINS);
        $query = trim((string) ($_GET['q'] ?? ''));
        $pattern = like_pattern($query);
        $total = (int) value('SELECT COUNT(*) FROM parties WHERE name LIKE ? OR source_name LIKE ?', [$pattern, $pattern]);
        $page = clamp_page(requested_page(), $total);
        render('admin_parties', [
            'query' => $query,
            'total' => $total,
            'page' => $page,
            'parties' => rows('SELECT id, name, source_name, area_id, is_active FROM parties WHERE name LIKE ? OR source_name LIKE ? ORDER BY name LIMIT ' . PAGE_SIZE . ' OFFSET ' . page_offset($page), [$pattern, $pattern]),
            'areas' => rows('SELECT id, name FROM areas WHERE is_active = 1 ORDER BY name'),
        ]);
    }
    if ($post && $path === '/admin/parties/update') {
        handle_update_party();
    }
    not_found();
}
