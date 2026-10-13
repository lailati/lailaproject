<?php
/**
 * Admin > Statistics
 *
 * Faculty-wide picture of expertise: how it is distributed across categories,
 * departments, levels and years, plus the two things management actually acts
 * on - certifications about to expire, and which expertise areas have only one
 * person covering them.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

/* ------------------------------------------------------------------ totals -- */
$totals = [
    'lecturers'      => scalar('SELECT COUNT(*) FROM lecturers WHERE status = \'active\''),
    'departments'    => scalar('SELECT COUNT(*) FROM departments WHERE status = \'active\''),
    'categories'     => scalar('SELECT COUNT(*) FROM expertise_categories WHERE status = \'active\''),
    'expertise'      => scalar('SELECT COUNT(*) FROM lecturer_expertise WHERE status = \'active\''),
    'research'       => scalar('SELECT COUNT(*) FROM research'),
    'publications'   => scalar('SELECT COUNT(*) FROM publications'),
    'certifications' => scalar('SELECT COUNT(*) FROM certifications'),
    'training'       => scalar('SELECT COUNT(*) FROM training'),
    'consultancy'    => scalar('SELECT COUNT(*) FROM consultancy'),
    'achievements'   => scalar('SELECT COUNT(*) FROM achievements'),
    'documents'      => scalar('SELECT COUNT(*) FROM documents'),
];

/* --------------------------------------------------------------- breakdowns -- */
$byCategory = $pdo->query(
    'SELECT c.category_name AS label, COUNT(DISTINCT le.lecturer_id) AS total
       FROM expertise_categories c
       LEFT JOIN expertise e            ON e.category_id = c.category_id
       LEFT JOIN lecturer_expertise le  ON le.expertise_id = e.expertise_id AND le.status = \'active\'
      WHERE c.status = \'active\'
      GROUP BY c.category_id, c.category_name
      ORDER BY total DESC, c.category_name'
)->fetchAll();

$byLevel = $pdo->query(
    'SELECT le.expertise_level AS label, COUNT(*) AS total
       FROM lecturer_expertise le
      WHERE le.status = \'active\'
      GROUP BY le.expertise_level
      ORDER BY FIELD(le.expertise_level, \'Expert\', \'Advanced\', \'Intermediate\', \'Beginner\')'
)->fetchAll();

$byDepartment = $pdo->query(
    'SELECT d.department_name AS label, COUNT(l.lecturer_id) AS total
       FROM departments d
       LEFT JOIN lecturers l ON l.department_id = d.department_id AND l.status = \'active\'
      GROUP BY d.department_id, d.department_name
     HAVING total > 0
      ORDER BY total DESC'
)->fetchAll();

$publicationsByYear = $pdo->query(
    'SELECT `year` AS label, COUNT(*) AS total
       FROM publications
      WHERE `year` IS NOT NULL
      GROUP BY `year`
      ORDER BY `year`'
)->fetchAll();

$expertiseByYear = $pdo->query(
    'SELECT `year` AS label, COUNT(*) AS total
       FROM research
      WHERE `year` IS NOT NULL
      GROUP BY `year`
      ORDER BY `year`'
)->fetchAll();

/* ------------------------------------------------- action lists for management - */

// Certifications expiring within 90 days (or already expired but still marked active).
$expiring = $pdo->query(
    'SELECT c.certification_name, c.issuing_body, c.expiry_date, c.status,
            l.full_name, l.lecturer_id, d.department_name,
            DATEDIFF(c.expiry_date, CURDATE()) AS days_left
       FROM certifications c
       JOIN lecturers l  ON l.lecturer_id = c.lecturer_id
       LEFT JOIN departments d ON d.department_id = l.department_id
      WHERE c.expiry_date IS NOT NULL
        AND c.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
      ORDER BY c.expiry_date ASC'
)->fetchAll();

// Expertise areas covered by a single lecturer - a continuity risk.
$singleCoverage = $pdo->query(
    'SELECT e.expertise_name, c.category_name,
            COUNT(DISTINCT le.lecturer_id) AS holders,
            MIN(l.full_name) AS only_holder
       FROM expertise e
       JOIN expertise_categories c ON c.category_id = e.category_id
       JOIN lecturer_expertise le  ON le.expertise_id = e.expertise_id AND le.status = \'active\'
       JOIN lecturers l            ON l.lecturer_id = le.lecturer_id
      GROUP BY e.expertise_id, e.expertise_name, c.category_name
     HAVING holders = 1
      ORDER BY e.expertise_name'
)->fetchAll();

// Most experienced per category (the "who do I ask" list).
$topExperts = $pdo->query(
    'SELECT l.full_name, l.lecturer_id, l.staff_no, e.expertise_name,
            le.expertise_level, le.years_experience, c.category_name,
            d.department_name
       FROM lecturer_expertise le
       JOIN lecturers l  ON l.lecturer_id = le.lecturer_id
       JOIN expertise  e ON e.expertise_id = le.expertise_id
       JOIN expertise_categories c ON c.category_id = e.category_id
       LEFT JOIN departments d ON d.department_id = l.department_id
      WHERE le.status = \'active\'
      ORDER BY le.years_experience DESC, l.full_name
      LIMIT 12'
)->fetchAll();

$page_title = 'Statistics';
$page_sub   = 'Statistik kepakaran pensyarah FSTP';
$active     = 'statistics';
$extra_js   = ['vendor/chartjs/chart.umd.min.js', 'js/dashboard-statistics.js'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Pensyarah',      $totals['lecturers'],      'bi-people-fill',  'kpi-blue'],
        ['Rekod Kepakaran',$totals['expertise'],      'bi-lightbulb',    'kpi-green'],
        ['Penyelidikan',   $totals['research'],       'bi-journal-text', 'kpi-purple'],
        ['Penerbitan',     $totals['publications'],   'bi-book',         'kpi-orange'],
        ['Pensijilan',     $totals['certifications'], 'bi-patch-check',  'kpi-blue'],
        ['Latihan',        $totals['training'],       'bi-mortarboard',  'kpi-green'],
        ['Konsultansi',    $totals['consultancy'],    'bi-briefcase',    'kpi-purple'],
        ['Pencapaian',     $totals['achievements'],   'bi-trophy',       'kpi-orange'],
    ];
    foreach ($tiles as [$label, $value, $icon, $colour]): ?>
        <div class="col-6 col-md-3 col-xl-3">
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

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-bar-chart-line text-primary"></i> Expertise Distribution
                <span class="ms-auto small text-muted">pensyarah per kategori</span>
            </div>
            <div class="card-body"><div style="height:320px"><canvas id="chartCategory"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-sliders text-success"></i> Expertise Level Distribution</div>
            <div class="card-body"><div style="height:320px"><canvas id="chartLevel"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart text-warning"></i> Pensyarah mengikut Jabatan</div>
            <div class="card-body"><div style="height:300px"><canvas id="chartDepartment"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-graph-up text-primary"></i> Penerbitan &amp; Penyelidikan mengikut Tahun
            </div>
            <div class="card-body"><div style="height:300px"><canvas id="chartYears"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-exclamation-triangle text-danger"></i> Pensijilan Luput / Hampir Luput
                <span class="badge text-bg-danger ms-2"><?= count($expiring) ?></span>
                <span class="ms-auto small text-muted">dalam 90 hari</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Pensyarah</th><th>Pensijilan</th><th>Luput</th><th class="text-center">Baki</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$expiring): ?>
                        <tr><td colspan="4"><div class="empty-state">
                            <i class="bi bi-shield-check"></i>Tiada pensijilan hampir luput.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($expiring as $c): ?>
                            <tr>
                                <td>
                                    <a class="text-decoration-none"
                                       href="<?= e(url('admin/lecturers-view.php?id=' . (int)$c['lecturer_id'])) ?>">
                                        <?= e($c['full_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= e($c['department_name'] ?? '') ?></small>
                                </td>
                                <td>
                                    <small><?= e($c['certification_name']) ?></small>
                                    <br><small class="text-muted"><?= e($c['issuing_body']) ?></small>
                                </td>
                                <td><small><?= e(format_date($c['expiry_date'])) ?></small></td>
                                <td class="text-center">
                                    <?php $d = (int)$c['days_left']; ?>
                                    <?php if ($d < 0): ?>
                                        <span class="badge text-bg-danger"><?= abs($d) ?>h lepas</span>
                                    <?php elseif ($d <= 30): ?>
                                        <span class="badge text-bg-danger"><?= $d ?>h</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning"><?= $d ?>h</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-person-exclamation text-warning"></i> Bidang Kepakaran Berisiko
                <span class="badge text-bg-warning ms-2"><?= count($singleCoverage) ?></span>
                <span class="ms-auto small text-muted">hanya 1 pensyarah</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Bidang Kepakaran</th><th>Kategori</th><th>Pemegang Tunggal</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$singleCoverage): ?>
                        <tr><td colspan="3"><div class="empty-state">
                            <i class="bi bi-people"></i>Setiap bidang mempunyai lebih daripada satu pemegang.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($singleCoverage as $s): ?>
                            <tr>
                                <td><b><?= e($s['expertise_name']) ?></b></td>
                                <td><small class="text-muted"><?= e($s['category_name']) ?></small></td>
                                <td><small><?= e($s['only_holder']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                Bidang dengan seorang sahaja pemegang berisiko hilang sekiranya pensyarah tersebut berpindah atau bersara.
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-award text-success"></i> Pensyarah Paling Berpengalaman</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:52px">No.</th>
                            <th>Pensyarah</th>
                            <th>Jabatan</th>
                            <th>Bidang Kepakaran</th>
                            <th>Kategori</th>
                            <th>Tahap</th>
                            <th class="text-center">Tahun</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topExperts as $i => $e): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <a class="text-decoration-none"
                                   href="<?= e(url('admin/lecturers-view.php?id=' . (int)$e['lecturer_id'])) ?>">
                                    <?= e($e['full_name']) ?>
                                </a>
                                <br><small class="text-muted"><?= e($e['staff_no']) ?></small>
                            </td>
                            <td><small><?= e($e['department_name'] ?? '—') ?></small></td>
                            <td><?= e($e['expertise_name']) ?></td>
                            <td><small class="text-muted"><?= e($e['category_name']) ?></small></td>
                            <td>
                                <span class="badge-level level-<?= e(strtolower($e['expertise_level'])) ?>">
                                    <?= e($e['expertise_level']) ?>
                                </span>
                            </td>
                            <td class="text-center"><?= (int)$e['years_experience'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
window.FSTP_STATS = {
    category:   <?= json_encode($byCategory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    level:      <?= json_encode($byLevel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    department: <?= json_encode($byDepartment, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    publicationsByYear: <?= json_encode($publicationsByYear, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    researchByYear:     <?= json_encode($expertiseByYear, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
