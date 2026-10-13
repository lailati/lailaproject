<?php
/**
 * Shared helper functions
 *
 * Kept deliberately small: escaping, redirects, flash messages, CSRF tokens,
 * activity logging and a few formatting helpers used across every page.
 */

require_once __DIR__ . '/../config/config.php';

/* ---------------------------------------------------------------------------
   OUTPUT ESCAPING
   Every value echoed into HTML goes through e() so a lecturer can never inject
   markup through their own profile data.
   --------------------------------------------------------------------------- */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------------------------------------------------------------------
   URLS AND REDIRECTS
   url() takes a path relative to the app root, e.g. url('admin/dashboard.php')
   --------------------------------------------------------------------------- */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? BASE_URL . '/' : BASE_URL . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function current_page(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '');
}

/* ---------------------------------------------------------------------------
   FLASH MESSAGES
   Survive exactly one redirect, used for "Rekod berjaya disimpan" style notes.
   --------------------------------------------------------------------------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------------------------------------------------------------------------
   CSRF PROTECTION
   --------------------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/* ---------------------------------------------------------------------------
   ACTIVITY LOG
   Feeds the "Recent Activities" panel on the lecturer dashboard.
   Never let a logging failure break the user's action.
   --------------------------------------------------------------------------- */
function log_activity(
    int $userId,
    string $type,
    ?string $entity = null,
    ?int $entityId = null,
    string $description = ''
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, activity_type, entity, entity_id, description, ip_address)
             VALUES (:user_id, :type, :entity, :entity_id, :description, :ip)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':type'        => $type,
            ':entity'      => $entity,
            ':entity_id'   => $entityId,
            ':description' => mb_substr($description, 0, 255),
            ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Logging must never take the page down.
        error_log('activity_log failed: ' . $e->getMessage());
    }
}

/* ---------------------------------------------------------------------------
   FORMATTING HELPERS
   --------------------------------------------------------------------------- */
function initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    // Skip academic titles so "Dr. Aisyah Binti Ahmad" becomes "AA".
    $skip = ['dr', 'dr.', 'prof', 'prof.', 'madya', 'assoc', 'assoc.', 'ir', 'hj', 'hjh', 'ts'];
    $parts = preg_split('/\s+/', $name) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if (!in_array(mb_strtolower($part), $skip, true) && preg_match('/^\p{L}/u', $part)) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if (mb_strlen($letters) === 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : mb_strtoupper(mb_substr($name, 0, 1));
}

function format_date(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

/** "17 Aug 2026" style used by the Recent Activities list. */
function format_date_short(?string $date): string
{
    if (empty($date)) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('j M Y', $ts) : '—';
}

function relative_time(?string $datetime): string
{
    if (empty($datetime)) {
        return '—';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 60)          return 'Baru sahaja';
    if ($diff < 3600)        return floor($diff / 60) . ' minit lalu';
    if ($diff < 86400)       return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800)      return floor($diff / 86400) . ' hari lalu';
    return date('j M Y', $ts);
}

function bytes_human(?int $bytes): string
{
    if (!$bytes || $bytes <= 0) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $value = (float)$bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return round($value, $value < 10 && $i > 0 ? 1 : 0) . ' ' . $units[$i];
}

/* ---------------------------------------------------------------------------
   PROFILE COMPLETION
   Drives the "Profile Completion 85%" bar on the lecturer dashboard.
   --------------------------------------------------------------------------- */
function profile_completion(array $lecturer): int
{
    $checks = [
        !empty($lecturer['full_name']),
        !empty($lecturer['staff_no']),
        !empty($lecturer['email']),
        !empty($lecturer['department_id']),
        !empty($lecturer['position']),
        !empty($lecturer['phone']),
        !empty($lecturer['location']),
        !empty($lecturer['profile_photo']),
        !empty($lecturer['research_area']),
    ];
    $done = count(array_filter($checks));
    return (int)round($done / count($checks) * 100);
}

/**
 * Consistency guard: the seeded/demo rows are the only source of truth, so all
 * counts read from the database rather than being hard-coded in a view.
 */
function scalar(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/* ---------------------------------------------------------------------------
   RECORD TYPE DEFINITIONS + FIELD DEFINITIONS + UPLOADS
   Loaded here so any page that pulls in functions.php can use record_types(),
   record_cell(), record_fields(), store_upload() etc. without further requires.
   All three files only define functions, so including them at the end of this
   file is safe.
   --------------------------------------------------------------------------- */
require_once __DIR__ . '/record-types.php';
require_once __DIR__ . '/record-fields.php';
require_once __DIR__ . '/uploads.php';
