<?php
/**
 * Pensyarah > My Expertise
 *
 * The one record type that is a many-to-many relationship, so it needs its own
 * page rather than the generic record manager: the lecturer picks an expertise
 * area from the catalogue the Pentadbir maintains, and adds their own level,
 * years of experience and research interest.
 *
 * Duplicate expertise is prevented twice over: the form filters the areas the
 * lecturer already holds, and the UNIQUE(lecturer_id, expertise_id) key in the
 * database is the final guard.
 */

require_once __DIR__ . '/../includes/auth.php';
require_lecturer();

$user       = current_user();
$lecturerId = (int)($user['lecturer_id'] ?? 0);
$pdo        = db();

if ($lecturerId === 0) {
    flash('danger', 'Akaun anda belum dipautkan kepada rekod pensyarah.');
    redirect(home_for_role());
}

$levels  = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
$errors  = [];
$form    = [];
$editing = null;

/* =========================================================================
   ACTIONS
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        flash('danger', 'Sesi tidak sah. Sila cuba sekali lagi.');
        redirect('lecturer/expertise.php');
    }

    $action = (string)($_POST['action'] ?? '');

    /* ------------------------------------------------------------- delete -- */
    if ($action === 'delete') {
        $rowId = (int)($_POST['lecturer_expertise_id'] ?? 0);

        // Ownership is in the WHERE clause.
        $stmt = $pdo->prepare(
            'DELETE FROM lecturer_expertise WHERE lecturer_expertise_id = :id AND lecturer_id = :lecturer_id'
        );
        $stmt->execute([':id' => $rowId, ':lecturer_id' => $lecturerId]);

        if ($stmt->rowCount() > 0) {
            log_activity((int)$user['user_id'], 'delete', 'lecturer_expertise', $rowId,
                'Removed an expertise entry');
            flash('success', 'Rekod kepakaran berjaya dipadam.');
        } else {
            flash('danger', 'Rekod tidak dijumpai, atau ia bukan milik anda.');
        }
        redirect('lecturer/expertise.php');
    }

    /* ------------------------------------------------------ create/update -- */
    if ($action === 'create' || $action === 'update') {
        $rowId       = (int)($_POST['lecturer_expertise_id'] ?? 0);
        $expertiseId = (int)($_POST['expertise_id'] ?? 0);
        $level       = (string)($_POST['expertise_level'] ?? '');
        $years       = trim((string)($_POST['years_experience'] ?? '0'));
        $interest    = trim((string)($_POST['research_interest'] ?? ''));

        $form = $_POST;

        if ($expertiseId <= 0) {
            $errors[] = 'Sila pilih bidang kepakaran.';
        }
        if (!in_array($level, $levels, true)) {
            $errors[] = 'Sila pilih tahap kepakaran yang sah.';
        }
        if (!ctype_digit($years) || (int)$years < 0 || (int)$years > 60) {
            $errors[] = 'Tahun pengalaman mesti antara 0 dan 60.';
        }
        if (mb_strlen($interest) > 1000) {
            $errors[] = 'Research interest tidak boleh melebihi 1000 aksara.';
        }

        // Duplicate guard (mirrors the UNIQUE key for a friendly message).
        if (!$errors) {
            $sql = 'SELECT COUNT(*) FROM lecturer_expertise
                     WHERE lecturer_id = :lecturer_id AND expertise_id = :expertise_id';
            $params = [':lecturer_id' => $lecturerId, ':expertise_id' => $expertiseId];
            if ($action === 'update') {
                $sql .= ' AND lecturer_expertise_id <> :id';
                $params[':id'] = $rowId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Anda sudah merekodkan bidang kepakaran ini. Sila edit rekod sedia ada.';
            }
        }

        /* --------------------------------------------- optional document -- */
        $newDocument = null;
        if (!$errors
            && isset($_FILES['supporting_document'])
            && (int)($_FILES['supporting_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

            $upload = store_upload($_FILES['supporting_document'],
                ALLOWED_DOC_EXT, ALLOWED_DOC_MIME, MAX_DOC_BYTES, UPLOAD_DOC_DIR);

            if (!$upload['ok']) {
                $errors[] = 'Dokumen sokongan: ' . $upload['error'];
            } else {
                $newDocument = $upload['stored'];
            }
        }

        if (!$errors) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO lecturer_expertise
                           (lecturer_id, expertise_id, expertise_level, years_experience,
                            research_interest, supporting_document, status)
                         VALUES (:lecturer_id, :expertise_id, :level, :years, :interest, :document, \'active\')'
                    );
                    $stmt->execute([
                        ':lecturer_id'  => $lecturerId,
                        ':expertise_id' => $expertiseId,
                        ':level'        => $level,
                        ':years'        => (int)$years,
                        ':interest'     => $interest !== '' ? $interest : null,
                        ':document'     => $newDocument,
                    ]);
                    $newId = (int)$pdo->lastInsertId();

                    log_activity((int)$user['user_id'], 'insert', 'lecturer_expertise', $newId,
                        'Added expertise to profile');

                    flash('success', 'Kepakaran berjaya ditambah.');
                } else {
                    $stmt = $pdo->prepare(
                        'SELECT supporting_document FROM lecturer_expertise
                          WHERE lecturer_expertise_id = :id AND lecturer_id = :lecturer_id'
                    );
                    $stmt->execute([':id' => $rowId, ':lecturer_id' => $lecturerId]);
                    $existing = $stmt->fetch();

                    if (!$existing) {
                        if ($newDocument) {
                            delete_stored_file($newDocument);
                        }
                        flash('danger', 'Rekod tidak dijumpai, atau ia bukan milik anda.');
                        redirect('lecturer/expertise.php');
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE lecturer_expertise
                            SET expertise_id = :expertise_id, expertise_level = :level,
                                years_experience = :years, research_interest = :interest,
                                supporting_document = :document
                          WHERE lecturer_expertise_id = :id AND lecturer_id = :lecturer_id'
                    );
                    $stmt->execute([
                        ':expertise_id' => $expertiseId,
                        ':level'        => $level,
                        ':years'        => (int)$years,
                        ':interest'     => $interest !== '' ? $interest : null,
                        ':document'     => $newDocument ?? $existing['supporting_document'],
                        ':id'           => $rowId,
                        ':lecturer_id'  => $lecturerId,
                    ]);

                    if ($newDocument && !empty($existing['supporting_document'])) {
                        delete_stored_file((string)$existing['supporting_document']);
                    }

                    log_activity((int)$user['user_id'], 'update', 'lecturer_expertise', $rowId,
                        'Updated an expertise entry');

                    flash('success', 'Kepakaran berjaya dikemas kini.');
                }

                redirect('lecturer/expertise.php');
            } catch (Throwable $ex) {
                if ($newDocument) {
                    delete_stored_file($newDocument);
                }
                $errors[] = APP_DEBUG
                    ? 'Gagal menyimpan: ' . $ex->getMessage()
                    : 'Gagal menyimpan rekod. Sila cuba lagi.';
            }
        }
    }
}

/* =========================================================================
   DATA
   ========================================================================= */
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        'SELECT * FROM lecturer_expertise WHERE lecturer_expertise_id = :id AND lecturer_id = :lecturer_id'
    );
    $stmt->execute([':id' => (int)$_GET['edit'], ':lecturer_id' => $lecturerId]);
    $editing = $stmt->fetch() ?: null;

    if (!$editing) {
        flash('danger', 'Rekod kepakaran yang anda cuba edit tidak dijumpai.');
        redirect('lecturer/expertise.php');
    }
    if (!$form) {
        $form = $editing;
    }
}

$stmt = $pdo->prepare(
    'SELECT le.*, ex.expertise_name, ex.category_id, ec.category_name
       FROM lecturer_expertise le
       JOIN expertise ex ON ex.expertise_id = le.expertise_id
       LEFT JOIN expertise_categories ec ON ec.category_id = ex.category_id
      WHERE le.lecturer_id = :id AND le.status = \'active\'
      ORDER BY FIELD(le.expertise_level, \'Expert\', \'Advanced\', \'Intermediate\', \'Beginner\'),
               le.years_experience DESC'
);
$stmt->execute([':id' => $lecturerId]);
$myExpertise = $stmt->fetchAll();

// Areas the lecturer already holds, so the form can hide them.
$held = array_column($myExpertise, 'expertise_id');
if ($editing) {
    $held = array_values(array_diff($held, [(int)$editing['expertise_id']]));
}

$categories = $pdo->query(
    'SELECT category_id, category_name FROM expertise_categories WHERE status = \'active\' ORDER BY category_name'
)->fetchAll();

$catalogue = $pdo->query(
    'SELECT expertise_id, category_id, expertise_name FROM expertise WHERE status = \'active\'
      ORDER BY expertise_name'
)->fetchAll();

// Group the catalogue by category so the second dropdown can be filtered client-side.
$catalogueByCategory = [];
foreach ($catalogue as $item) {
    $catalogueByCategory[(int)$item['category_id']][] = [
        'id'   => (int)$item['expertise_id'],
        'name' => $item['expertise_name'],
    ];
}

$page_title = 'My Expertise';
$page_sub   = 'Bidang kepakaran, tahap dan pengalaman anda';
$active     = 'expertise';

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

<div class="row g-3">

    <!-- ===================== FORM ===================== -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightbulb text-warning"></i>
                <?= $editing ? 'Edit Expertise' : 'Add New Expertise' ?>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data"
                      action="<?= e(url('lecturer/expertise.php')) ?>" class="no-double-submit">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="lecturer_expertise_id"
                               value="<?= (int)$editing['lecturer_expertise_id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="category_id">Expertise Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">— Semua Kategori —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['category_id'] ?>"
                                    <?= (int)($form['category_id'] ?? $editing['category_id'] ?? 0) === (int)$c['category_id'] ? 'selected' : '' ?>>
                                    <?= e($c['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Menapis senarai bidang di bawah.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="expertise_id">Expertise Area <span class="text-danger">*</span></label>
                        <select class="form-select" id="expertise_id" name="expertise_id" required>
                            <option value="">— Pilih Bidang —</option>
                            <?php foreach ($categories as $c): ?>
                                <?php foreach ($catalogueByCategory[(int)$c['category_id']] ?? [] as $item): ?>
                                    <?php if (in_array($item['id'], $held, true)) continue; ?>
                                    <option value="<?= (int)$item['id'] ?>"
                                            data-category="<?= (int)$c['category_id'] ?>"
                                        <?= (int)($form['expertise_id'] ?? $editing['expertise_id'] ?? 0) === $item['id'] ? 'selected' : '' ?>>
                                        <?= e($item['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Bidang yang sudah anda rekodkan tidak dipaparkan.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="expertise_level">Expertise Level <span class="text-danger">*</span></label>
                            <select class="form-select" id="expertise_level" name="expertise_level" required>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?= e($level) ?>"
                                        <?= ($form['expertise_level'] ?? $editing['expertise_level'] ?? '') === $level ? 'selected' : '' ?>>
                                        <?= e($level) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="years_experience">Years of Experience <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" id="years_experience" name="years_experience"
                                   min="0" max="60" step="1" required
                                   value="<?= e((string)($form['years_experience'] ?? $editing['years_experience'] ?? 0)) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="research_interest">Research Interest (Optional)</label>
                        <textarea class="form-control" id="research_interest" name="research_interest" rows="2"
                                  placeholder="Contoh: Cybercrime investigation, Incident response"><?= e($form['research_interest'] ?? $editing['research_interest'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="supporting_document">Supporting Document</label>
                        <input class="form-control" type="file" id="supporting_document" name="supporting_document"
                               accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">PDF, JPG atau PNG. Maksimum 5 MB.</div>
                        <?php if ($editing && !empty($editing['supporting_document'])):
                            $docUrl = document_url($editing['supporting_document']); ?>
                            <div class="small mt-1">
                                <?php if ($docUrl): ?>
                                    Fail semasa: <a href="<?= e($docUrl) ?>" target="_blank" rel="noopener">lihat</a>
                                    <span class="text-muted">(muat naik baharu akan menggantikannya)</span>
                                <?php else: ?>
                                    <span class="text-muted">Fail direkodkan tetapi tiada pada pelayan.</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($editing): ?>
                            <a class="btn btn-light border" href="<?= e(url('lecturer/expertise.php')) ?>">Cancel</a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i><?= $editing ? 'Save Changes' : 'Save Expertise' ?>
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
                <i class="bi bi-list-check text-primary"></i> My Expertise
                <span class="badge text-bg-light ms-2"><?= count($myExpertise) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:48px">No.</th>
                            <th>Expertise</th>
                            <th>Category</th>
                            <th>Level</th>
                            <th class="text-center">Years</th>
                            <th>Document</th>
                            <th style="width:96px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$myExpertise): ?>
                        <tr><td colspan="7"><div class="empty-state">
                            <i class="bi bi-lightbulb"></i>Belum ada rekod kepakaran.
                            Tambah yang pertama menggunakan borang di sebelah.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($myExpertise as $i => $row):
                            $docUrl = document_url($row['supporting_document'] ?? null);
                        ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <b><?= e($row['expertise_name']) ?></b>
                                    <?php if ($row['research_interest']): ?>
                                        <br><small class="text-muted"><?= e($row['research_interest']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= e($row['category_name'] ?? '—') ?></small></td>
                                <td>
                                    <span class="badge-level level-<?= e(strtolower($row['expertise_level'])) ?>">
                                        <?= e($row['expertise_level']) ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= (int)$row['years_experience'] ?></td>
                                <td>
                                    <?php if ($docUrl): ?>
                                        <a href="<?= e($docUrl) ?>" target="_blank" rel="noopener"
                                           title="Lihat dokumen"><i class="bi bi-paperclip"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= e(url('lecturer/expertise.php?edit=' . (int)$row['lecturer_expertise_id'])) ?>"
                                           title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="lecturer_expertise_id"
                                                   value="<?= (int)$row['lecturer_expertise_id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Padam"
                                                    data-confirm="Padam rekod kepakaran '<?= e($row['expertise_name']) ?>'?">
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
            <div class="card-footer bg-white small text-muted">
                Senarai bidang kepakaran diselenggara oleh Pentadbir. Maklumkan kepada mereka jika bidang anda tiada dalam senarai.
            </div>
        </div>
    </div>
</div>

<script>
/* Filter the Expertise Area dropdown by the chosen category. */
(function () {
    'use strict';
    var categorySelect  = document.getElementById('category_id');
    var expertiseSelect = document.getElementById('expertise_id');
    if (!categorySelect || !expertiseSelect) { return; }

    var options = Array.prototype.slice.call(expertiseSelect.options);

    function apply() {
        var wanted = categorySelect.value;
        var stillVisible = false;

        options.forEach(function (opt) {
            if (!opt.value) { return; }                       // the placeholder
            var match = !wanted || opt.getAttribute('data-category') === wanted;
            opt.hidden = !match;
            opt.disabled = !match;
            if (match && opt.selected) { stillVisible = true; }
        });

        // If the current choice was filtered out, fall back to the placeholder.
        if (!stillVisible) { expertiseSelect.value = ''; }
    }

    categorySelect.addEventListener('change', apply);
    apply();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
