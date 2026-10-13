<?php
/**
 * Admin > Lecturers
 *
 * The master list of lecturer profiles. Each row links to the full profile with
 * every record the lecturer has entered, which is how the Pentadbir reviews
 * what has been updated.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

$q        = trim((string)($_GET['q'] ?? ''));
$dept     = (int)($_GET['department'] ?? 0);
$filterStatus = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';

$where  = [];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(l.full_name LIKE ? OR l.staff_no LIKE ? OR l.email LIKE ? OR l.`position` LIKE ?
                 OR EXISTS (SELECT 1 FROM lecturer_expertise le
                              JOIN expertise ex ON ex.expertise_id = le.expertise_id
                             WHERE le.lecturer_id = l.lecturer_id AND ex.expertise_name LIKE ?))';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($dept > 0) {
    $where[]  = 'l.department_id = ?';
    $params[] = $dept;
}
if ($filterStatus !== '') {
    $where[]  = 'l.status = ?';
    $params[] = $filterStatus;
}

$sql = 'SELECT l.lecturer_id, l.staff_no, l.full_name, l.email, l.`position`, l.profile_photo,
               l.status, d.department_name,
               (SELECT COUNT(*) FROM lecturer_expertise le
                 WHERE le.lecturer_id = l.lecturer_id AND le.status = \'active\') AS expertise_count,
               (SELECT COUNT(*) FROM research r       WHERE r.lecturer_id = l.lecturer_id) AS research_count,
               (SELECT COUNT(*) FROM publications p   WHERE p.lecturer_id = l.lecturer_id) AS publication_count,
               (SELECT COUNT(*) FROM certifications c WHERE c.lecturer_id = l.lecturer_id) AS certification_count
          FROM lecturers l
          LEFT JOIN departments d ON d.department_id = l.department_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY l.full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lecturers = $stmt->fetchAll();

$departments = $pdo->query(
    'SELECT department_id, department_name FROM departments WHERE status = \'active\' ORDER BY department_name'
)->fetchAll();

$totals = [
    'lecturers'      => scalar('SELECT COUNT(*) FROM lecturers'),
    'active'         => scalar('SELECT COUNT(*) FROM lecturers WHERE status = \'active\''),
    'expertise'      => scalar('SELECT COUNT(*) FROM lecturer_expertise WHERE status = \'active\''),
    'certifications' => scalar('SELECT COUNT(*) FROM certifications'),
];

$page_title = 'Lecturers';
$page_sub   = 'Senarai pensyarah FSTP dan profil kepakaran mereka';
$active     = 'lecturers';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Total Pensyarah',  $totals['lecturers'],      'bi-people-fill',    'kpi-blue'],
        ['Aktif',            $totals['active'],         'bi-person-check',   'kpi-green'],
        ['Rekod Kepakaran',  $totals['expertise'],      'bi-lightbulb',      'kpi-purple'],
        ['Pensijilan',       $totals['certifications'], 'bi-patch-check',    'kpi-orange'],
    ];
    foreach ($tiles as [$label, $value, $icon, $colour]): ?>
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="kpi">
                    <div class="kpi-icon <?= e($colour) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                    <div>
                        <div class="kpi-value"><?= (int)$value ?></div>
                        <div class="kpi-label"><?= e($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-person-badge text-primary"></i> Lecturer Profiles
        <span class="badge text-bg-light ms-2"><?= count($lecturers) ?></span>
    </div>

    <div class="card-body pb-2">
        <form class="row g-2" method="get" action="<?= e(url('admin/lecturers.php')) ?>">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>"
                           placeholder="Cari nama, staff no, emel, jawatan atau kepakaran...">
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <select class="form-select" name="department">
                    <option value="">Semua Jabatan</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>"
                            <?= $dept === (int)$d['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="active"   <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">Search</button>
                <a class="btn btn-light border" href="<?= e(url('admin/lecturers.php')) ?>">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:52px">No.</th>
                    <th>Pensyarah</th>
                    <th>Jabatan</th>
                    <th>Jawatan</th>
                    <th class="text-center">Kepakaran</th>
                    <th class="text-center">Penyelidikan</th>
                    <th class="text-center">Penerbitan</th>
                    <th class="text-center">Pensijilan</th>
                    <th>Status</th>
                    <th style="width:120px">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$lecturers): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="bi bi-person-x"></i>Tiada pensyarah sepadan dengan carian anda.
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lecturers as $i => $row): ?>
                    <?php
                    $photo = $row['profile_photo']
                        && is_file(UPLOAD_PHOTO_DIR . '/' . $row['profile_photo'])
                        ? UPLOAD_PHOTO_URL . '/' . rawurlencode($row['profile_photo'])
                        : null;
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($photo): ?>
                                    <img class="avatar" src="<?= e($photo) ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar avatar-initials">
                                        <?= e(initials($row['full_name'])) ?>
                                    </span>
                                <?php endif; ?>
                                <span>
                                    <b><?= e($row['full_name']) ?></b>
                                    <br><small class="text-muted">
                                        <?= e($row['staff_no']) ?> &middot; <?= e($row['email']) ?>
                                    </small>
                                </span>
                            </div>
                        </td>
                        <td><small><?= e($row['department_name'] ?? '—') ?></small></td>
                        <td><small><?= e($row['position']) ?></small></td>
                        <td class="text-center"><span class="badge text-bg-light"><?= (int)$row['expertise_count'] ?></span></td>
                        <td class="text-center"><span class="badge text-bg-light"><?= (int)$row['research_count'] ?></span></td>
                        <td class="text-center"><span class="badge text-bg-light"><?= (int)$row['publication_count'] ?></span></td>
                        <td class="text-center"><span class="badge text-bg-light"><?= (int)$row['certification_count'] ?></span></td>
                        <td>
                            <?php if ($row['status'] === 'active'): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(url('admin/lecturers-view.php?id=' . (int)$row['lecturer_id'])) ?>">
                                <i class="bi bi-eye me-1"></i>View Profile
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
