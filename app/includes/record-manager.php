<?php
/**
 * Lecturer record manager
 *
 * One page drives add / edit / delete for every record type a Pensyarah owns:
 * research, publications, certifications, training, consultancy, achievements
 * and documents.
 *
 * It is driven entirely by includes/record-types.php (table, display columns)
 * and includes/record-fields.php (form inputs + validation), so the seven
 * modules are 7-line wrappers rather than seven near-identical pages.
 *
 * Security rules enforced here:
 *   - every read and write is scoped by lecturer_id from the SESSION, never
 *     from the request, so one lecturer can never see or touch another's row
 *     even by editing the hidden record_id in the form
 *   - a missing/foreign record id is reported, not silently ignored
 *   - uploads go through store_upload() (extension + content-MIME + size
 *     checks, generated filename)
 *   - replacing or deleting a record removes the file it owned
 *
 * Expects, set by the wrapper in lecturer/<type>.php:
 *   $manager_type   string  key into record_types()
 */

require_once __DIR__ . '/auth.php';
require_lecturer();

$user = current_user();
$lecturerId = (int)($user['lecturer_id'] ?? 0);

if ($lecturerId === 0 || !isset($manager_type) || !($type = record_type($manager_type))) {
    flash('danger', 'Modul ini tidak tersedia untuk akaun anda.');
    redirect(home_for_role());
}

$fields = record_fields($manager_type);
$table  = $type['table'];
$pk     = record_pk($manager_type);
$pdo    = db();

if ($pk === null) {
    http_response_code(500);
    exit('Record type "' . e($manager_type) . '" has no primary key defined.');
}

$errors  = [];
$form    = [];      // repopulates the form after a validation failure
$editing = null;    // the row being edited

/* =========================================================================
   ACTIONS
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        flash('danger', 'Sesi tidak sah. Sila cuba sekali lagi.');
        redirect('lecturer/' . $manager_type . '.php');
    }

    $action   = (string)($_POST['action'] ?? '');
    $recordId = (int)($_POST['record_id'] ?? 0);

    /* ------------------------------------------------------------- delete -- */
    if ($action === 'delete') {
        // Ownership is part of the WHERE clause: a forged id simply finds nothing.
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$pk} = :id AND lecturer_id = :lecturer_id");
        $stmt->execute([':id' => $recordId, ':lecturer_id' => $lecturerId]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('danger', 'Rekod tidak dijumpai, atau ia bukan milik anda.');
            redirect('lecturer/' . $manager_type . '.php');
        }

        // Remove any file the row owned.
        foreach ($fields as $field) {
            if ($field['type'] !== 'file') {
                continue;
            }
            foreach (['path_field', 'name_field', 'size_field'] as $which) {
                $column = $field['file'][$which] ?? null;
                if ($column && in_array($which, ['path_field'], true) && !empty($row[$column])) {
                    delete_stored_file((string)$row[$column]);
                }
            }
            // supporting_document stores the stored filename in one column
            if (isset($field['file']['path_field'])
                && $field['file']['path_field'] === 'supporting_document'
                && !empty($row['supporting_document'])) {
                delete_stored_file((string)$row['supporting_document']);
            }
        }

        $pdo->prepare("DELETE FROM {$table} WHERE {$pk} = :id AND lecturer_id = :lecturer_id")
            ->execute([':id' => $recordId, ':lecturer_id' => $lecturerId]);

        log_activity((int)$user['user_id'], 'delete', $table, $recordId,
            'Deleted ' . $type['singular']);

        flash('success', ucfirst($type['singular']) . ' berjaya dipadam.');
        redirect('lecturer/' . $manager_type . '.php');
    }

    /* ------------------------------------------------------ create/update -- */
    if ($action === 'create' || $action === 'update') {

        $values = [];
        $form   = $_POST;

        foreach ($fields as $field) {
            $name = $field['name'];

            /* ---- file inputs are handled after the text validation ---- */
            if ($field['type'] === 'file') {
                $submitted = isset($_FILES[$name]) && (int)($_FILES[$name]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($field['required'] ?? false) {
                    // Required only when creating: when editing, the existing file is kept.
                    $hasExisting = $action === 'update' && $recordId > 0;
                    if (!$submitted && !$hasExisting) {
                        $errors[] = $field['label'] . ' diperlukan.';
                    }
                }
                continue;
            }

            $raw = trim((string)($_POST[$name] ?? ''));

            if (($field['required'] ?? false) && $raw === '') {
                $errors[] = $field['label'] . ' diperlukan.';
                continue;
            }

            if ($raw === '') {
                $values[$name] = null;
                continue;
            }

            switch ($field['type']) {
                case 'number':
                    if (!ctype_digit($raw)) {
                        $errors[] = $field['label'] . ' mesti nombor bulat.';
                        break;
                    }
                    $n = (int)$raw;
                    if (isset($field['min']) && $n < $field['min']) {
                        $errors[] = $field['label'] . ' mesti sekurang-kurangnya ' . $field['min'] . '.';
                    } elseif (isset($field['max']) && $n > $field['max']) {
                        $errors[] = $field['label'] . ' tidak boleh melebihi ' . $field['max'] . '.';
                    } elseif ($field['name'] === 'year' && ($n < 1990 || $n > (int)date('Y') + 5)) {
                        $errors[] = $field['label'] . ' tidak munasabah.';
                    } else {
                        $values[$name] = $n;
                    }
                    break;

                case 'date':
                    $d = DateTime::createFromFormat('Y-m-d', $raw);
                    if (!$d || $d->format('Y-m-d') !== $raw) {
                        $errors[] = $field['label'] . ' bukan tarikh yang sah.';
                    } else {
                        $values[$name] = $raw;
                    }
                    break;

                case 'select':
                    if (!in_array($raw, $field['options'] ?? [], true)) {
                        $errors[] = $field['label'] . ' mengandungi pilihan yang tidak sah.';
                    } else {
                        $values[$name] = $raw;
                    }
                    break;

                default: // text, textarea
                    $max = $field['max'] ?? 255;
                    if (mb_strlen($raw) > $max) {
                        $errors[] = $field['label'] . ' tidak boleh melebihi ' . $max . ' aksara.';
                    } else {
                        $values[$name] = $raw;
                    }
            }
        }

        // An expiry date before the issue date is a data-entry mistake worth stopping.
        if (!empty($values['issue_date']) && !empty($values['expiry_date'])
            && $values['expiry_date'] < $values['issue_date']) {
            $errors[] = 'Tarikh luput tidak boleh sebelum tarikh keluar sijil.';
        }

        /* ---------------------------------------------------------- uploads -- */
        $uploadedFiles = [];
        if (!$errors) {
            foreach ($fields as $field) {
                if ($field['type'] !== 'file') {
                    continue;
                }
                $name = $field['name'];

                if (!isset($_FILES[$name]) || (int)($_FILES[$name]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $isPhoto = false;
                $result  = store_upload(
                    $_FILES[$name],
                    $isPhoto ? ALLOWED_IMAGE_EXT : ALLOWED_DOC_EXT,
                    $isPhoto ? ALLOWED_IMAGE_MIME : ALLOWED_DOC_MIME,
                    MAX_DOC_BYTES,
                    UPLOAD_DOC_DIR
                );

                if (!$result['ok']) {
                    $errors[] = $field['label'] . ': ' . $result['error'];
                    continue;
                }

                $uploadedFiles[] = $result['stored'];

                if (isset($field['file']['name_field'])) {
                    $values[$field['file']['name_field']] = $result['original'];
                }
                if (isset($field['file']['path_field'])) {
                    $values[$field['file']['path_field']] = $result['stored'];
                }
                if (isset($field['file']['size_field'])) {
                    $values[$field['file']['size_field']] = $result['size'];
                }
            }
        }

        /* ----------------------------------------------------------- write -- */
        if (!$errors) {
            try {
                if ($action === 'create') {
                    $values['lecturer_id'] = $lecturerId;

                    // documents also record the uploader (the admin can upload too)
                    if (!empty($type['owner_user_column'])) {
                        $values[$type['owner_user_column']] = (int)$user['user_id'];
                    }

                    $columns = array_keys($values);
                    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
                         . implode(', ', array_map(fn($c) => ':' . $c, $columns)) . ')';
                    $stmt = $pdo->prepare($sql);
                    foreach ($values as $column => $value) {
                        $stmt->bindValue(':' . $column, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    }
                    $stmt->execute();
                    $newId = (int)$pdo->lastInsertId();

                    log_activity((int)$user['user_id'], $uploadedFiles ? 'upload' : 'insert', $table, $newId,
                        'Added new ' . $type['singular']);

                    flash('success', ucfirst($type['singular']) . ' berjaya disimpan.');
                    redirect('lecturer/' . $manager_type . '.php');
                }

                /* ---- update: still scoped to this lecturer's own row ---- */
                $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$pk} = :id AND lecturer_id = :lecturer_id");
                $stmt->execute([':id' => $recordId, ':lecturer_id' => $lecturerId]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    flash('danger', 'Rekod tidak dijumpai, atau ia bukan milik anda.');
                    redirect('lecturer/' . $manager_type . '.php');
                }

                // If a new file replaced an old one, remove the old file.
                foreach ($fields as $field) {
                    if ($field['type'] !== 'file') {
                        continue;
                    }
                    $pathColumn = $field['file']['path_field'] ?? null;
                    if ($pathColumn && isset($values[$pathColumn]) && !empty($existing[$pathColumn])) {
                        delete_stored_file((string)$existing[$pathColumn]);
                    }
                }

                $assignments = [];
                foreach (array_keys($values) as $column) {
                    $assignments[] = $column . ' = :' . $column;
                }
                $sql = "UPDATE {$table} SET " . implode(', ', $assignments)
                     . " WHERE {$pk} = :pk AND lecturer_id = :lecturer_id";
                $stmt = $pdo->prepare($sql);
                foreach ($values as $column => $value) {
                    $stmt->bindValue(':' . $column, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $stmt->bindValue(':pk', $recordId, PDO::PARAM_INT);
                $stmt->bindValue(':lecturer_id', $lecturerId, PDO::PARAM_INT);
                $stmt->execute();

                log_activity((int)$user['user_id'], $uploadedFiles ? 'upload' : 'update', $table, $recordId,
                    'Updated ' . $type['singular']);

                flash('success', ucfirst($type['singular']) . ' berjaya dikemas kini.');
                redirect('lecturer/' . $manager_type . '.php');
            } catch (Throwable $ex) {
                // Roll back the file we just stored so a failed write does not
                // leave an orphan on disk.
                foreach ($uploadedFiles as $stored) {
                    delete_stored_file($stored);
                }
                $errors[] = APP_DEBUG
                    ? 'Gagal menyimpan: ' . $ex->getMessage()
                    : 'Gagal menyimpan rekod. Sila cuba lagi.';
            }
        }
    }
}

/* =========================================================================
   DATA FOR THE PAGE
   ========================================================================= */

// Edit mode: ?edit=<id>, ownership enforced in the query.
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$pk} = :id AND lecturer_id = :lecturer_id");
    $stmt->execute([':id' => (int)$_GET['edit'], ':lecturer_id' => $lecturerId]);
    $editing = $stmt->fetch() ?: null;

    if (!$editing) {
        flash('danger', 'Rekod yang anda cuba edit tidak dijumpai.');
        redirect('lecturer/' . $manager_type . '.php');
    }
    if (!$form) {
        $form = $editing;
    }
}

// Search within the lecturer's own records.
$q = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT ' . $type['select'] . ' FROM ' . $table . ' x WHERE x.lecturer_id = :lecturer_id';
$params = [':lecturer_id' => $lecturerId];

if ($q !== '') {
    $like = '%' . $q . '%';
    $or   = [];
    foreach ($type['search'] as $i => $column) {
        $column = str_replace('x.', 'x.', $column);
        $or[] = $column . ' LIKE :like' . $i;
        $params[':like' . $i] = $like;
    }
    $sql .= ' AND (' . implode(' OR ', $or) . ')';
}
$sql .= ' ORDER BY ' . $type['order'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$totalOwn = scalar("SELECT COUNT(*) FROM {$table} WHERE lecturer_id = ?", [$lecturerId]);

/* =========================================================================
   RENDER
   ========================================================================= */
$page_title = 'My ' . $type['label'];
$page_sub   = $editing
    ? 'Kemas kini ' . $type['singular']
    : 'Rekod ' . strtolower($type['label']) . ' anda';
$active     = $manager_type;

/**
 * Render one form control from its field definition.
 */
function render_record_field(array $field, $value): string
{
    $name  = $field['name'];
    $id    = 'f_' . $name;
    $label = e($field['label']);
    $req   = !empty($field['required']) ? ' <span class="text-danger">*</span>' : '';
    $val   = is_array($value) ? '' : (string)($value ?? '');
    $reqAttr = !empty($field['required']) ? 'required' : '';
    $help  = !empty($field['help'])
        ? '<div class="form-text">' . e($field['help']) . '</div>'
        : '';

    $html = '<label class="form-label" for="' . $id . '">' . $label . $req . '</label>';

    switch ($field['type']) {
        case 'textarea':
            $html .= '<textarea class="form-control" id="' . $id . '" name="' . $name . '" rows="3" '
                   . $reqAttr . '>' . e($val) . '</textarea>';
            break;

        case 'number':
            $html .= '<input class="form-control" type="number" id="' . $id . '" name="' . $name . '" '
                   . 'value="' . e($val) . '" '
                   . (isset($field['min']) ? 'min="' . (int)$field['min'] . '" ' : '')
                   . (isset($field['max']) ? 'max="' . (int)$field['max'] . '" ' : '')
                   . 'step="1" ' . $reqAttr . '>';
            break;

        case 'date':
            $html .= '<input class="form-control" type="date" id="' . $id . '" name="' . $name . '" '
                   . 'value="' . e($val) . '" ' . $reqAttr . '>';
            break;

        case 'select':
            $html .= '<select class="form-select" id="' . $id . '" name="' . $name . '" ' . $reqAttr . '>';
            $html .= '<option value="">— Pilih —</option>';
            foreach (($field['options'] ?? []) as $option) {
                $selected = ((string)$option === $val) ? ' selected' : '';
                $html .= '<option value="' . e($option) . '"' . $selected . '>' . e($option) . '</option>';
            }
            $html .= '</select>';
            break;

        case 'file':
            // The current file (when editing) is shown by the caller above the
            // control; a new upload replaces it.
            $html .= '<input class="form-control" type="file" id="' . $id . '" name="' . $name . '" '
                   . ($reqAttr && !isset($_GET['edit']) ? 'required' : '') . '>';
            break;

        default:
            $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" '
                   . 'value="' . e($val) . '" maxlength="' . (int)($field['max'] ?? 255) . '" '
                   . $reqAttr . '>';
    }

    return $html . $help;
}

require_once __DIR__ . '/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <b>Sila betulkan perkara berikut:</b>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- ===================== FORM ===================== -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi <?= e($type['icon']) ?> text-primary"></i>
                <?= $editing ? 'Edit ' . e(ucfirst($type['singular'])) : 'Add New ' . e(ucfirst($type['singular'])) ?>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data"
                      action="<?= e(url('lecturer/' . $manager_type . '.php')) ?>" class="no-double-submit">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="record_id" value="<?= (int)$editing[$pk] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <?php foreach ($fields as $field):
                            $value = $form[$field['name']] ?? ($editing[$field['name']] ?? null);
                        ?>
                            <div class="col-md-<?= (int)($field['col'] ?? 12) ?>">
                                <?= render_record_field($field, $value) ?>

                                <?php if ($field['type'] === 'file' && $editing):
                                    $existingPath = $editing[$field['file']['path_field'] ?? ''] ?? null;
                                    $existingUrl  = $existingPath ? document_url($existingPath) : null;
                                ?>
                                    <?php if ($existingUrl): ?>
                                        <div class="small mt-1">
                                            Fail semasa:
                                            <a href="<?= e($existingUrl) ?>" target="_blank" rel="noopener">lihat</a>
                                            <span class="text-muted">(muat naik baharu akan menggantikannya)</span>
                                        </div>
                                    <?php elseif ($existingPath): ?>
                                        <div class="small text-muted mt-1">
                                            Fail direkodkan tetapi tiada pada pelayan.
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <?php if ($editing): ?>
                            <a class="btn btn-light border" href="<?= e(url('lecturer/' . $manager_type . '.php')) ?>">Cancel</a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i><?= $editing ? 'Save Changes' : 'Save' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================== LIST ===================== -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <i class="bi <?= e($type['icon']) ?> text-primary"></i> <?= e($type['label']) ?> Saya
                <span class="badge text-bg-light ms-2"><?= (int)$totalOwn ?></span>
                <a class="ms-auto small text-decoration-none"
                   href="<?= e(url('lecturer/dashboard.php')) ?>">Dashboard</a>
            </div>

            <div class="card-body pb-2">
                <form class="row g-2" method="get" action="<?= e(url('lecturer/' . $manager_type . '.php')) ?>">
                    <div class="col">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input class="form-control" type="search" name="q" value="<?= e($q) ?>"
                                   placeholder="Cari dalam rekod saya...">
                        </div>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if ($q !== ''): ?>
                            <a class="btn btn-light border" href="<?= e(url('lecturer/' . $manager_type . '.php')) ?>">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:48px">No.</th>
                            <?php foreach ($type['columns'] as $col): ?>
                                <?php if (in_array($col['key'], ['updated_at'], true)) continue; ?>
                                <th><?= e($col['label']) ?></th>
                            <?php endforeach; ?>
                            <th style="width:96px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$records): ?>
                        <tr>
                            <td colspan="<?= count($type['columns']) + 1 ?>">
                                <div class="empty-state">
                                    <i class="bi <?= e($type['icon']) ?>"></i>
                                    <?= $q !== ''
                                        ? 'Tiada rekod sepadan dengan carian anda.'
                                        : 'Belum ada rekod. Tambah yang pertama menggunakan borang di sebelah.' ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <?php foreach ($type['columns'] as $col): ?>
                                    <?php if (in_array($col['key'], ['updated_at'], true)) continue; ?>
                                    <td><?= record_cell($col, $row) ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= e(url('lecturer/' . $manager_type . '.php?edit=' . (int)$row['record_id'])) ?>"
                                           title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="record_id" value="<?= (int)$row['record_id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Padam"
                                                    data-confirm="Padam rekod ini? Tindakan ini tidak boleh dibatalkan.">
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

        <?php
        // Documents get a download link row under the table, because the file
        // has no meaningful place inside a narrow table cell.
        if ($manager_type === 'documents' && $records): ?>
            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-paperclip text-primary"></i> Fail Saya</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($records as $row):
                        $url = document_url($row['file_path'] ?? null);
                    ?>
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark"></i>
                            <span>
                                <b><?= e($row['title']) ?></b>
                                <br><small class="text-muted">
                                    <?= e($row['document_type'] ?? '') ?>
                                    <?= !empty($row['file_name']) ? ' &middot; ' . e($row['file_name']) : '' ?>
                                    <?php if (!empty($row['file_size'])): ?>
                                        &middot; <?= e(bytes_human((int)$row['file_size'])) ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                            <span class="ms-auto">
                                <?php if ($url): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e($url) ?>"
                                       target="_blank" rel="noopener">
                                        <i class="bi bi-download"></i> Lihat
                                    </a>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">fail tiada</span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
