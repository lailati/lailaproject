<?php
/**
 * Authentication and role guard
 *
 * One login for both roles. Every protected page starts by requiring this file,
 * then calls require_login() or require_admin(). The role check lives here and
 * nowhere else, so a lecturer can never reach an admin page by typing its URL.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/db.php';

/* ---------------------------------------------------------------------------
   SESSION BOOTSTRAP
   --------------------------------------------------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    // "Remember me" simply keeps the session cookie alive; there is no extra
    // token table in the ERD, so we extend the lifetime instead.
    $lifetime = isset($_COOKIE['remember_me']) ? REMEMBER_DAYS * 86400 : 0;

    ini_set('session.gc_maxlifetime', (string)(REMEMBER_DAYS * 86400));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name(SESSION_NAME);
    session_start();
}

/* ---------------------------------------------------------------------------
   CURRENT USER
   --------------------------------------------------------------------------- */

/** The logged-in user row, enriched with the lecturer row when applicable. */
function current_user(): ?array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.*, d.department_name, d.department_code,
                l.lecturer_id, l.staff_no, l.full_name AS lecturer_name,
                l.full_name, l.profile_photo, l.`position`, l.phone, l.location,
                l.research_area
           FROM users u
           LEFT JOIN departments d ON d.department_id = u.department_id
           LEFT JOIN lecturers  l ON l.user_id       = u.user_id
          WHERE u.user_id = :id
            AND u.status  = \'active\'
          LIMIT 1'
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        // Account was deleted or deactivated while the session was open.
        logout_user();
        return null;
    }

    $cached = $row;
    return $cached;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function is_lecturer(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'lecturer';
}

/** Name to show in the top bar. */
function display_name(): string
{
    $u = current_user();
    if (!$u) {
        return 'Guest';
    }
    return $u['role'] === 'lecturer' && !empty($u['lecturer_name'])
        ? $u['lecturer_name']
        : $u['username'];
}

/** Where a user belongs after login. */
function home_for_role(): string
{
    return is_admin() ? 'admin/dashboard.php' : 'lecturer/dashboard.php';
}

/* ---------------------------------------------------------------------------
   GUARDS
   --------------------------------------------------------------------------- */

/** Any authenticated user. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('warning', 'Sila log masuk untuk meneruskan.');
        redirect('login.php');
    }

    // A password issued by the Pentadbir is one-off: the user must choose their
    // own before they can use anything else. Without this the one-off password
    // would stay valid forever and would have been shown on screen.
    $user = current_user();
    if (!empty($user['must_change_pw'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== 'change-password.php') {
            $_SESSION['force_password_change'] = true;
            redirect('auth/change-password.php');
        }
    }
}

/** Pentadbir only. A lecturer hitting an admin URL is sent home, not shown a 403. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Anda tiada kebenaran untuk mengakses halaman Pentadbir.');
        redirect(home_for_role());
    }
}

/** Pensyarah only (used by the record modules). */
function require_lecturer(): void
{
    require_login();
    if (!is_lecturer()) {
        flash('danger', 'Halaman ini hanya untuk akaun Pensyarah.');
        redirect(home_for_role());
    }
}

/* ---------------------------------------------------------------------------
   LOGIN / LOGOUT
   --------------------------------------------------------------------------- */

/**
 * Attempt a login.
 *
 * @return array{ok:bool, message:string, role:?string}
 */
function attempt_login(string $identity, string $password, bool $remember): array
{
    $identity = trim($identity);

    if ($identity === '' || $password === '') {
        return ['ok' => false, 'message' => 'Sila masukkan nama pengguna dan kata laluan.', 'role' => null];
    }

    // Accept either the username or the email address.
    // NOTE: emulated prepares are OFF, so a named placeholder may only appear
    // once in the statement - hence two separate parameters for the same value.
    $stmt = db()->prepare(
        'SELECT u.user_id, u.username, u.password, u.`role`, u.status, u.must_change_pw,
                l.lecturer_id
           FROM users u
           LEFT JOIN lecturers l ON l.user_id = u.user_id
          WHERE u.username = :identity_user OR u.email = :identity_email
          LIMIT 1'
    );
    $stmt->execute([':identity_user' => $identity, ':identity_email' => $identity]);
    $user = $stmt->fetch();

    // Deliberately generic message: never reveal whether the account exists.
    $failure = ['ok' => false, 'message' => 'Nama pengguna atau kata laluan tidak sah.', 'role' => null];
    $dummyHash = '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.';

    if (!$user) {
        // Still run a hash comparison so a missing account is not faster than
        // a wrong password (timing-based account enumeration).
        password_verify($password, $dummyHash);
        return $failure;
    }

    if (!password_verify($password, $user['password'])) {
        return $failure;
    }

    if ($user['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Akaun anda tidak aktif. Sila hubungi Pentadbir.', 'role' => null];
    }

    // Re-hash if the algorithm/parameters have changed since the hash was stored.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $upd = db()->prepare('UPDATE users SET password = :hash WHERE user_id = :id');
        $upd->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['user_id']]);
    }

    login_user($user, $remember);

    return ['ok' => true, 'message' => 'Berjaya log masuk.', 'role' => $user['role']];
}

/** Establish the session for a verified user row. */
function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);   // defeats session fixation

    $_SESSION['user_id']  = (int)$user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];

    if (!empty($user['lecturer_id'])) {
        $_SESSION['lecturer_id'] = (int)$user['lecturer_id'];
    }

    if ($remember) {
        setcookie('remember_me', '1', [
            'expires'  => time() + REMEMBER_DAYS * 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_SESSION['remember'] = true;
    }

    $upd = db()->prepare('UPDATE users SET last_login = NOW() WHERE user_id = :id');
    $upd->execute([':id' => $user['user_id']]);

    log_activity((int)$user['user_id'], 'login', 'users', (int)$user['user_id'], 'Logged in to the system');

    unset($_SESSION['redirect_after_login']);
}

/** Destroy the session and the remember-me cookie. */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    setcookie('remember_me', '', ['expires' => time() - 42000, 'path' => '/']);

    session_destroy();
}
