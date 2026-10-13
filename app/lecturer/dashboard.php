<?php
/**
 * Lecturer (Pensyarah) dashboard
 *
 * Personal view: the lecturer's own record counts, profile completion and the
 * activity they have performed. All counts are scoped to this lecturer's
 * lecturer_id, so one lecturer can never see another's totals.
 */

require_once __DIR__ . '/../includes/auth.php';
require_lecturer();

$user       = current_user();
$lecturerId = (int)($user['lecturer_id'] ?? 0);
$pdo        = db();

if ($lecturerId === 0) {
    // A lecturer-role account without a lecturer profile cannot show a
    // dashboard, so point the user at the administrator instead of erroring.
    $page_title = 'Dashboard';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-warning">Akaun anda belum dipautkan kepada rekod pensyarah. '
       . 'Sila hubungi Pentadbir sistem.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

/* ----------------------------------------------------------- own counters -- */
$counts = [
    'expertise'      => scalar('SELECT COUNT(*) FROM lecturer_expertise WHERE lecturer_id = ? AND status = \'active\'', [$lecturerId]),
    'research'       => scalar('SELECT COUNT(*) FROM research       WHERE lecturer_id = ?', [$lecturerId]),
    'publications'   => scalar('SELECT COUNT(*) FROM publications   WHERE lecturer_id = ?', [$lecturerId]),
    'certifications' => scalar('SELECT COUNT(*) FROM certifications WHERE lecturer_id = ?', [$lecturerId]),
    'training'       => scalar('SELECT COUNT(*) FROM training       WHERE lecturer_id = ?', [$lecturerId]),
    'consultancy'    => scalar('SELECT COUNT(*) FROM consultancy    WHERE lecturer_id = ?', [$lecturerId]),
    'achievements'   => scalar('SELECT COUNT(*) FROM achievements   WHERE lecturer_id = ?', [$lecturerId]),
    'documents'      => scalar('SELECT COUNT(*) FROM documents      WHERE lecturer_id = ? AND status = \'active\'', [$lecturerId]),
];

/* ------------------------------------------------------------ top expertise - */
$stmt = $pdo->prepare(
    'SELECT e.expertise_name, le.expertise_level, le.years_experience
       FROM lecturer_expertise le
       JOIN expertise e ON e.expertise_id = le.expertise_id
      WHERE le.lecturer_id = :id AND le.status = \'active\'
      ORDER BY FIELD(le.expertise_level, \'Expert\', \'Advanced\', \'Intermediate\', \'Beginner\'),
               le.years_experience DESC
      LIMIT 5'
);
$stmt->execute([':id' => $lecturerId]);
$topExpertise = $stmt->fetchAll();

/* --------------------------------------------------------- recent activity -- */
$stmt = $pdo->prepare(
    'SELECT description, activity_type, created_at
       FROM activity_log
      WHERE user_id = :id
      ORDER BY created_at DESC, activity_id DESC
      LIMIT 6'
);
$stmt->execute([':id' => (int)$user['user_id']]);
$recent = $stmt->fetchAll();

$completion = profile_completion($user);

$activityIcon = [
    'insert' => 'bi-plus-circle',
    'update' => 'bi-pencil-square',
    'delete' => 'bi-trash',
    'upload' => 'bi-cloud-arrow-up',
    'login'  => 'bi-box-arrow-in-right',
];

$page_title = 'Dashboard';
$page_sub   = 'Selamat datang, ' . $user['lecturer_name']
            . ' — ' . ($user['department_name'] ?? 'FSTP');
$active     = 'dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <h5 class="mb-1"><?= e($user['lecturer_name']) ?></h5>
            <div class="text-muted small">
                <?= e($user['position'] ?? '') ?>
                &middot; <?= e($user['department_name'] ?? '') ?>, FSTP
            </div>
        </div>
        <div style="min-width:260px">
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
</div>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['expertise',      'Expertise',      'bi-lightbulb',     'kpi-blue'],
        ['research',       'Research',       'bi-journal-text',  'kpi-green'],
        ['publications',   'Publications',   'bi-book',          'kpi-purple'],
        ['certifications', 'Certifications', 'bi-patch-check',   'kpi-orange'],
    ];
    foreach ($tiles as [$key, $label, $icon, $colour]): ?>
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="kpi">
                    <div class="kpi-icon <?= e($colour) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                    <div>
                        <div class="kpi-value"><?= (int)$counts[$key] ?></div>
                        <div class="kpi-label"><?= e($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-clock-history text-danger"></i> Recent Activities
            </div>
            <div class="card-body">
                <?php if ($recent): ?>
                    <ul class="activity-list">
                        <?php foreach ($recent as $row): ?>
                            <li>
                                <i class="bi <?= e($activityIcon[$row['activity_type']] ?? 'bi-dot') ?>"></i>
                                <span><?= e($row['description']) ?></span>
                                <time datetime="<?= e($row['created_at']) ?>">
                                    <?= e(format_date_short($row['created_at'])) ?>
                                </time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-clock-history"></i>
                        Tiada aktiviti lagi. Kemas kini profil anda untuk mula.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-lightbulb text-warning"></i> My Expertise (Top 5)
            </div>
            <div class="card-body">
                <?php if ($topExpertise): ?>
                    <?php foreach ($topExpertise as $row): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><?= e($row['expertise_name']) ?></span>
                                <span class="badge-level level-<?= e(strtolower($row['expertise_level'])) ?>">
                                    <?= e($row['expertise_level']) ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Pengalaman</span>
                                <span><?= (int)$row['years_experience'] ?> tahun</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-lightbulb"></i>
                        Belum ada rekod kepakaran.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-collection text-primary"></i> My Records</div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <?php
                    $summary = [
                        ['Training',      $counts['training'],     'bi-mortarboard'],
                        ['Consultancy',   $counts['consultancy'],  'bi-briefcase'],
                        ['Achievements',  $counts['achievements'], 'bi-trophy'],
                        ['Documents',     $counts['documents'],    'bi-folder2-open'],
                    ];
                    foreach ($summary as [$label, $value, $icon]): ?>
                        <div class="col-6 col-md-3">
                            <i class="bi <?= e($icon) ?> fs-4 text-primary"></i>
                            <div class="kpi-value mt-1"><?= (int)$value ?></div>
                            <div class="kpi-label"><?= e($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <hr>
                <div class="row g-2">
                    <?php
                    $quickLinks = [
                        ['lecturer/profile.php',        'Kemas kini Profil',  'bi-person-circle'],
                        ['lecturer/expertise.php',      'Tambah Kepakaran',   'bi-lightbulb'],
                        ['lecturer/research.php',       'Tambah Penyelidikan','bi-journal-text'],
                        ['lecturer/publications.php',   'Tambah Penerbitan',  'bi-book'],
                        ['lecturer/certifications.php', 'Tambah Pensijilan',  'bi-patch-check'],
                        ['lecturer/documents.php',      'Muat Naik Dokumen',  'bi-folder2-open'],
                    ];
                    foreach ($quickLinks as [$href, $label, $icon]): ?>
                        <div class="col-6 col-md-4">
                            <a class="btn btn-sm btn-outline-primary w-100 text-start"
                               href="<?= e(url($href)) ?>">
                                <i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
