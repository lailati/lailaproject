<?php
/**
 * Forgot password
 *
 * There is no mail server in the XAMPP development environment, so this page
 * does not send a reset link. It records the request in the activity log and
 * tells the user to contact the Pentadbir, who resets the password from
 * Manage Users and reads the new password out to the account holder.
 *
 * The submitted address is never confirmed to exist, so this page cannot be
 * used to discover who has an account.
 */

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect(home_for_role());
}

$submitted = false;
$email     = '';
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim((string)($_POST['email'] ?? ''));
    $submitted = true;

    if (!verify_csrf()) {
        $errors[] = 'Sesi tidak sah. Sila cuba sekali lagi.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Sila masukkan alamat emel yang sah.';
    } elseif ($email !== '') {
        // Log the request against the account when it exists (for the admin's
        // benefit) but never reveal the outcome to the requester.
        $stmt = db()->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            log_activity((int)$userId, 'password_reset_request', 'users', (int)$userId,
                'Password reset requested from ' . $email);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lupa Kata Laluan &middot; <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= e(ASSETS_URL) ?>/img/favicon.svg">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/vendor/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/app.css">
</head>
<body class="app-body">

<div class="login-page">
    <section class="login-hero">
        <div class="login-hero-brand">
            <svg viewBox="0 0 32 36" width="52" height="58" aria-hidden="true">
                <path d="M16 1 3 6v13c0 8 5.6 13.3 13 16 7.4-2.7 13-8 13-16V6L16 1z" fill="#eaf1fb"/>
                <path d="M16 4.6 6 8.4v10.4c0 6.4 4.4 10.7 10 12.8 5.6-2.1 10-6.4 10-12.8V8.4L16 4.6z" fill="#1e4f9c"/>
                <path d="M16 4.6 6 8.4v4.2h20V8.4L16 4.6z" fill="#b8242a"/>
            </svg>
            <span>
                <strong>FSTP</strong>
                <small>FAKULTI SAINS DAN TEKNOLOGI PERTAHANAN<br><?= e(APP_UNIVERSITY) ?></small>
            </span>
        </div>
        <div class="login-hero-body">
            <h1>FSTP TALENT MAP</h1>
            <h2><?= e(APP_FULL_NAME) ?></h2>
        </div>
        <div class="login-hero-foot">Sistem Pemetaan Kepakaran Pensyarah</div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <h3>Lupa Kata Laluan</h3>
            <p class="sub">Masukkan emel berdaftar anda</p>

            <?php if ($submitted && !$errors): ?>
                <div class="alert alert-success py-3 px-3">
                    <small>
                        <b>Permintaan direkodkan.</b><br>
                        Sila hubungi Pentadbir sistem untuk menetapkan semula kata laluan anda:
                        <br><br><i class="bi bi-envelope me-1"></i><?= e('admin@upnm.edu.my') ?>
                    </small>
                </div>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger py-2 px-3"><small><?= e($error) ?></small></div>
                <?php endforeach; ?>

                <form method="post" action="<?= e(url('auth/forgot-password.php')) ?>" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <label class="visually-hidden" for="email">Emel berdaftar</label>
                    <div class="login-input">
                        <i class="bi bi-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="Emel berdaftar"
                               value="<?= e($email) ?>" required autofocus>
                    </div>

                    <button class="btn-login" type="submit">Hantar Permintaan</button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="<?= e(url('login.php')) ?>" class="small">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke halaman log masuk
                </a>
            </div>
        </div>
    </section>
</div>

<script src="<?= e(ASSETS_URL) ?>/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= e(ASSETS_URL) ?>/js/app.js"></script>
</body>
</html>
