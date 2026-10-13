<?php
/**
 * Pensyarah > My Profile
 *
 * The lecturer maintains their own record: contact details, profile photo and
 * research interests, plus a read-only view of everything they have entered
 * (the same tabbed layout the Pentadbir sees).
 *
 * Deliberately NOT editable here: staff_no and department. Those are
 * administrative facts, so only the Pentadbir can change them - otherwise a
 * lecturer could move themselves into another department.
 */

require_once __DIR__ . '/../includes/auth.php';
require_lecturer();

$user       = current_user();
$lecturerId = (int)($user['lecturer_id'] ?? 0);
$pdo        = db();

if ($lecturerId === 0) {
    flash('danger', 'Akaun anda belum dipautkan kepada rekod pensyarah. Sila hubungi Pentadbir.');
    redirect(home_for_role());
}

$errors = [];

/* =========================================================================
   SAVE
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        flash('danger', 'Sesi tidak sah. Sila cuba sekali lagi.');
        redirect('lecturer/profile.php');
    }

    $fullName  = trim((string)($_POST['full_name'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));
    $position  = trim((string)($_POST['position'] ?? ''));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $location  = trim((string)($_POST['location'] ?? ''));
    $research  = trim((string)($_POST['research_area'] ?? ''));

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $errors[] = 'Nama penuh diperlukan (maksimum 100 aksara).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors[] = 'Alamat emel tidak sah.';
    }
    if ($position === '' || mb_strlen($position) > 100) {
        $errors[] = 'Jawatan diperlukan (maksimum 100 aksara).';
    }
    if ($phone !== '' && mb_strlen($phone) > 20) {
        $errors[] = 'Nombor telefon tidak boleh melebihi 20 aksara.';
    }
    if ($location !== '' && mb_strlen($location) > 100) {
        $errors[] = 'Lokasi tidak boleh melebihi 100 aksara.';
    }

    // The email also identifies the login account, so it must stay unique there.
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?');
        $stmt->execute([$email, (int)$user['user_id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Emel "' . $email . '" sudah digunakan oleh akaun lain.';
        }
    }

    /* ------------------------------------------------------ photo upload -- */
    $newPhoto = null;
    if (!$errors
        && isset($_FILES['profile_photo'])
        && (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

        $upload = store_upload(
            $_FILES['profile_photo'],
            ALLOWED_IMAGE_EXT,
            ALLOWED_IMAGE_MIME,
            MAX_PHOTO_BYTES,
            UPLOAD_PHOTO_DIR
        );

        if (!$upload['ok']) {
            $errors[] = 'Gambar profil: ' . $upload['error'];
        } else {
            $newPhoto = $upload['stored'];
        }
    }

    /* ------------------------------------------------------------- write -- */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE lecturers
                    SET full_name = :full_name, email = :email, `position` = :position,
                        phone = :phone, location = :location, research_area = :research_area
                  WHERE lecturer_id = :id'
            );
            $stmt->execute([
                ':full_name'     => $fullName,
                ':email'         => $email,
                ':position'      => $position,
                ':phone'         => $phone     !== '' ? $phone    : null,
                ':location'      => $location  !== '' ? $location : null,
                ':research_area' => $research  !== '' ? $research : null,
                ':id'            => $lecturerId,
            ]);

            // Keep the login record in step with the profile.
            $pdo->prepare('UPDATE users SET email = ? WHERE user_id = ?')
                ->execute([$email, (int)$user['user_id']]);

            if ($newPhoto !== null) {
                $old = (string)($user['profile_photo'] ?? '');
                $pdo->prepare('UPDATE lecturers SET profile_photo = ? WHERE lecturer_id = ?')
                    ->execute([$newPhoto, $lecturerId]);
                if ($old !== '' && $old !== $newPhoto) {
                    delete_stored_file($old, UPLOAD_PHOTO_DIR);
                }
            }

            $pdo->commit();

            log_activity((int)$user['user_id'], $newPhoto ? 'upload' : 'update', 'lecturers', $lecturerId,
                $newPhoto ? 'Updated profile photo' : 'Updated profile information');

            flash('success', 'Profil anda berjaya dikemas kini.');
            redirect('lecturer/profile.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newPhoto !== null) {
                delete_stored_file($newPhoto, UPLOAD_PHOTO_DIR);
            }
            $errors[] = APP_DEBUG
                ? 'Gagal menyimpan: ' . $ex->getMessage()
                : 'Gagal menyimpan profil. Sila cuba lagi.';
        }
    }
}

/* =========================================================================
   DATA
   ========================================================================= */
$stmt = $pdo->prepare(
    'SELECT l.*, d.department_name, d.department_code, u.username, u.last_login
       FROM lecturers l
       LEFT JOIN departments d ON d.department_id = l.department_id
       LEFT JOIN users u       ON u.user_id = l.user_id
      WHERE l.lecturer_id = :id LIMIT 1'
);
$stmt->execute([':id' => $lecturerId]);
$lecturer = $stmt->fetch();

if (!$lecturer) {
    flash('danger', 'Rekod profil tidak dijumpai.');
    redirect(home_for_role());
}

// Own expertise (needs a join, so it is not one of the generic record types).
$stmt = $pdo->prepare(
    'SELECT le.expertise_level, le.years_experience, le.research_interest,
            ex.expertise_name, ec.category_name
       FROM lecturer_expertise le
       JOIN expertise ex ON ex.expertise_id = le.expertise_id
       LEFT JOIN expertise_categories ec ON ec.category_id = ex.category_id
      WHERE le.lecturer_id = :id AND le.status = \'active\'
      ORDER BY FIELD(le.expertise_level, \'Expert\', \'Advanced\', \'Intermediate\', \'Beginner\')'
);
$stmt->execute([':id' => $lecturerId]);
$expertiseRows = $stmt->fetchAll();

// Own records for every other type.
$records = [];
foreach (record_types() as $key => $type) {
    $s = $pdo->prepare('SELECT ' . $type['select'] . ' FROM ' . $type['table']
                     . ' x WHERE x.lecturer_id = :id ORDER BY ' . $type['order']);
    $s->execute([':id' => $lecturerId]);
    $records[$key] = $s->fetchAll();
}

$completion = profile_completion($lecturer);
$photoUrl   = photo_url($lecturer['profile_photo']);

$page_title = 'My Profile';
$page_sub   = 'Profil peribadi, kepakaran dan rekod profesional anda';
$active     = 'profile';

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

<div class="row g-3 mb-3">
    <!-- ---------- identity card ---------- -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <?php if ($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" alt=""
                         class="avatar mb-2" style="width:110px;height:110px">
                <?php else: ?>
                    <span class="avatar avatar-initials mb-2" style="width:110px;height:110px;font-size:36px">
                        <?= e(initials($lecturer['full_name'])) ?>
                    </span>
                <?php endif; ?>

                <h5 class="mb-1"><?= e($lecturer['full_name']) ?></h5>
                <div class="text-muted small">
                    <?= e($lecturer['position']) ?><br>
                    <?= e($lecturer['department_name'] ?? '') ?>, FSTP
                </div>

                <div class="small mt-3 text-start">
                    <div class="mb-1"><i class="bi bi-hash text-primary"></i> <?= e($lecturer['staff_no']) ?></div>
                    <div class="mb-1"><i class="bi bi-envelope text-primary"></i> <?= e($lecturer['email']) ?></div>
                    <?php if ($lecturer['phone']): ?>
                        <div class="mb-1"><i class="bi bi-telephone text-primary"></i> <?= e($lecturer['phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($lecturer['location']): ?>
                        <div class="mb-1"><i class="bi bi-geo-alt text-primary"></i> <?= e($lecturer['location']) ?></div>
                    <?php endif; ?>
                    <div class="mb-1">
                        <i class="bi bi-person-badge text-primary"></i>
                        <?= e($lecturer['username']) ?>
                    </div>
                    <div><i class="bi bi-clock-history text-primary"></i>
                        Log masuk terakhir: <?= e(relative_time($lecturer['last_login'])) ?></div>
                </div>

                <hr>

                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Profile Completion</span>
                    <b><?= (int)$completion ?>%</b>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar <?= $completion >= 80 ? 'bg-success' : ($completion >= 50 ? 'bg-primary' : 'bg-warning') ?>"
                         style="width: <?= (int)$completion ?>%"></div>
                </div>
                <?php if ($completion < 100): ?>
                    <div class="small text-muted mt-2">
                        Lengkapkan butiran di sebelah kanan untuk meningkatkan peratusan ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ---------- edit form ---------- -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pencil-square text-primary"></i> Edit Profile</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data"
                      action="<?= e(url('lecturer/profile.php')) ?>" class="no-double-submit">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control" id="full_name" name="full_name" required maxlength="100"
                                   value="<?= e($lecturer['full_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                            <input class="form-control" type="email" id="email" name="email" required maxlength="100"
                                   value="<?= e($lecturer['email']) ?>">
                            <div class="form-text">Emel ini juga digunakan untuk log masuk.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="position">Position <span class="text-danger">*</span></label>
                            <input class="form-control" id="position" name="position" required maxlength="100"
                                   value="<?= e($lecturer['position']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input class="form-control" id="phone" name="phone" maxlength="20"
                                   value="<?= e($lecturer['phone']) ?>" placeholder="+60 12-345 6789">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="location">Location</label>
                            <input class="form-control" id="location" name="location" maxlength="100"
                                   value="<?= e($lecturer['location']) ?>" placeholder="Kuala Lumpur, Malaysia">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="department">Department</label>
                            <input class="form-control" id="department" value="<?= e($lecturer['department_name'] ?? '') ?>"
                                   disabled>
                            <div class="form-text">Ditadbir oleh Pentadbir sistem.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="staff_no">Staff No</label>
                            <input class="form-control" id="staff_no" value="<?= e($lecturer['staff_no']) ?>" disabled>
                            <div class="form-text">Ditadbir oleh Pentadbir sistem.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="profile_photo">Profile Photo</label>
                            <input class="form-control" type="file" id="profile_photo" name="profile_photo"
                                   accept=".jpg,.jpeg,.png">
                            <div class="form-text">JPG atau PNG, maksimum 2 MB.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="research_area">Research Areas</label>
                            <textarea class="form-control" id="research_area" name="research_area" rows="2"
                                      placeholder="Contoh: Cyber Threat Detection, Network Security, Digital Forensics"><?= e($lecturer['research_area']) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i>Save Profile
                        </button>
                        <a class="btn btn-light border" href="<?= e(url('lecturer/profile.php')) ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ---------- everything this lecturer has recorded ---------- -->
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#p-expertise" type="button">
            <i class="bi bi-lightbulb me-1"></i>Expertise
            <span class="badge text-bg-light ms-1"><?= count($expertiseRows) ?></span>
        </button>
    </li>
    <?php foreach (record_types() as $key => $type): ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#p-<?= e($key) ?>" type="button">
                <i class="bi <?= e($type['icon']) ?> me-1"></i><?= e($type['label']) ?>
                <span class="badge text-bg-light ms-1"><?= count($records[$key]) ?></span>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content card border-top-0" style="border-radius:0 0 var(--fstp-radius) var(--fstp-radius)">

    <div class="tab-pane fade show active" id="p-expertise">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th style="width:48px">No.</th><th>Expertise</th><th>Category</th>
                        <th>Level</th><th class="text-center">Tahun</th><th>Research Interest</th></tr>
                </thead>
                <tbody>
                <?php if (!$expertiseRows): ?>
                    <tr><td colspan="6"><div class="empty-state">
                        <i class="bi bi-lightbulb"></i>Belum ada rekod kepakaran.
                        <div class="mt-2">
                            <a class="btn btn-sm btn-primary" href="<?= e(url('lecturer/expertise.php')) ?>">
                                Tambah Kepakaran
                            </a>
                        </div>
                    </div></td></tr>
                <?php else: ?>
                    <?php foreach ($expertiseRows as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><b><?= e($row['expertise_name']) ?></b></td>
                            <td><small class="text-muted"><?= e($row['category_name'] ?? '—') ?></small></td>
                            <td><span class="badge-level level-<?= e(strtolower($row['expertise_level'])) ?>">
                                <?= e($row['expertise_level']) ?></span></td>
                            <td class="text-center"><?= (int)$row['years_experience'] ?></td>
                            <td><small class="text-muted"><?= e($row['research_interest'] ?? '—') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach (record_types() as $key => $type): ?>
        <div class="tab-pane fade" id="p-<?= e($key) ?>">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:48px">No.</th>
                            <?php foreach ($type['columns'] as $col): ?>
                                <?php if ($col['key'] === 'updated_at') continue; ?>
                                <th><?= e($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$records[$key]): ?>
                        <tr><td colspan="<?= count($type['columns']) + 1 ?>">
                            <div class="empty-state">
                                <i class="bi <?= e($type['icon']) ?>"></i>Belum ada rekod.
                                <div class="mt-2">
                                    <a class="btn btn-sm btn-primary"
                                       href="<?= e(url('lecturer/' . $key . '.php')) ?>">
                                        Tambah <?= e($type['label']) ?>
                                    </a>
                                </div>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($records[$key] as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <?php foreach ($type['columns'] as $col): ?>
                                    <?php if ($col['key'] === 'updated_at') continue; ?>
                                    <td><?= record_cell($col, $row) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
