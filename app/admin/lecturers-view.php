<?php
/**
 * Admin > Lecturer profile (read-only)
 *
 * Everything one lecturer has recorded, in one page with tabs. Built from
 * includes/record-types.php so the columns match the record browsers exactly.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo        = db();
$lecturerId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT l.*, d.department_name, d.department_code,
            u.username, u.email AS login_email, u.status AS user_status, u.last_login
       FROM lecturers l
       LEFT JOIN departments d ON d.department_id = l.department_id
       LEFT JOIN users       u ON u.user_id       = l.user_id
      WHERE l.lecturer_id = :id
      LIMIT 1'
);
$stmt->execute([':id' => $lecturerId]);
$lecturer = $stmt->fetch();

if (!$lecturer) {
    flash('danger', 'Rekod pensyarah tidak dijumpai.');
    redirect('admin/lecturers.php');
}

/* ------------------------------------------------------------- own expertise */
$stmt = $pdo->prepare(
    'SELECT le.expertise_level, le.years_experience, le.research_interest, le.status, le.created_at,
            ex.expertise_name, ec.category_name
       FROM lecturer_expertise le
       JOIN expertise ex            ON ex.expertise_id = le.expertise_id
       LEFT JOIN expertise_categories ec ON ec.category_id = ex.category_id
      WHERE le.lecturer_id = :id
      ORDER BY FIELD(le.expertise_level, \'Expert\', \'Advanced\', \'Intermediate\', \'Beginner\'),
               le.years_experience DESC'
);
$stmt->execute([':id' => $lecturerId]);
$expertiseRows = $stmt->fetchAll();

/* ------------------------------------------------- every other record type -- */
$records    = [];
$counts     = [];
foreach (record_types() as $key => $type) {
    $sql = 'SELECT ' . $type['select'] . '
              FROM ' . $type['table'] . ' x
             WHERE x.lecturer_id = :id
             ORDER BY ' . $type['order'];
    $s = $pdo->prepare($sql);
    $s->execute([':id' => $lecturerId]);
    $records[$key] = $s->fetchAll();
    $counts[$key]  = count($records[$key]);
}

$completion = profile_completion($lecturer);

$photo = $lecturer['profile_photo'] && is_file(UPLOAD_PHOTO_DIR . '/' . $lecturer['profile_photo'])
    ? UPLOAD_PHOTO_URL . '/' . rawurlencode($lecturer['profile_photo'])
    : null;

$page_title = 'Lecturer Profile';
$page_sub   = $lecturer['full_name'];
$active     = 'lecturers';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
    <a class="btn btn-sm btn-light border" href="<?= e(url('admin/lecturers.php')) ?>">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke senarai pensyarah
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <?php if ($photo): ?>
                    <img src="<?= e($photo) ?>" alt="" class="avatar" style="width:84px;height:84px">
                <?php else: ?>
                    <span class="avatar avatar-initials" style="width:84px;height:84px;font-size:26px">
                        <?= e(initials($lecturer['full_name'])) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="col">
                <h5 class="mb-1"><?= e($lecturer['full_name']) ?></h5>
                <div class="text-muted small mb-1">
                    <?= e($lecturer['position']) ?> &middot; <?= e($lecturer['department_name'] ?? '') ?>, FSTP
                </div>
                <div class="small text-muted">
                    <i class="bi bi-hash"></i><?= e($lecturer['staff_no']) ?>
                    <span class="mx-2">|</span>
                    <i class="bi bi-envelope"></i> <?= e($lecturer['email']) ?>
                    <?php if ($lecturer['phone']): ?>
                        <span class="mx-2">|</span><i class="bi bi-telephone"></i> <?= e($lecturer['phone']) ?>
                    <?php endif; ?>
                    <?php if ($lecturer['location']): ?>
                        <span class="mx-2">|</span><i class="bi bi-geo-alt"></i> <?= e($lecturer['location']) ?>
                    <?php endif; ?>
                </div>
                <div class="mt-2">
                    <?php if ($lecturer['status'] === 'active'): ?>
                        <span class="badge text-bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Inactive</span>
                    <?php endif; ?>
                    <span class="badge text-bg-light">Login terakhir:
                        <?= e(relative_time($lecturer['last_login'])) ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Profile Completion</span>
                    <b><?= (int)$completion ?>%</b>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar <?= $completion >= 80 ? 'bg-success' : ($completion >= 50 ? 'bg-primary' : 'bg-warning') ?>"
                         style="width: <?= (int)$completion ?>%"></div>
                </div>
            </div>
        </div>

        <?php if ($lecturer['research_area']): ?>
            <hr class="my-3">
            <div class="small">
                <span class="text-muted">Bidang kajian utama:</span>
                <?= e($lecturer['research_area']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- tab strip -->
<ul class="nav nav-tabs" id="profileTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-expertise" type="button">
            <i class="bi bi-lightbulb me-1"></i>Expertise
            <span class="badge text-bg-light ms-1"><?= count($expertiseRows) ?></span>
        </button>
    </li>
    <?php foreach (record_types() as $key => $type): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-<?= e($key) ?>" type="button">
                <i class="bi <?= e($type['icon']) ?> me-1"></i><?= e($type['label']) ?>
                <span class="badge text-bg-light ms-1"><?= (int)$counts[$key] ?></span>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content card border-top-0" style="border-radius:0 0 var(--fstp-radius) var(--fstp-radius)">

    <!-- expertise -->
    <div class="tab-pane fade show active" id="tab-expertise">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:52px">No.</th>
                        <th>Expertise</th>
                        <th>Category</th>
                        <th>Level</th>
                        <th class="text-center">Tahun</th>
                        <th>Research Interest</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$expertiseRows): ?>
                    <tr><td colspan="6"><div class="empty-state">
                        <i class="bi bi-lightbulb"></i>Tiada rekod kepakaran.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($expertiseRows as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><b><?= e($row['expertise_name']) ?></b></td>
                            <td><small><?= e($row['category_name'] ?? '—') ?></small></td>
                            <td>
                                <span class="badge-level level-<?= e(strtolower($row['expertise_level'])) ?>">
                                    <?= e($row['expertise_level']) ?>
                                </span>
                            </td>
                            <td class="text-center"><?= (int)$row['years_experience'] ?></td>
                            <td><small class="text-muted"><?= e($row['research_interest'] ?? '—') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- the seven record types -->
    <?php foreach (record_types() as $key => $type): ?>
        <div class="tab-pane fade" id="tab-<?= e($key) ?>">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:52px">No.</th>
                            <?php foreach ($type['columns'] as $col): ?>
                                <th class="<?= e($col['class'] ?? '') ?>"><?= e($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$records[$key]): ?>
                        <tr><td colspan="<?= count($type['columns']) + 1 ?>">
                            <div class="empty-state">
                                <i class="bi <?= e($type['icon']) ?>"></i>
                                Tiada rekod <?= e(strtolower($type['label'])) ?>.
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($records[$key] as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <?php foreach ($type['columns'] as $col): ?>
                                    <td class="<?= e($col['class'] ?? '') ?>">
                                        <?= record_cell($col, $row) ?>
                                    </td>
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
