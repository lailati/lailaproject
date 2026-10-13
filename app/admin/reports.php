<?php
/**
 * Admin > Reports
 *
 * Generates the four reports FSTP management asked for, each filterable and
 * exportable. Every report is built from one definition (title, columns, rows,
 * summary) so printing and CSV export work for all of them identically.
 *
 *   1. expertise-report      lecturers by expertise (a CV-style mapping list)
 *   2. department-report     expertise and output per department
 *   3. certification-report  certification expiry tracking
 *   4. publication-report    publications by year and department
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

$reportKey = (string)($_GET['report'] ?? 'expertise');
$dept      = (int)($_GET['department'] ?? 0);
$category  = (int)($_GET['category'] ?? 0);
$yearFrom  = trim((string)($_GET['year_from'] ?? ''));
$yearTo    = trim((string)($_GET['year_to'] ?? ''));
$generated = isset($_GET['generate']) || isset($_GET['export']);

$reports = [
    'expertise'      => 'Laporan Pemetaan Kepakaran Pensyarah',
    'department'     => 'Laporan Ringkasan Jabatan',
    'certification'  => 'Laporan Status Pensijilan Profesional',
    'publication'    => 'Laporan Penerbitan dan Penyelidikan',
];

if (!isset($reports[$reportKey])) {
    $reportKey = 'expertise';
}

$departments = $pdo->query(
    'SELECT department_id, department_name FROM departments WHERE status = \'active\' ORDER BY department_name'
)->fetchAll();

$categories = $pdo->query(
    'SELECT category_id, category_name FROM expertise_categories WHERE status = \'active\' ORDER BY category_name'
)->fetchAll();

/* =========================================================================
   BUILD THE SELECTED REPORT
   ========================================================================= */
$title   = $reports[$reportKey];
$columns = [];
$rows    = [];
$summary = [];

if ($generated) {

    if ($reportKey === 'expertise') {
        $where  = ['l.status = \'active\''];
        $params = [];
        if ($dept > 0) {
            $where[]  = 'l.department_id = ?';
            $params[] = $dept;
        }
        if ($category > 0) {
            $where[]  = 'ex.category_id = ?';
            $params[] = $category;
        }

        $stmt = $pdo->prepare(
            'SELECT l.full_name, l.staff_no, d.department_name, l.`position`,
                    e.expertise_name, c.category_name, le.expertise_level, le.years_experience
               FROM lecturer_expertise le
               JOIN lecturers l  ON l.lecturer_id = le.lecturer_id
               JOIN expertise  e ON e.expertise_id = le.expertise_id
               JOIN expertise_categories c ON c.category_id = e.category_id
               LEFT JOIN departments d ON d.department_id = l.department_id
              WHERE ' . implode(' AND ', $where) . ' AND le.status = \'active\'
              ORDER BY d.department_name, l.full_name,
                       FIELD(le.expertise_level, \'Expert\', \'Advanced\', \'Intermediate\', \'Beginner\')'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $columns = [
            ['key' => 'staff_no',         'label' => 'Staff No'],
            ['key' => 'full_name',        'label' => 'Nama Pensyarah'],
            ['key' => 'department_name',  'label' => 'Jabatan'],
            ['key' => 'position',         'label' => 'Jawatan'],
            ['key' => 'expertise_name',   'label' => 'Bidang Kepakaran'],
            ['key' => 'category_name',    'label' => 'Kategori'],
            ['key' => 'expertise_level',  'label' => 'Tahap'],
            ['key' => 'years_experience', 'label' => 'Tahun'],
        ];
        $summary = [
            'Jumlah rekod kepakaran' => count($rows),
            'Pensyarah terlibat'     => count(array_unique(array_column($rows, 'staff_no'))),
        ];
    }

    if ($reportKey === 'department') {
        $params = [];
        $having = '';
        if ($dept > 0) {
            $having = ' WHERE d.department_id = ?';
            $params[] = $dept;
        }

        $stmt = $pdo->prepare(
            'SELECT d.department_name,
                    COUNT(DISTINCT l.lecturer_id) AS lecturers,
                    COUNT(DISTINCT le.lecturer_id, le.expertise_id) AS expertise_records,
                    COUNT(DISTINCT r.research_id)     AS research_count,
                    COUNT(DISTINCT p.publication_id)  AS publication_count,
                    COUNT(DISTINCT c.certification_id) AS certification_count
               FROM departments d
               LEFT JOIN lecturers l ON l.department_id = d.department_id
               LEFT JOIN lecturer_expertise le ON le.lecturer_id = l.lecturer_id AND le.status = \'active\'
               LEFT JOIN research r       ON r.lecturer_id = l.lecturer_id
               LEFT JOIN publications p   ON p.lecturer_id = l.lecturer_id
               LEFT JOIN certifications c ON c.lecturer_id = l.lecturer_id
               ' . $having . '
              GROUP BY d.department_id, d.department_name
              ORDER BY lecturers DESC, d.department_name'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $columns = [
            ['key' => 'department_name',   'label' => 'Jabatan'],
            ['key' => 'lecturers',         'label' => 'Pensyarah'],
            ['key' => 'expertise_records', 'label' => 'Rekod Kepakaran'],
            ['key' => 'research_count',    'label' => 'Penyelidikan'],
            ['key' => 'publication_count', 'label' => 'Penerbitan'],
            ['key' => 'certification_count', 'label' => 'Pensijilan'],
        ];
        $summary = [
            'Jumlah jabatan'  => count($rows),
            'Jumlah pensyarah' => array_sum(array_column($rows, 'lecturers')),
        ];
    }

    if ($reportKey === 'certification') {
        $where  = [];
        $params = [];
        if ($dept > 0) {
            $where[]  = 'l.department_id = ?';
            $params[] = $dept;
        }
        $whereSql = $where ? ' AND ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare(
            'SELECT l.full_name, l.staff_no, d.department_name,
                    c.certification_name, c.issuing_body, c.certificate_number,
                    c.issue_date, c.expiry_date, c.status,
                    DATEDIFF(c.expiry_date, CURDATE()) AS days_left
               FROM certifications c
               JOIN lecturers l ON l.lecturer_id = c.lecturer_id
               LEFT JOIN departments d ON d.department_id = l.department_id
              WHERE 1 = 1' . $whereSql . '
              ORDER BY c.expiry_date IS NULL, c.expiry_date ASC, l.full_name'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $columns = [
            ['key' => 'staff_no',           'label' => 'Staff No'],
            ['key' => 'full_name',          'label' => 'Nama Pensyarah'],
            ['key' => 'department_name',    'label' => 'Jabatan'],
            ['key' => 'certification_name', 'label' => 'Pensijilan'],
            ['key' => 'issuing_body',       'label' => 'Badan Pengeluar'],
            ['key' => 'certificate_number', 'label' => 'No. Sijil'],
            ['key' => 'issue_date',         'label' => 'Tarikh Keluar'],
            ['key' => 'expiry_date',        'label' => 'Tarikh Luput'],
            ['key' => 'status',             'label' => 'Status'],
        ];

        $soon = 0;
        foreach ($rows as $r) {
            if ($r['days_left'] !== null && (int)$r['days_left'] <= 90) {
                $soon++;
            }
        }
        $summary = [
            'Jumlah pensijilan' => count($rows),
            'Luput / hampir luput (90 hari)' => $soon,
        ];
    }

    if ($reportKey === 'publication') {
        $where  = [];
        $params = [];
        if ($dept > 0) {
            $where[]  = 'l.department_id = ?';
            $params[] = $dept;
        }
        if ($yearFrom !== '' && ctype_digit($yearFrom)) {
            $where[]  = 'p.`year` >= ?';
            $params[] = (int)$yearFrom;
        }
        if ($yearTo !== '' && ctype_digit($yearTo)) {
            $where[]  = 'p.`year` <= ?';
            $params[] = (int)$yearTo;
        }
        $whereSql = $where ? ' AND ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare(
            'SELECT l.full_name, l.staff_no, d.department_name,
                    p.title, p.publication_type, p.journal_publisher, p.`year`, p.status, p.doi_link,
                    (SELECT COUNT(*) FROM research r WHERE r.lecturer_id = l.lecturer_id) AS research_count
               FROM publications p
               JOIN lecturers l ON l.lecturer_id = p.lecturer_id
               LEFT JOIN departments d ON d.department_id = l.department_id
              WHERE 1 = 1' . $whereSql . '
              ORDER BY p.`year` DESC, l.full_name'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $columns = [
            ['key' => 'staff_no',          'label' => 'Staff No'],
            ['key' => 'full_name',         'label' => 'Nama Pensyarah'],
            ['key' => 'department_name',   'label' => 'Jabatan'],
            ['key' => 'title',             'label' => 'Tajuk Penerbitan'],
            ['key' => 'publication_type',  'label' => 'Jenis'],
            ['key' => 'journal_publisher', 'label' => 'Jurnal / Penerbit'],
            ['key' => 'year',              'label' => 'Tahun'],
            ['key' => 'status',            'label' => 'Status'],
        ];
        $summary = [
            'Jumlah penerbitan' => count($rows),
            'Pensyarah terlibat' => count(array_unique(array_column($rows, 'staff_no'))),
        ];
    }
}

/* ------------------------------------------------------------------ export -- */
if ($generated && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $reportKey . '-report-' . date('Ymd-His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, [$title]);
    fputcsv($out, [APP_UNIVERSITY . ' - ' . APP_FACULTY]);
    fputcsv($out, ['Dijana: ' . date('d/m/Y H:i')]);
    foreach ($summary as $label => $value) {
        fputcsv($out, [$label, $value]);
    }
    fputcsv($out, []);

    fputcsv($out, array_map(fn($c) => $c['label'], $columns));
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $value = $row[$col['key']] ?? '';
            if (in_array($col['key'], ['issue_date', 'expiry_date'], true)) {
                $value = format_date($value);
            }
            $line[] = $value;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$page_title = 'Reports';
$page_sub   = 'Jana laporan kepakaran, jabatan, pensijilan dan penerbitan';
$active     = 'reports';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3 d-print-none">
    <div class="card-header"><i class="bi bi-funnel text-primary"></i> Pilih Laporan</div>
    <div class="card-body">
        <form class="row g-3" method="get" action="<?= e(url('admin/reports.php')) ?>">
            <div class="col-md-4">
                <label class="form-label" for="report">Jenis Laporan</label>
                <select class="form-select" id="report" name="report" required>
                    <?php foreach ($reports as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $reportKey === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="department">Jabatan</label>
                <select class="form-select" id="department" name="department">
                    <option value="">Semua Jabatan</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['department_id'] ?>"
                            <?= $dept === (int)$d['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="category">Kategori Kepakaran</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['category_id'] ?>"
                            <?= $category === (int)$c['category_id'] ? 'selected' : '' ?>>
                            <?= e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Berkaitan laporan pemetaan kepakaran.</div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Julat Tahun</label>
                <div class="input-group">
                    <input class="form-control" type="number" name="year_from" min="1990" max="2100"
                           placeholder="Dari" value="<?= e($yearFrom) ?>">
                    <input class="form-control" type="number" name="year_to" min="1990" max="2100"
                           placeholder="Hingga" value="<?= e($yearTo) ?>">
                </div>
                <div class="form-text">Untuk laporan penerbitan.</div>
            </div>

            <div class="col-12 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" type="submit" name="generate" value="1">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i>Jana Laporan
                </button>
                <?php if ($generated): ?>
                    <button class="btn btn-outline-success" type="submit" name="export" value="csv">
                        <i class="bi bi-filetype-csv me-1"></i>Export CSV
                    </button>
                    <button class="btn btn-outline-secondary" type="button" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Cetak / PDF
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!$generated): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-file-earmark-text"></i>
                Pilih jenis laporan dan penapis, kemudian klik <b>Jana Laporan</b>.
            </div>
            <div class="row g-3 mt-2">
                <?php foreach ($reports as $key => $label): ?>
                    <div class="col-md-6">
                        <a class="border rounded-3 p-3 d-block text-decoration-none h-100"
                           href="<?= e(url('admin/reports.php?report=' . $key . '&generate=1')) ?>">
                            <b><?= e($label) ?></b>
                            <div class="small text-muted mt-1">
                                <?php
                                echo match ($key) {
                                    'expertise'     => 'Setiap bidang kepakaran beserta pensyarah yang memilikinya.',
                                    'department'    => 'Bilangan pensyarah, kepakaran dan output setiap jabatan.',
                                    'certification' => 'Status dan tarikh luput pensijilan profesional.',
                                    'publication'   => 'Senarai penerbitan mengikut tahun dan jabatan.',
                                };
                                ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php else: ?>

    <div class="card">
        <div class="card-body">
            <div class="text-center mb-3">
                <div class="fw-bold"><?= e(APP_UNIVERSITY) ?></div>
                <div class="small"><?= e(APP_FACULTY) ?></div>
                <h5 class="mt-3 mb-1"><?= e($title) ?></h5>
                <div class="small text-muted">
                    Dijana pada <?= date('d/m/Y H:i') ?>
                    <?php if ($dept > 0): ?>
                        <?php foreach ($departments as $d): ?>
                            <?php if ((int)$d['department_id'] === $dept): ?>
                                &middot; Jabatan: <?= e($d['department_name']) ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($category > 0): ?>
                        <?php foreach ($categories as $c): ?>
                            <?php if ((int)$c['category_id'] === $category): ?>
                                &middot; Kategori: <?= e($c['category_name']) ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($summary): ?>
                <div class="row g-2 mb-3">
                    <?php foreach ($summary as $label => $value): ?>
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-2 text-center">
                                <div class="kpi-value" style="font-size:20px"><?= e((string)$value) ?></div>
                                <div class="kpi-label"><?= e($label) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:48px">No.</th>
                            <?php foreach ($columns as $col): ?>
                                <th><?= e($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="<?= count($columns) + 1 ?>" class="text-center text-muted py-4">
                                Tiada rekod untuk penapis ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <?php foreach ($columns as $col): ?>
                                    <?php
                                    $key   = $col['key'];
                                    $value = $row[$key] ?? null;
                                    if (in_array($key, ['issue_date', 'expiry_date'], true)) {
                                        $value = format_date($value);
                                    }
                                    ?>
                                    <td>
                                        <?php if ($key === 'expertise_level' && $value): ?>
                                            <span class="badge-level level-<?= e(strtolower((string)$value)) ?>">
                                                <?= e((string)$value) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= $value === null || $value === '' ? '—' : e((string)$value) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
