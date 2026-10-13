<?php
/**
 * Admin > Talent Mapping
 *
 * "Cari dan tapis pensyarah berdasarkan kepakaran, jabatan atau bidang kajian."
 *
 * This is the page the whole system exists for: given a need (a project to
 * supervise, a panel to sit, a collaboration to staff), find who in FSTP can
 * cover it. Filter by keyword, department and expertise level; the left panel
 * lists the expertise categories with a live count and acts as a shortcut.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

$q          = trim((string)($_GET['q'] ?? ''));
$dept       = (int)($_GET['department'] ?? 0);
$level      = (string)($_GET['level'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$sort       = ($_GET['sort'] ?? 'latest') === 'name' ? 'name' : 'latest';

$levels = ['Expert', 'Advanced', 'Intermediate', 'Beginner'];

$where  = ['l.status = \'active\'', 'le.status = \'active\''];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(l.full_name LIKE ? OR l.staff_no LIKE ? OR ex.expertise_name LIKE ?
                 OR l.research_area LIKE ? OR l.`position` LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($dept > 0) {
    $where[]  = 'l.department_id = ?';
    $params[] = $dept;
}
if (in_array($level, $levels, true)) {
    $where[]  = 'le.expertise_level = ?';
    $params[] = $level;
}
if ($categoryId > 0) {
    $where[]  = 'ex.category_id = ?';
    $params[] = $categoryId;
}

$orderBy = $sort === 'name'
    ? 'l.full_name ASC'
    : 'l.updated_at DESC, l.lecturer_id DESC';

$sql = 'SELECT l.lecturer_id, l.full_name, l.staff_no, l.profile_photo, l.`position`,
               l.research_area, l.updated_at, d.department_name,
               COUNT(DISTINCT le.expertise_id) AS expertise_count,
               MAX(le.years_experience)        AS max_years,
               MAX(FIELD(le.expertise_level, \'Expert\', \'Advanced\', \'Intermediate\', \'Beginner\')) AS best_rank,
               GROUP_CONCAT(DISTINCT ex.expertise_name ORDER BY ex.expertise_name SEPARATOR ", ") AS expertise_list
          FROM lecturers l
          JOIN lecturer_expertise le ON le.lecturer_id = l.lecturer_id
          JOIN expertise ex          ON ex.expertise_id = le.expertise_id
          LEFT JOIN departments   d  ON d.department_id = l.department_id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY l.lecturer_id
         ORDER BY ' . $orderBy;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();

// FIELD() ranks Expert=1 ... Beginner=4; map the best rank back to a label.
$rankToLevel = [1 => 'Expert', 2 => 'Advanced', 3 => 'Intermediate', 4 => 'Beginner'];

/* --------------------------------------------------- categories side panel -- */
$categories = $pdo->query(
    'SELECT c.category_id, c.category_name,
            COUNT(DISTINCT le.lecturer_id) AS lecturer_count
       FROM expertise_categories c
       LEFT JOIN expertise e          ON e.category_id = c.category_id
       LEFT JOIN lecturer_expertise le ON le.expertise_id = e.expertise_id AND le.status = \'active\'
      WHERE c.status = \'active\'
      GROUP BY c.category_id, c.category_name
      ORDER BY lecturer_count DESC, c.category_name'
)->fetchAll();

$departments = $pdo->query(
    'SELECT department_id, department_name FROM departments WHERE status = \'active\' ORDER BY department_name'
)->fetchAll();

// Heading reflects what is being mapped.
$heading = 'Talent Mapping';
if ($categoryId > 0) {
    foreach ($categories as $c) {
        if ((int)$c['category_id'] === $categoryId) {
            $heading = $c['category_name'] . ' Experts';
            break;
        }
    }
} elseif ($q !== '') {
    $heading = 'Hasil carian: "' . $q . '"';
}
$hasFilters = $q !== '' || $dept > 0 || $level !== '' || $categoryId > 0;

$page_title = 'Talent Mapping';
$page_sub   = 'Cari dan tapis pensyarah berdasarkan kepakaran, jabatan atau tahap kepakaran';
$active     = 'mapping';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(url('admin/talent-mapping.php')) ?>">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Carian</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>"
                           placeholder="Cari nama pensyarah atau kepakaran...">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Jabatan</label>
                <select class="form-select" name="department">
                    <option value="">All Department</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>"
                            <?= $dept === (int)$d['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Tahap</label>
                <select class="form-select" name="level">
                    <option value="">All Expertise Level</option>
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= e($l) ?>" <?= $level === $l ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Susun</label>
                <select class="form-select" name="sort">
                    <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Sort by: Latest</option>
                    <option value="name"   <?= $sort === 'name' ? 'selected' : '' ?>>Sort by: Name</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Search</button>
                <?php if ($hasFilters): ?>
                    <a class="btn btn-light border" href="<?= e(url('admin/talent-mapping.php')) ?>">Reset</a>
                <?php endif; ?>
            </div>
            <?php if ($categoryId > 0): ?>
                <input type="hidden" name="category" value="<?= $categoryId ?>">
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row g-3">
    <!-- ---------------- expertise categories shortcut ---------------- -->
    <div class="col-lg-4 col-xl-3">
        <div class="card">
            <div class="card-header"><i class="bi bi-diagram-3 text-primary"></i> Expertise Categories</div>
            <div class="list-group list-group-flush">
                <?php foreach ($categories as $c): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                              <?= $categoryId === (int)$c['category_id'] ? 'active' : '' ?>"
                       href="<?= e(url('admin/talent-mapping.php?category=' . (int)$c['category_id'])) ?>">
                        <span><?= e($c['category_name']) ?></span>
                        <span class="badge <?= $categoryId === (int)$c['category_id'] ? 'text-bg-light' : 'text-bg-secondary' ?>">
                            <?= (int)$c['lecturer_count'] ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ---------------------------- results --------------------------- -->
    <div class="col-lg-8 col-xl-9">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-people-fill text-primary"></i>
                <?= e($heading) ?>
                <span class="badge text-bg-light ms-2">
                    <?= count($matches) ?> Lecturer<?= count($matches) === 1 ? '' : 's' ?>
                </span>
                <?php if ($level !== '' || $dept > 0): ?>
                    <span class="ms-2 small text-muted">
                        <?= $level !== '' ? 'tahap ' . e($level) : '' ?>
                        <?= $dept > 0 ? 'ditapis mengikut jabatan' : '' ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php if (!$matches): ?>
                    <div class="empty-state">
                        <i class="bi bi-search"></i>
                        Tiada pensyarah sepadan. Cuba kata kunci lain, atau longgarkan penapis tahap/jabatan.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($matches as $m): ?>
                            <?php
                            $photo = $m['profile_photo'] && is_file(UPLOAD_PHOTO_DIR . '/' . $m['profile_photo'])
                                ? UPLOAD_PHOTO_URL . '/' . rawurlencode($m['profile_photo'])
                                : null;
                            $bestLevel = $rankToLevel[(int)$m['best_rank']] ?? 'Beginner';
                            ?>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100 d-flex gap-3">
                                    <div class="flex-shrink-0">
                                        <?php if ($photo): ?>
                                            <img class="avatar" src="<?= e($photo) ?>" alt=""
                                                 style="width:56px;height:56px">
                                        <?php else: ?>
                                            <span class="avatar avatar-initials" style="width:56px;height:56px;font-size:18px">
                                                <?= e(initials($m['full_name'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="d-flex align-items-start gap-2">
                                            <div>
                                                <b><?= e($m['full_name']) ?></b>
                                                <div class="small text-muted"><?= e($m['department_name'] ?? '') ?></div>
                                            </div>
                                            <span class="ms-auto badge-level level-<?= e(strtolower($bestLevel)) ?>">
                                                <?= e($bestLevel) ?>
                                            </span>
                                        </div>

                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-briefcase"></i> <?= e($m['position']) ?>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="bi bi-clock-history"></i>
                                            <?= (int)$m['max_years'] ?> tahun pengalaman &middot;
                                            <?= (int)$m['expertise_count'] ?> bidang kepakaran
                                        </div>

                                        <div class="mt-2">
                                            <?php foreach (array_slice(explode(', ', (string)$m['expertise_list']), 0, 3) as $tag): ?>
                                                <span class="badge text-bg-light border me-1 mb-1"><?= e($tag) ?></span>
                                            <?php endforeach; ?>
                                            <?php if ((int)$m['expertise_count'] > 3): ?>
                                                <span class="badge text-bg-light border mb-1">
                                                    +<?= (int)$m['expertise_count'] - 3 ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <a class="btn btn-sm btn-outline-primary mt-2"
                                           href="<?= e(url('admin/lecturers-view.php?id=' . (int)$m['lecturer_id'])) ?>">
                                            View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-footer bg-white small text-muted">
                Ditapis daripada <?= (int)scalar('SELECT COUNT(*) FROM lecturers') ?> pensyarah FSTP
                berdasarkan rekod kepakaran yang disahkan.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
