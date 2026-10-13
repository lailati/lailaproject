<?php
/**
 * Admin record browser
 *
 * One page renders every lecturer record type (research, publications,
 * certifications, training, consultancy, achievements, documents) from the
 * definition in includes/record-types.php.
 *
 * The Pentadbir's role for these records is to REVIEW what the pensyarah have
 * entered (proposal: "menyemak maklumat yang dikemas kini oleh pensyarah"),
 * so this browser is read-only with search, department and status filters and
 * a CSV export - records themselves are created by the lecturer who owns them.
 *
 * Expects, set by the thin wrapper in admin/<type>.php:
 *   $browser_type   string  key into record_types()
 */

require_once __DIR__ . '/auth.php';

// auth.php pulls in functions.php (and therefore record-types.php), so the
// definition must be loaded before record_type() is called below.
require_admin();

if (!isset($browser_type) || !($type = record_type($browser_type))) {
    http_response_code(404);
    exit('Unknown record type.');
}

$pdo = db();

/* ------------------------------------------------------------------ filters */
$q        = trim((string)($_GET['q'] ?? ''));
$dept     = (int)($_GET['department'] ?? 0);
$status   = (string)($_GET['status'] ?? '');
$year     = trim((string)($_GET['year'] ?? ''));

$where  = [];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $or   = [];
    foreach ($type['search'] as $i => $column) {
        $or[] = $column . ' LIKE ?';
        $params[] = $like;
    }
    $where[] = '(' . implode(' OR ', $or) . ')';
}
if ($dept > 0) {
    $where[]  = 'l.department_id = ?';
    $params[] = $dept;
}
if ($status !== '' && !empty($type['status_values']) && in_array($status, $type['status_values'], true)) {
    $where[]  = 'x.status = ?';
    $params[] = $status;
}
if ($year !== '' && ctype_digit($year)) {
    $where[]  = 'x.`year` = ?';
    $params[] = (int)$year;
}

$sql = 'SELECT ' . $type['select'] . ',
               x.lecturer_id,
               l.full_name, l.staff_no, l.lecturer_id AS lid,
               d.department_name
          FROM ' . $type['table'] . ' x
          JOIN lecturers  l ON l.lecturer_id = x.lecturer_id
          LEFT JOIN departments d ON d.department_id = l.department_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $type['order'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ------------------------------------------------------------------- facets */
$departments = $pdo->query(
    'SELECT department_id, department_name FROM departments WHERE status = \'active\' ORDER BY department_name'
)->fetchAll();

// Only some record types have a year column (training, certifications and
// documents do not), so the facet is both queried and rendered conditionally.
$hasYear = in_array('year', array_column($type['columns'], 'key'), true);

$years = $hasYear
    ? $pdo->query(
        'SELECT DISTINCT `year` FROM ' . $type['table'] . ' WHERE `year` IS NOT NULL ORDER BY `year` DESC'
      )->fetchAll(PDO::FETCH_COLUMN)
    : [];

// Counts per department for the little summary strip.
// The primary key is read from the start of the type's select clause
// (e.g. "x.research_id AS record_id, ..." -> x.research_id).
preg_match('/^\s*([A-Za-z_.]+)\s+AS\s+record_id/i', $type['select'], $idMatch);
$idColumn = $idMatch[1] ?? null;

$byDept = $idColumn === null ? [] : $pdo->query(
    'SELECT d.department_name, COUNT(' . $idColumn . ') AS total
       FROM ' . $type['table'] . ' x
       JOIN lecturers  l ON l.lecturer_id = x.lecturer_id
       JOIN departments d ON d.department_id = l.department_id
      GROUP BY d.department_id, d.department_name
      ORDER BY total DESC'
)->fetchAll();

$total = count($rows);

/* ------------------------------------------------------------ CSV download -- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'
        . strtolower($type['label']) . '-' . date('Ymd-His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");   // BOM so Excel reads UTF-8 correctly

    $head = ['No.', 'Lecturer', 'Staff No', 'Department'];
    foreach ($type['columns'] as $col) {
        $head[] = $col['label'];
    }
    fputcsv($out, $head);

    foreach ($rows as $i => $row) {
        $line = [$i + 1, $row['full_name'], $row['staff_no'], $row['department_name']];
        foreach ($type['columns'] as $col) {
            $line[] = strip_tags((string)($row[$col['key']] ?? ''));
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$page_title = $type['label'];
$page_sub   = 'Rekod ' . strtolower($type['label']) . ' semua pensyarah — paparan semakan Pentadbir';
$active     = $browser_type;

require_once __DIR__ . '/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="kpi">
                <div class="kpi-icon kpi-blue"><i class="bi <?= e($type['icon']) ?>"></i></div>
                <div>
                    <div class="kpi-value"><?= (int)$total ?></div>
                    <div class="kpi-label"><?= e($type['label']) ?> (ditapis)</div>
                </div>
            </div>
        </div>
    </div>
    <?php foreach (array_slice($byDept, 0, 3) as $d): ?>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="kpi">
                    <div class="kpi-icon kpi-green"><i class="bi bi-building"></i></div>
                    <div>
                        <div class="kpi-value"><?= (int)$d['total'] ?></div>
                        <div class="kpi-label"><?= e($d['department_name']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi <?= e($type['icon']) ?> text-primary"></i> <?= e($type['label']) ?> Records
        <span class="badge text-bg-light ms-2"><?= (int)$total ?></span>
        <a class="btn btn-sm btn-outline-success ms-auto"
           href="<?= e(url('admin/' . $browser_type . '.php?' . http_build_query([
                   'q' => $q,
                   'department' => $dept ?: null,
                   'status' => $status ?: null,
                   'year' => $year ?: null,
                   'export' => 'csv',
               ]))) ?>">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
    </div>

    <div class="card-body pb-2">
        <form class="row g-2" method="get" action="<?= e(url('admin/' . $browser_type . '.php')) ?>">
            <div class="col-md-5 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>"
                           placeholder="Cari <?= e(strtolower($type['label'])) ?>...">
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
            <?php if (!empty($type['status_values'])): ?>
                <div class="col-6 col-md-2 col-lg-2">
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <?php foreach ($type['status_values'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                                <?= e(ucfirst($s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($hasYear && $years): ?>
                <div class="col-6 col-md-2 col-lg-1">
                    <select class="form-select" name="year">
                        <option value="">Tahun</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= (int)$y ?>" <?= $year === (string)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">Search</button>
                <a class="btn btn-light border" href="<?= e(url('admin/' . $browser_type . '.php')) ?>">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:52px">No.</th>
                    <th>Pensyarah</th>
                    <?php foreach ($type['columns'] as $col): ?>
                        <th class="<?= e($col['class'] ?? '') ?>"><?= e($col['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="<?= count($type['columns']) + 2 ?>">
                        <div class="empty-state">
                            <i class="bi <?= e($type['icon']) ?>"></i>
                            Tiada rekod <?= e(strtolower($type['label'])) ?> sepadan dengan carian anda.
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <a class="text-decoration-none"
                               href="<?= e(url('admin/lecturers-view.php?id=' . (int)$row['lecturer_id'])) ?>">
                                <b><?= e($row['full_name']) ?></b>
                            </a>
                            <br><small class="text-muted">
                                <?= e($row['staff_no']) ?>
                                <?= $row['department_name'] ? ' &middot; ' . e($row['department_name']) : '' ?>
                            </small>
                        </td>
                        <?php foreach ($type['columns'] as $col): ?>
                            <td class="<?= e($col['class'] ?? '') ?>"><?= record_cell($col, $row) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-white small text-muted">
        Paparan semakan sahaja — rekod dicipta dan dikemas kini oleh pensyarah melalui akaun masing-masing.
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
