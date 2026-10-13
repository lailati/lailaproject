<?php
/**
 * Manage Users (Pentadbir)
 *
 * Creates and maintains every account in the system:
 *   - create account  (also creates the matching lecturers row for a Pensyarah)
 *   - activate / deactivate
 *   - reset password  (a new one-off password is shown once, and the user is
 *     forced to choose their own on next login)
 *   - delete          (never your own account, never the last Pentadbir)
 *
 * A lecturer-role account is created together with its lecturer profile in one
 * transaction, so the 1:1 relationship in the ERD can never end up half-built.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo      = db();
$me       = current_user();
$meId     = (int)$me['user_id'];
$errors   = [];
$old      = [];      // repopulates the form after a validation failure

/* =========================================================================
   ACTIONS (POST)
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        flash('danger', 'Sesi tidak sah. Sila cuba sekali lagi.');
        redirect('admin/users.php');
    }

    $action = $_POST['action'] ?? '';

    /* ------------------------------------------------------------ create -- */
    if ($action === 'create') {
        $old = [
            'username'      => trim((string)($_POST['username'] ?? '')),
            'full_name'     => trim((string)($_POST['full_name'] ?? '')),
            'email'         => trim((string)($_POST['email'] ?? '')),
            'role'          => ($_POST['role'] ?? 'lecturer') === 'admin' ? 'admin' : 'lecturer',
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'status'        => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'staff_no'      => trim((string)($_POST['staff_no'] ?? '')),
            'position'      => trim((string)($_POST['position'] ?? '')),
            'phone'         => trim((string)($_POST['phone'] ?? '')),
            'password'      => (string)($_POST['password'] ?? ''),
        ];

        /* ---- validation ---- */
        if ($old['username'] === '' || mb_strlen($old['username']) < 3) {
            $errors[] = 'Username mesti sekurang-kurangnya 3 aksara.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $old['username'])) {
            $errors[] = 'Username hanya boleh mengandungi huruf, nombor, titik, garis bawah atau sengkang.';
        }

        if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Alamat emel tidak sah.';
        }

        if (strlen($old['password']) < 8) {
            $errors[] = 'Kata laluan awal mesti sekurang-kurangnya 8 aksara.';
        }

        if ($old['role'] === 'lecturer') {
            if ($old['full_name'] === '') {
                $errors[] = 'Nama penuh diperlukan untuk akaun Pensyarah.';
            }
            if ($old['department_id'] <= 0) {
                $errors[] = 'Sila pilih jabatan untuk akaun Pensyarah.';
            }
            if ($old['position'] === '') {
                $errors[] = 'Jawatan diperlukan untuk akaun Pensyarah.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $stmt->execute([$old['username']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Username "' . $old['username'] . '" sudah digunakan.';
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Emel "' . $old['email'] . '" sudah didaftarkan.';
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, email, password, `role`, department_id,
                                        status, initial_password, must_change_pw)
                     VALUES (:username, :email, :password, :role, :department_id,
                             :status, :initial_password, 1)'
                );
                $stmt->execute([
                    ':username'         => $old['username'],
                    ':email'            => $old['email'],
                    ':password'         => password_hash($old['password'], PASSWORD_DEFAULT),
                    ':role'             => $old['role'],
                    ':department_id'    => $old['department_id'] > 0 ? $old['department_id'] : null,
                    ':status'           => $old['status'],
                    ':initial_password' => $old['password'],
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                if ($old['role'] === 'lecturer') {
                    // Auto-generate a staff number when the admin leaves it blank.
                    $staffNo = $old['staff_no'];
                    if ($staffNo === '') {
                        $next = (int)$pdo->query(
                            'SELECT COALESCE(MAX(CAST(SUBSTRING(staff_no, 5) AS UNSIGNED)), 0)
                               FROM lecturers WHERE staff_no LIKE \'UPNM%\''
                        )->fetchColumn() + 1;
                        $staffNo = 'UPNM' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO lecturers (user_id, staff_no, full_name, email, department_id,
                                                `position`, phone, status)
                         VALUES (:user_id, :staff_no, :full_name, :email, :department_id,
                                 :position, :phone, :status)'
                    );
                    $stmt->execute([
                        ':user_id'       => $newUserId,
                        ':staff_no'      => $staffNo,
                        ':full_name'     => $old['full_name'],
                        ':email'         => $old['email'],
                        ':department_id' => $old['department_id'],
                        ':position'      => $old['position'],
                        ':phone'         => $old['phone'] !== '' ? $old['phone'] : null,
                        ':status'        => $old['status'],
                    ]);
                    $lecturerId = (int)$pdo->lastInsertId();
                }

                $pdo->commit();

                log_activity($meId, 'insert', 'users', $newUserId,
                    'Created ' . $old['role'] . ' account "' . $old['username'] . '"');

                flash('success',
                    'Akaun "' . $old['username'] . '" berjaya dicipta. '
                    . 'Kata laluan awal: ' . $old['password']
                    . ' — pengguna akan diminta menukarnya semasa log masuk pertama.');

                redirect('admin/users.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = APP_DEBUG
                    ? 'Gagal menyimpan: ' . $e->getMessage()
                    : 'Gagal menyimpan rekod. Sila cuba lagi.';
            }
        }
    }

    /* ------------------------------------------------ activate/deactivate -- */
    if ($action === 'toggle') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId === $meId) {
            flash('danger', 'Anda tidak boleh mengubah status akaun anda sendiri.');
            redirect('admin/users.php');
        }

        $stmt = $pdo->prepare('SELECT username, `role`, status FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $target = $stmt->fetch();

        if ($target) {
            $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';

            // Never allow the last active Pentadbir to be switched off.
            if ($target['role'] === 'admin' && $newStatus === 'inactive') {
                $admins = scalar('SELECT COUNT(*) FROM users WHERE `role` = \'admin\' AND status = \'active\'');
                if ($admins <= 1) {
                    flash('danger', 'Ini satu-satunya akaun Pentadbir yang aktif dan tidak boleh dinyahaktifkan.');
                    redirect('admin/users.php');
                }
            }

            $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?')
                ->execute([$newStatus, $userId]);
            $pdo->prepare('UPDATE lecturers SET status = ? WHERE user_id = ?')
                ->execute([$newStatus, $userId]);

            log_activity($meId, 'update', 'users', $userId,
                ucfirst($newStatus) . ' account "' . $target['username'] . '"');

            flash('success', 'Status akaun "' . $target['username'] . '" ditukar kepada ' . $newStatus . '.');
        }
        redirect('admin/users.php');
    }

    /* ---------------------------------------------------- reset password -- */
    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT username FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $username = $stmt->fetchColumn();

        if ($username) {
            // Readable one-off password: letters + digits, no look-alike characters.
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
            $temp = '';
            for ($i = 0; $i < 10; $i++) {
                $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $pdo->prepare(
                'UPDATE users SET password = ?, initial_password = ?, must_change_pw = 1 WHERE user_id = ?'
            )->execute([password_hash($temp, PASSWORD_DEFAULT), $temp, $userId]);

            log_activity($meId, 'update', 'users', $userId,
                'Reset password for "' . $username . '"');

            flash('success', 'Kata laluan baharu untuk "' . $username . '": ' . $temp
                . ' — berikan kepada pengguna; mereka perlu menukarnya semasa log masuk.');
        }
        redirect('admin/users.php');
    }

    /* ------------------------------------------------------------ delete -- */
    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId === $meId) {
            flash('danger', 'Anda tidak boleh menghapuskan akaun anda sendiri.');
            redirect('admin/users.php');
        }

        $stmt = $pdo->prepare('SELECT username, `role` FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $target = $stmt->fetch();

        if ($target) {
            if ($target['role'] === 'admin') {
                $admins = scalar('SELECT COUNT(*) FROM users WHERE `role` = \'admin\'');
                if ($admins <= 1) {
                    flash('danger', 'Akaun Pentadbir terakhir tidak boleh dihapuskan.');
                    redirect('admin/users.php');
                }
            }

            // ON DELETE CASCADE removes the lecturer row and every dependent
            // record (research, publications, documents, ...).
            $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);

            log_activity($meId, 'delete', 'users', $userId,
                'Deleted account "' . $target['username'] . '"');

            flash('success', 'Akaun "' . $target['username'] . '" dan semua rekod berkaitannya telah dihapuskan.');
        }
        redirect('admin/users.php');
    }
}

/* =========================================================================
   FILTERS + LIST
   ========================================================================= */
// NOTE: these are named $filterRole / $filterStatus, not $role / $status,
// because includes/header.php sets $__role for the logged-in user and a shared
// name here would silently show the wrong option in the dropdowns.
$q            = trim((string)($_GET['q'] ?? ''));
$filterRole   = in_array($_GET['role'] ?? '', ['admin', 'lecturer'], true) ? $_GET['role'] : '';
$filterStatus = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.username LIKE ? OR u.email LIKE ? OR l.full_name LIKE ? OR l.staff_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($filterRole !== '') {
    $where[] = 'u.`role` = ?';
    $params[] = $filterRole;
}
if ($filterStatus !== '') {
    $where[] = 'u.status = ?';
    $params[] = $filterStatus;
}

$sql = 'SELECT u.user_id, u.username, u.email, u.`role`, u.status, u.last_login, u.created_at,
               u.must_change_pw, d.department_name, l.full_name, l.staff_no, l.`position`, l.lecturer_id
          FROM users u
          LEFT JOIN departments d ON d.department_id = u.department_id
          LEFT JOIN lecturers  l ON l.user_id = u.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY u.`role` ASC, u.username ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$departments = $pdo->query(
    'SELECT department_id, department_name FROM departments WHERE status = \'active\' ORDER BY department_name'
)->fetchAll();

$showForm = ($_GET['action'] ?? '') === 'new' || $errors;

$page_title = 'Manage Users';
$page_sub   = 'Urus akaun pengguna sistem — Pentadbir dan Pensyarah';
$active     = 'users';

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <b>Sila betulkan perkara berikut:</b>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ================= ADD USER ACCOUNT ================= -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-person-plus text-primary"></i> Add User Account (Admin)
        <button class="btn btn-sm btn-outline-secondary ms-auto" type="button"
                data-bs-toggle="collapse" data-bs-target="#addUserForm">
            <i class="bi bi-chevron-expand"></i> Tunjuk / Sembunyi
        </button>
    </div>
    <div class="collapse <?= $showForm ? 'show' : '' ?>" id="addUserForm">
        <div class="card-body">
            <form method="post" action="<?= e(url('admin/users.php')) ?>" class="no-double-submit">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
                        <input class="form-control" id="full_name" name="full_name"
                               value="<?= e($old['full_name'] ?? '') ?>" placeholder="cth: Dr. Ahmad Bin Ali">
                        <div class="form-text">Diperlukan untuk akaun Pensyarah.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                        <input class="form-control" id="username" name="username" required
                               value="<?= e($old['username'] ?? '') ?>" placeholder="cth: ahmad">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                        <input class="form-control" id="email" name="email" type="email" required
                               value="<?= e($old['email'] ?? '') ?>" placeholder="cth: ahmad@upnm.edu.my">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="lecturer" <?= ($old['role'] ?? 'lecturer') === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                            <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="department_id">Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">— Pilih —</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['department_id'] ?>"
                                    <?= (int)($old['department_id'] ?? 0) === (int)$d['department_id'] ? 'selected' : '' ?>>
                                    <?= e($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active"   <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="password">Initial Password <span class="text-danger">*</span></label>
                        <div class="pw-wrap input-group">
                            <input class="form-control" id="password" name="password" type="password"
                                   required minlength="8" autocomplete="new-password" placeholder="min 8 aksara">
                            <button class="btn btn-outline-secondary pw-toggle" type="button" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="staff_no">Staff No</label>
                        <input class="form-control" id="staff_no" name="staff_no"
                               value="<?= e($old['staff_no'] ?? '') ?>" placeholder="kosongkan untuk auto">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="position">Position</label>
                        <input class="form-control" id="position" name="position"
                               value="<?= e($old['position'] ?? '') ?>" placeholder="cth: Senior Lecturer">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input class="form-control" id="phone" name="phone"
                               value="<?= e($old['phone'] ?? '') ?>" placeholder="cth: +60 12-345 6789">
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <a class="btn btn-light border" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i>Save User
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= USER LIST ================= -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-people-fill text-primary"></i> User Accounts
        <span class="badge text-bg-light ms-2"><?= count($users) ?></span>
        <span class="ms-auto small text-muted">Kata laluan disimpan dalam bentuk bcrypt hash</span>
    </div>

    <div class="card-body pb-2">
        <form class="row g-2" method="get" action="<?= e(url('admin/users.php')) ?>">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" name="q"
                           value="<?= e($q) ?>" placeholder="Cari nama, username, emel atau staff no...">
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <select class="form-select" name="role">
                    <option value="">Semua Role</option>
                    <option value="admin"    <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="lecturer" <?= $filterRole === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="active"   <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">Search</button>
                <a class="btn btn-light border" href="<?= e(url('admin/users.php')) ?>">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:52px">No.</th>
                    <th>Nama / Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="width:150px">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-people"></i>Tiada akaun sepadan dengan carian anda.
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-initials" style="width:30px;height:30px;font-size:11px">
                                    <?= e(initials($row['full_name'] ?: $row['username'])) ?>
                                </span>
                                <span>
                                    <b><?= e($row['full_name'] ?: $row['username']) ?></b>
                                    <br><small class="text-muted">
                                        <?= e($row['username']) ?>
                                        <?= $row['staff_no'] ? ' &middot; ' . e($row['staff_no']) : '' ?>
                                    </small>
                                </span>
                            </div>
                        </td>
                        <td><small><?= e($row['email']) ?></small></td>
                        <td>
                            <span class="role-chip role-<?= e($row['role']) ?>">
                                <?= $row['role'] === 'admin' ? 'Pentadbir' : 'Pensyarah' ?>
                            </span>
                        </td>
                        <td><small><?= e($row['department_name'] ?? '—') ?></small></td>
                        <td>
                            <?php if ($row['status'] === 'active'): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Inactive</span>
                            <?php endif; ?>
                            <?php if ((int)$row['must_change_pw'] === 1): ?>
                                <span class="badge text-bg-warning" title="Belum tukar kata laluan">pwd</span>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= e(relative_time($row['last_login'])) ?></small></td>
                        <td>
                            <div class="d-flex gap-1">
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                                            title="<?= $row['status'] === 'active' ? 'Nyahaktifkan' : 'Aktifkan' ?>"
                                            <?= (int)$row['user_id'] === $meId ? 'disabled' : '' ?>>
                                        <i class="bi <?= $row['status'] === 'active' ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                                    </button>
                                </form>

                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit"
                                            title="Reset kata laluan"
                                            data-confirm="Jana kata laluan baharu untuk '<?= e($row['username']) ?>'?">
                                        <i class="bi bi-key"></i>
                                    </button>
                                </form>

                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                            title="Hapus akaun"
                                            data-confirm="Hapuskan akaun '<?= e($row['username']) ?>'? SEMUA rekod berkaitan (profil, penyelidikan, penerbitan, dokumen) akan turut dihapuskan. Tindakan ini tidak boleh dibatalkan."
                                            <?= (int)$row['user_id'] === $meId ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
