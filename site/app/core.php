<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', '0');

const MIN_PASSWORD_LENGTH = 8;
const SCHEMA_VERSION = '7';

final class UserError extends RuntimeException {}

function config(): array
{
    static $config = null;
    if ($config === null) {
        $path = getenv('PM_CONFIG') ?: __DIR__ . '/config.local.php';
        if (!is_file($path)) {
            http_response_code(503);
            exit('The app is not configured yet.');
        }
        $config = require $path;
    }
    return $config;
}

function storage_dir(): string
{
    return rtrim(config()['storage_dir'] ?? dirname(__DIR__) . '/storage', '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config();
        $port = $c['db_port'] ?? 3306;
        $pdo = new PDO("mysql:host={$c['db_host']};port={$port};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+05:30'");
        ensure_schema($pdo);
    }
    return $pdo;
}

function add_missing_user_columns(PDO $pdo): void
{
    $columns = [
        'address' => 'VARCHAR(255) NULL',
        'photo_key' => 'VARCHAR(500) NULL',
        'pan_key' => 'VARCHAR(500) NULL',
        'aadhaar_front_key' => 'VARCHAR(500) NULL',
        'aadhaar_back_key' => 'VARCHAR(500) NULL',
        'designation' => 'VARCHAR(100) NULL',
        'salary' => 'DECIMAL(12,2) NULL',
        'joining_date' => 'DATE NULL',
    ];
    $existing = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $name $definition");
        }
    }
}

/** Moves history out of the old JSON columns into audit_changes and import_errors, then drops them. */
function move_json_to_columns(PDO $pdo): void
{
    $auditColumns = $pdo->query('SHOW COLUMNS FROM audit_log')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('before_data', $auditColumns, true)) {
        $insert = $pdo->prepare('INSERT INTO audit_changes (audit_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)');
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM audit_changes');
        foreach ($pdo->query('SELECT id, before_data, after_data FROM audit_log WHERE before_data IS NOT NULL OR after_data IS NOT NULL')->fetchAll() as $row) {
            $before = json_decode((string) $row['before_data'], true) ?: [];
            $after = json_decode((string) $row['after_data'], true) ?: [];
            foreach (array_keys($before + $after) as $field) {
                $insert->execute([$row['id'], mb_substr((string) $field, 0, 60), audit_text($before[$field] ?? null), audit_text($after[$field] ?? null)]);
            }
        }
        $pdo->commit();
        $pdo->exec('ALTER TABLE audit_log DROP COLUMN before_data, DROP COLUMN after_data');
    }
    $batchColumns = $pdo->query('SHOW COLUMNS FROM import_batches')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('error_report', $batchColumns, true)) {
        $insert = $pdo->prepare('INSERT INTO import_errors (import_batch_id, row_no, outcome, reason) VALUES (?, ?, ?, ?)');
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM import_errors');
        foreach ($pdo->query('SELECT id, error_report FROM import_batches WHERE error_report IS NOT NULL')->fetchAll() as $row) {
            foreach (json_decode((string) $row['error_report'], true) ?: [] as $problem) {
                $reason = (string) ($problem['reason'] ?? '');
                $insert->execute([$row['id'], (int) ($problem['row'] ?? 0), stripos($reason, 'exists') !== false || stripos($reason, 'repeated') !== false ? 'SKIPPED' : 'FAILED', mb_substr($reason, 0, 255)]);
            }
        }
        $pdo->commit();
        $pdo->exec('ALTER TABLE import_batches DROP COLUMN error_report');
    }
}

/** Joins parties whose saved names differ only by extra spaces. */
function merge_spaced_parties(PDO $pdo): void
{
    $find = $pdo->prepare('SELECT id FROM parties WHERE source_name = ? AND id <> ?');
    $move = $pdo->prepare('UPDATE documents SET party_id = ? WHERE party_id = ?');
    $remove = $pdo->prepare('DELETE FROM parties WHERE id = ?');
    $rename = $pdo->prepare('UPDATE parties SET source_name = ? WHERE id = ?');
    foreach ($pdo->query("SELECT id, source_name FROM parties WHERE source_name LIKE '%  %'")->fetchAll() as $party) {
        $clean = trim((string) preg_replace('/\s+/', ' ', (string) $party['source_name']));
        $find->execute([$clean, $party['id']]);
        $target = $find->fetchColumn();
        if ($target) {
            $move->execute([$target, $party['id']]);
            $remove->execute([$party['id']]);
        } else {
            $rename->execute([$clean, $party['id']]);
        }
    }
}

/** Adds the Expiry section to databases made before it existed. Safe to run again. */
/** Lets a Sales Challan keep a typed party name until it is matched to Party Master. */
function add_sales_challan_party_text(PDO $pdo): void
{
    if (!$pdo->query("SHOW TABLES LIKE 'sales_challans'")->fetchColumn()) {
        return;
    }
    $existing = $pdo->query('SHOW COLUMNS FROM sales_challans')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('party_text', $existing, true)) {
        $pdo->exec('ALTER TABLE sales_challans ADD COLUMN party_text VARCHAR(200) NULL AFTER party_id');
    }
    $pdo->exec('ALTER TABLE sales_challans MODIFY party_id BIGINT NULL');
}

function add_expiry_section(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE import_batches MODIFY doc_type ENUM('SALE','CF_SALE','PURCHASE','EXPIRY') NOT NULL");
    $pdo->exec("ALTER TABLE documents MODIFY doc_type ENUM('SALE','CF_SALE','PURCHASE','EXPIRY') NOT NULL");
    $pdo->exec("ALTER TABLE attachments MODIFY kind ENUM('RECEIPT_COPY','INVOICE_PHOTO','CHALLAN_PHOTO','GOODS_PHOTO','EXPIRY_PHOTO') NOT NULL");
    $columns = ['reference_no' => 'VARCHAR(50) NULL', 'receipt_date' => 'DATE NULL', 'received_by' => 'VARCHAR(120) NULL'];
    $existing = $pdo->query('SHOW COLUMNS FROM documents')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec("ALTER TABLE documents ADD COLUMN $name $definition");
        }
    }
}

function ensure_schema(PDO $pdo): void
{
    $marker = storage_dir() . '/.schema-' . SCHEMA_VERSION;
    if (!is_file($marker)) {
        foreach (require __DIR__ . '/schema.php' as $statement) {
            $pdo->exec($statement);
        }
        add_missing_user_columns($pdo);
        add_expiry_section($pdo);
        add_sales_challan_party_text($pdo);
        move_json_to_columns($pdo);
        merge_spaced_parties($pdo);
        if (!is_dir(storage_dir())) {
            mkdir(storage_dir(), 0755, true);
        }
        file_put_contents($marker, date('c'));
    }
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $c = config();
    if (empty($c['admin_phone']) || strlen((string) ($c['admin_password'] ?? '')) < MIN_PASSWORD_LENGTH) {
        return;
    }
    $statement = $pdo->prepare("INSERT INTO users (full_name, phone, password_hash, role) VALUES (?, ?, ?, 'ADMIN')");
    $statement->execute([$c['admin_name'] ?? 'Admin', $c['admin_phone'], password_hash($c['admin_password'], PASSWORD_DEFAULT)]);
}

function run(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute(array_values($params));
    return $statement;
}

function rows(string $sql, array $params = []): array
{
    return run($sql, $params)->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    $result = run($sql, $params)->fetch();
    return $result === false ? null : $result;
}

function value(string $sql, array $params = []): mixed
{
    return run($sql, $params)->fetchColumn();
}

function tx(callable $work): mixed
{
    db()->beginTransaction();
    try {
        $result = $work();
        db()->commit();
        return $result;
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
}

function audit_text(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    return is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
}

function audit(?int $userId, string $action, string $type, ?int $id, ?array $before = null, ?array $after = null): void
{
    run('INSERT INTO audit_log (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)', [$userId, $action, $type, $id]);
    $auditId = (int) db()->lastInsertId();
    foreach (array_keys(($before ?? []) + ($after ?? [])) as $field) {
        run('INSERT INTO audit_changes (audit_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)', [$auditId, mb_substr((string) $field, 0, 60), audit_text($before[$field] ?? null), audit_text($after[$field] ?? null)]);
    }
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function start_session(): void
{
    session_name('pm_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '43200');
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string
{
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = row('SELECT id, full_name, phone, role FROM users WHERE id = ? AND is_active = 1', [(int) $_SESSION['uid']]);
            if ($user) {
                $user['id'] = (int) $user['id'];
            }
        }
    }
    return $user;
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function flash(string $message, bool $ok = true): void
{
    $_SESSION['flash'] = ['ok' => $ok, 'message' => $message];
}

function require_user(array $roles = []): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        redirect('/');
    }
    return $user;
}

function api_user(array $roles = []): array
{
    $user = current_user();
    if (!$user) {
        json_response(['error' => 'Please sign in again.'], 401);
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        json_response(['error' => 'You do not have access to this.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !csrf_valid()) {
        json_response(['error' => 'Your session expired. Reload the page.'], 403);
    }
    return $user;
}

function require_csrf(): void
{
    if (!csrf_valid()) {
        http_response_code(403);
        exit('Your session expired. Go back and reload the page.');
    }
}

function render(string $view, array $data = []): never
{
    extract($data, EXTR_SKIP);
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = ob_get_clean();
    $user = current_user();
    $title = $title ?? 'Pharmacy Management';
    require __DIR__ . '/views/layout.php';
    exit;
}

function not_found(): never
{
    http_response_code(404);
    exit('Page not found.');
}

function display_date(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    [$year, $month, $day] = explode('-', substr($iso, 0, 10));
    return "$day/$month/$year";
}

function money(float|string $amount): string
{
    $number = (float) $amount;
    $whole = (string) (int) floor($number);
    $fraction = round($number - floor($number), 2);
    $last = substr($whole, -3);
    $rest = substr($whole, 0, -3);
    if ($rest !== '' && $rest !== false) {
        $last = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last;
    }
    return "\u{20B9}" . $last . ($fraction > 0 ? rtrim(substr(number_format($fraction, 2, '.', ''), 1), '0') : '');
}

function like_pattern(string $text): string
{
    return '%' . addcslashes($text, '\\%_') . '%';
}

function requested_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

/** Keeps a requested page within the pages that exist. */
function clamp_page(int $page, int $total): int
{
    return min($page, max(1, (int) ceil($total / PAGE_SIZE)));
}

function page_offset(int $page): int
{
    return ($page - 1) * PAGE_SIZE;
}

/** Previous and Next controls; $link receives a page number and returns its address. */
function pager(int $page, int $total, callable $link): string
{
    if ($total <= PAGE_SIZE) {
        return '';
    }
    $last = (int) ceil($total / PAGE_SIZE);
    $from = page_offset($page) + 1;
    $to = min($total, $page * PAGE_SIZE);
    $html = '<nav class="row between pager" aria-label="Pages"><span class="muted">Showing ' . $from . '-' . $to . ' of ' . number_format($total) . ' &middot; Page ' . $page . ' of ' . $last . '</span><span class="row">';
    if ($page > 1) {
        $html .= '<a class="btn" href="' . e($link($page - 1)) . '">Previous</a>';
    }
    if ($page < $last) {
        $html .= '<a class="btn" href="' . e($link($page + 1)) . '">Next</a>';
    }
    return $html . '</span></nav>';
}
