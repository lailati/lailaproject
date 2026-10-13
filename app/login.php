<?php
/**
 * Login page — single entry point for BOTH roles.
 *
 * The role is read from the users table after the password is verified, then
 * the user is sent to their own dashboard (Pentadbir -> admin, Pensyarah ->
 * lecturer). Nobody chooses their role at login, so a lecturer cannot promote
 * themselves by picking an option in the form.
 */

require_once __DIR__ . '/includes/auth.php';

// Already signed in? Skip the form.
if (is_logged_in()) {
    redirect(home_for_role());
}

$errors   = [];
$identity = '';
$remember = false;
$messages = take_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if (!verify_csrf()) {
        $errors[] = 'Sesi tidak sah. Sila muat semula halaman dan cuba lagi.';
    } elseif ($identity === '' || $password === '') {
        $errors[] = 'Sila masukkan nama pengguna dan kata laluan.';
    } else {
        $result = attempt_login($identity, $password, $remember);

        if ($result['ok']) {
            // Return the user to the page they were trying to reach, but only
            // if it points inside this application (prevents an open redirect).
            $target = (string)($_SESSION['redirect_after_login'] ?? '');
            unset($_SESSION['redirect_after_login']);

            if ($target !== ''
                && str_starts_with($target, BASE_URL . '/')
                && !str_contains($target, '//')
                && !str_contains($target, '\\')) {
                header('Location: ' . $target);
                exit;
            }
            redirect(home_for_role());
        }

        $errors[] = $result['message'];
        $password = '';
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log Masuk &middot; <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= e(ASSETS_URL) ?>/img/favicon.svg">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/vendor/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/app.css">
</head>
<body class="app-body">

<div class="login-page">

    <!-- ============ LEFT: branding ============ -->
    <section class="login-hero">
        <div class="login-hero-brand">
            <svg viewBox="0 0 32 36" width="52" height="58" aria-hidden="true">
                <path d="M16 1 3 6v13c0 8 5.6 13.3 13 16 7.4-2.7 13-8 13-16V6L16 1z" fill="#eaf1fb"/>
                <path d="M16 4.6 6 8.4v10.4c0 6.4 4.4 10.7 10 12.8 5.6-2.1 10-6.4 10-12.8V8.4L16 4.6z" fill="#1e4f9c"/>
                <path d="M16 4.6 6 8.4v4.2h20V8.4L16 4.6z" fill="#b8242a"/>
                <path d="M14.6 15.2h2.8v9.4h-2.8z" fill="#eaf1fb"/>
                <path d="M11.2 18.6h9.6v2.6h-9.6z" fill="#eaf1fb"/>
            </svg>
            <span>
                <strong>FSTP</strong>
                <small>FAKULTI SAINS DAN TEKNOLOGI PERTAHANAN<br><?= e(APP_UNIVERSITY) ?></small>
            </span>
        </div>

        <div class="login-hero-body">
            <h1>FSTP TALENT MAP</h1>
            <h2><?= e(APP_FULL_NAME) ?></h2>
            <div class="login-hero-points">
                <span><i class="bi bi-people"></i> Connect Expertise</span>
                <span><i class="bi bi-search"></i> Discover Talent</span>
                <span><i class="bi bi-diagram-3"></i> Build Collaboration</span>
            </div>
        </div>

        <div class="login-hero-foot">
            Sistem Pemetaan Kepakaran Pensyarah &middot; PSM 2241073 Lailati binti Mohd
        </div>
    </section>

    <!-- ============ RIGHT: login form ============ -->
    <section class="login-panel">
        <div class="login-card">

            <h3>Welcome</h3>
            <p class="sub">Log masuk ke akaun anda</p>

            <?php foreach ($messages as $m): ?>
                <div class="alert alert-<?= e($m['type']) ?> py-2 px-3" role="alert">
                    <small><?= e($m['message']) ?></small>
                </div>
            <?php endforeach; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 px-3" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <small class="d-block"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></small>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="on" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <label class="visually-hidden" for="identity">Nama pengguna atau emel</label>
                <div class="login-input">
                    <i class="bi bi-person"></i>
                    <input type="text" id="identity" name="identity"
                           placeholder="Username / Email"
                           value="<?= e($identity) ?>"
                           required autofocus autocomplete="username">
                </div>

                <label class="visually-hidden" for="password">Kata laluan</label>
                <div class="login-input">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password"
                           placeholder="Kata Laluan"
                           required autocomplete="current-password">
                    <button class="pw-toggle" type="button" tabindex="-1"
                            aria-label="Tunjuk atau sembunyi kata laluan">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <div class="login-meta">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember"
                               id="remember" value="1" <?= $remember ? 'checked' : '' ?>>
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="<?= e(url('auth/forgot-password.php')) ?>">Forgot Password?</a>
                </div>

                <button class="btn-login" type="submit">Login</button>
            </form>

            <div class="login-hint">
                <b>Demo accounts</b><br>
                Pentadbir: <code>admin</code> / <code>Admin@123</code><br>
                Pensyarah: <code>aisyah</code> / <code>Pensyarah@123</code>
            </div>

        </div>
    </section>
</div>

<script src="<?= e(ASSETS_URL) ?>/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= e(ASSETS_URL) ?>/js/app.js"></script>
</body>
</html>
