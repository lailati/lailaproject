<?php
/**
 * Change own password
 *
 * Reached in two ways:
 *   1. A user opens it from the account menu.
 *   2. require_login() forces it when must_change_pw = 1, i.e. the Pentadbir
 *      issued a one-off password from Manage Users.
 *
 * The current password is always required first, so a hijacked session cannot
 * be used to lock the real owner out of their own account.
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

$user    = current_user();
$forced  = !empty($user['must_change_pw']);
$errors  = [];
$done    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!verify_csrf()) {
        $errors[] = 'Sesi tidak sah. Sila cuba sekali lagi.';
    }

    if (!$errors && !password_verify($current, $user['password'])) {
        $errors[] = 'Kata laluan semasa tidak betul.';
    }

    if (!$errors) {
        if (strlen($new) < 8) {
            $errors[] = 'Kata laluan baharu mesti sekurang-kurangnya 8 aksara.';
        } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $errors[] = 'Kata laluan baharu mesti mengandungi huruf dan nombor.';
        } elseif ($new === $current) {
            $errors[] = 'Kata laluan baharu tidak boleh sama dengan yang lama.';
        } elseif ($new !== $confirm) {
            $errors[] = 'Pengesahan kata laluan tidak sepadan.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->prepare(
            'UPDATE users
                SET password = ?, initial_password = NULL, must_change_pw = 0
              WHERE user_id = ?'
        )->execute([password_hash($new, PASSWORD_DEFAULT), (int)$user['user_id']]);

        log_activity((int)$user['user_id'], 'update', 'users', (int)$user['user_id'],
            'Changed own password');

        unset($_SESSION['force_password_change']);
        flash('success', 'Kata laluan anda telah dikemas kini.');
        redirect(home_for_role());
    }
}

$page_title = 'Tukar Kata Laluan';
$page_sub   = 'Keselamatan akaun';
$active     = '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">

        <?php if ($forced): ?>
            <div class="alert alert-warning">
                <i class="bi bi-shield-exclamation me-1"></i>
                <b>Kata laluan sementara.</b>
                Kata laluan anda telah ditetapkan oleh Pentadbir. Sila pilih kata laluan
                sendiri sebelum menggunakan sistem.
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="bi bi-key text-primary"></i> Tukar Kata Laluan</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('auth/change-password.php')) ?>" class="no-double-submit">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="current_password">Kata Laluan Semasa <span class="text-danger">*</span></label>
                        <div class="pw-wrap input-group">
                            <input class="form-control" type="password" id="current_password"
                                   name="current_password" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary pw-toggle" type="button" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="new_password">Kata Laluan Baharu <span class="text-danger">*</span></label>
                        <div class="pw-wrap input-group">
                            <input class="form-control" type="password" id="new_password"
                                   name="new_password" required minlength="8" autocomplete="new-password">
                            <button class="btn btn-outline-secondary pw-toggle" type="button" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Sekurang-kurangnya 8 aksara, mengandungi huruf dan nombor.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Sahkan Kata Laluan Baharu <span class="text-danger">*</span></label>
                        <div class="pw-wrap input-group">
                            <input class="form-control" type="password" id="confirm_password"
                                   name="confirm_password" required minlength="8" autocomplete="new-password">
                            <button class="btn btn-outline-secondary pw-toggle" type="button" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if (!$forced): ?>
                            <a class="btn btn-light border" href="<?= e(url(home_for_role())) ?>">Cancel</a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i>Simpan Kata Laluan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
