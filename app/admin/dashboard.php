<?php
/**
 * Admin (Pentadbir) dashboard
 *
 * Every number on this page is read live from the database - nothing here is
 * hard-coded, so the counters stay correct as lecturers add records.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

/* ------------------------------------------------------------------ KPIs -- */
$totalLecturers    = scalar('SELECT COUNT(*) FROM lecturers WHERE status = \'active\'');
$totalCategories   = scalar('SELECT COUNT(*) FROM expertise_categories WHERE status = \'active\'');
$totalPublications = scalar('SELECT COUNT(*) FROM publications');
$totalCertifications = scalar('SELECT COUNT(*) FROM certifications');

/* ------------------------------------------------- expertise distribution -- */
$expertiseDistribution = $pdo->query(
    'SELECT ec.category_name AS label, COUNT(DISTINCT le.lecturer_id) AS total
       FROM expertise_categories ec
       LEFT JOIN expertise e        ON e.category_id = ec.category_id
       LEFT JOIN lecturer_expertise le ON le.expertise_id = e.expertise_id
      WHERE ec.status = \'active\'
      GROUP BY ec.category_id, ec.category_name
      ORDER BY total DESC, ec.category_name
      LIMIT 10'
)->fetchAll();

/* ------------------------------------------------- lecturers by department - */
$byDepartment = $pdo->query(
    'SELECT d.department_name AS label, COUNT(l.lecturer_id) AS total
       FROM departments d
       LEFT JOIN lecturers l ON l.department_id = d.department_id AND l.status = \'active\'
      GROUP BY d.department_id, d.department_name
     HAVING total > 0
      ORDER BY total DESC, d.department_name'
)->fetchAll();

/* ------------------------------------------------------------ recent feed -- */
$recentActivity = $pdo->query(
    'SELECT a.description, a.created_at, a.activity_type,
            COALESCE(l.full_name, u.username) AS actor
       FROM activity_log a
       JOIN users u      ON u.user_id = a.user_id
       LEFT JOIN lecturers l ON l.user_id = u.user_id
      ORDER BY a.created_at DESC, a.activity_id DESC
      LIMIT 8'
)->fetchAll();

$page_title = 'Dashboard';
$page_sub   = 'Overview of FSTP Lecturers\' Talent and Expertise';
$active     = 'dashboard';
// Order matters: Chart.js must be loaded before the script that uses it, and
// both are emitted by footer.php AFTER this page's markup and inline data.
$extra_js   = ['vendor/chartjs/chart.umd.min.js', 'js/dashboard-admin.js'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="kpi">
                <div class="kpi-icon kpi-blue"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="kpi-value"><?= (int)$totalLecturers ?></div>
                    <div class="kpi-label">Lecturers</div>
                    <a class="kpi-link" href="<?= e(url('admin/users.php')) ?>">View Details &rarr;</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="kpi">
                <div class="kpi-icon kpi-green"><i class="bi bi-diagram-3"></i></div>
                <div>
                    <div class="kpi-value"><?= (int)$totalCategories ?></div>
                    <div class="kpi-label">Expertise Categories</div>
                    <span class="kpi-link text-muted">View Details &rarr;</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="kpi">
                <div class="kpi-icon kpi-purple"><i class="bi bi-book"></i></div>
                <div>
                    <div class="kpi-value"><?= (int)$totalPublications ?></div>
                    <div class="kpi-label">Research Publications</div>
                    <span class="kpi-link text-muted">View Details &rarr;</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="kpi">
                <div class="kpi-icon kpi-orange"><i class="bi bi-patch-check"></i></div>
                <div>
                    <div class="kpi-value"><?= (int)$totalCertifications ?></div>
                    <div class="kpi-label">Certifications</div>
                    <span class="kpi-link text-muted">View Details &rarr;</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-bar-chart-line text-primary"></i> Expertise Distribution
                <span class="ms-auto small text-muted">lecturers per category</span>
            </div>
            <div class="card-body">
                <?php if ($expertiseDistribution): ?>
                    <div style="height:290px"><canvas id="chartExpertise"></canvas></div>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>Tiada data kepakaran lagi.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart text-success"></i> Lecturers by Department
            </div>
            <div class="card-body">
                <?php if ($byDepartment): ?>
                    <div style="height:290px"><canvas id="chartDepartment"></canvas></div>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-pie-chart"></i>Tiada data jabatan lagi.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-activity text-danger"></i> Recent Activities</div>
            <div class="card-body">
                <?php if ($recentActivity): ?>
                    <ul class="activity-list">
                        <?php foreach ($recentActivity as $row): ?>
                            <li>
                                <i class="bi bi-clock-history"></i>
                                <span>
                                    <b><?= e($row['actor']) ?></b> &middot; <?= e($row['description']) ?>
                                </span>
                                <time datetime="<?= e($row['created_at']) ?>">
                                    <?= e(format_date_short($row['created_at'])) ?>
                                </time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-activity"></i>Tiada aktiviti direkodkan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lightning-charge text-warning"></i> Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a class="btn btn-primary" href="<?= e(url('admin/users.php')) ?>">
                    <i class="bi bi-person-plus me-2"></i>Tambah Akaun Pengguna
                </a>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/users.php?role=lecturer')) ?>">
                    <i class="bi bi-people me-2"></i>Senarai Pensyarah
                </a>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/users.php?status=inactive')) ?>">
                    <i class="bi bi-person-dash me-2"></i>Akaun Tidak Aktif
                </a>
                <hr class="my-1">
                <p class="small text-muted mb-0">
                    Modul Talent Mapping, Research, Publications, Statistics dan Reports
                    akan ditambah pada fasa seterusnya.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
/* Data comes from the database queries above, encoded safely for JS. */
window.FSTP_DASHBOARD = {
    expertise:  <?= json_encode($expertiseDistribution, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    department: <?= json_encode($byDepartment, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<?php /* Chart.js and dashboard-admin.js are queued in $extra_js above. */ ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
