<?php
/**
 * Admin > Expertise Categories
 *
 * The one admin module with full create / update / delete, because the
 * expertise taxonomy is the Pentadbir's to maintain (proposal scope:
 * "menambah, mengemas kini dan memadam kategori bidang kepakaran").
 *
 * Two related lists on one page:
 *   - categories          (12 seeded, matched by the dashboard KPI)
 *   - expertise areas     (the catalogue items lecturers pick from)
 *
 * Deletion is refused while anything still references the row, so the
 * ON DELETE RESTRICT foreign keys can never raise a raw SQL error at the user.
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo    = db();
$errors = [];

/* =========================================================================
   ACTIONS
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        flash('danger', 'Sesi tidak sah. Sila cuba sekali lagi.');
        redirect('admin/expertise-categories.php');
    }

    $action = (string)($_POST['action'] ?? '');

    /* ------------------------------------------------------- categories --- */
    if (in_array($action, ['add_category', 'update_category'], true)) {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name       = trim((string)($_POST['category_name'] ?? ''));
        $desc       = trim((string)($_POST['description'] ?? ''));
        $status     = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($name === '') {
            $errors[] = 'Nama kategori diperlukan.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Nama kategori tidak boleh melebihi 100 aksara.';
        }

        if (!$errors) {
            // Unique check that ignores the row being edited.
            $stmt = $pdo->prepare('SELECT category_id FROM expertise_categories WHERE category_name = ? AND category_id <> ?');
            $stmt->execute([$name, $categoryId]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'Kategori "' . $name . '" sudah wujud.';
            }
        }

        if (!$errors) {
            if ($action === 'add_category') {
                $stmt = $pdo->prepare(
                    'INSERT INTO expertise_categories (category_name, description, status) VALUES (?, ?, ?)'
                );
                $stmt->execute([$name, $desc !== '' ? $desc : null, $status]);
                $newId = (int)$pdo->lastInsertId();
                log_activity((int)current_user()['user_id'], 'insert', 'expertise_categories', $newId,
                    'Added expertise category "' . $name . '"');
                flash('success', 'Kategori "' . $name . '" berjaya ditambah.');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE expertise_categories SET category_name = ?, description = ?, status = ? WHERE category_id = ?'
                );
                $stmt->execute([$name, $desc !== '' ? $desc : null, $status, $categoryId]);
                log_activity((int)current_user()['user_id'], 'update', 'expertise_categories', $categoryId,
                    'Updated expertise category "' . $name . '"');
                flash('success', 'Kategori "' . $name . '" berjaya dikemas kini.');
            }
            redirect('admin/expertise-categories.php');
        }
    }

    if ($action === 'delete_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);

        $items = scalar('SELECT COUNT(*) FROM expertise WHERE category_id = ?', [$categoryId]);
        if ($items > 0) {
            flash('danger', 'Kategori ini masih mengandungi ' . $items
                . ' bidang kepakaran. Padam atau pindahkan bidang tersebut dahulu.');
            redirect('admin/expertise-categories.php');
        }

        $stmt = $pdo->prepare('SELECT category_name FROM expertise_categories WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        $name = $stmt->fetchColumn();

        if ($name) {
            $pdo->prepare('DELETE FROM expertise_categories WHERE category_id = ?')->execute([$categoryId]);
            log_activity((int)current_user()['user_id'], 'delete', 'expertise_categories', $categoryId,
                'Deleted expertise category "' . $name . '"');
            flash('success', 'Kategori "' . $name . '" telah dipadam.');
        }
        redirect('admin/expertise-categories.php');
    }

    /* --------------------------------------------------------- expertise --- */
    if (in_array($action, ['add_expertise', 'update_expertise'], true)) {
        $expertiseId = (int)($_POST['expertise_id'] ?? 0);
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $name        = trim((string)($_POST['expertise_name'] ?? ''));
        $desc        = trim((string)($_POST['description'] ?? ''));
        $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($name === '') {
            $errors[] = 'Nama bidang kepakaran diperlukan.';
        }
        if ($action === 'add_expertise' && $categoryId <= 0) {
            $errors[] = 'Sila pilih kategori.';
        }
        if (!$errors && $action === 'add_expertise') {
            $stmt = $pdo->prepare('SELECT expertise_id FROM expertise WHERE expertise_name = ?');
            $stmt->execute([$name]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'Bidang "' . $name . '" sudah wujud dalam katalog.';
            }
        }
        if (!$errors && $action === 'update_expertise') {
            $stmt = $pdo->prepare('SELECT expertise_id FROM expertise WHERE expertise_name = ? AND expertise_id <> ?');
            $stmt->execute([$name, $expertiseId]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'Bidang "' . $name . '" sudah wujud dalam katalog.';
            }
        }

        if (!$errors) {
            if ($action === 'add_expertise') {
                $stmt = $pdo->prepare(
                    'INSERT INTO expertise (category_id, expertise_name, description, status) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$categoryId, $name, $desc !== '' ? $desc : null, $status]);
                $newId = (int)$pdo->lastInsertId();
                log_activity((int)current_user()['user_id'], 'insert', 'expertise', $newId,
                    'Added expertise area "' . $name . '"');
                flash('success', 'Bidang kepakaran "' . $name . '" berjaya ditambah.');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE expertise SET category_id = ?, expertise_name = ?, description = ?, status = ?
                      WHERE expertise_id = ?'
                );
                $stmt->execute([$categoryId, $name, $desc !== '' ? $desc : null, $status, $expertiseId]);
                log_activity((int)current_user()['user_id'], 'update', 'expertise', $expertiseId,
                    'Updated expertise area "' . $name . '"');
                flash('success', 'Bidang kepakaran "' . $name . '" berjaya dikemas kini.');
            }
            redirect('admin/expertise-categories.php');
        }
    }

    if ($action === 'delete_expertise') {
        $expertiseId = (int)($_POST['expertise_id'] ?? 0);

        $used = scalar('SELECT COUNT(*) FROM lecturer_expertise WHERE expertise_id = ?', [$expertiseId]);
        if ($used > 0) {
            flash('danger', 'Bidang ini digunakan oleh ' . $used
                . ' rekod kepakaran pensyarah dan tidak boleh dipadam.');
            redirect('admin/expertise-categories.php');
        }

        $stmt = $pdo->prepare('SELECT expertise_name FROM expertise WHERE expertise_id = ?');
        $stmt->execute([$expertiseId]);
        $name = $stmt->fetchColumn();

        if ($name) {
            $pdo->prepare('DELETE FROM expertise WHERE expertise_id = ?')->execute([$expertiseId]);
            log_activity((int)current_user()['user_id'], 'delete', 'expertise', $expertiseId,
                'Deleted expertise area "' . $name . '"');
            flash('success', 'Bidang kepakaran "' . $name . '" telah dipadam.');
        }
        redirect('admin/expertise-categories.php');
    }
}

/* =========================================================================
   DATA
   ========================================================================= */
$editCategory = null;
$editExpertise = null;

if (isset($_GET['edit_category'])) {
    $stmt = $pdo->prepare('SELECT * FROM expertise_categories WHERE category_id = ?');
    $stmt->execute([(int)$_GET['edit_category']]);
    $editCategory = $stmt->fetch() ?: null;
}
if (isset($_GET['edit_expertise'])) {
    $stmt = $pdo->prepare('SELECT * FROM expertise WHERE expertise_id = ?');
    $stmt->execute([(int)$_GET['edit_expertise']]);
    $editExpertise = $stmt->fetch() ?: null;
}

$categories = $pdo->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM expertise e WHERE e.category_id = c.category_id) AS item_count,
            (SELECT COUNT(DISTINCT le.lecturer_id)
               FROM expertise e
               JOIN lecturer_expertise le ON le.expertise_id = e.expertise_id
              WHERE e.category_id = c.category_id) AS lecturer_count
       FROM expertise_categories c
      ORDER BY c.category_name'
)->fetchAll();

$categoryFilter = (int)($_GET['category'] ?? 0);

$sql = 'SELECT e.*, c.category_name,
               (SELECT COUNT(*) FROM lecturer_expertise le WHERE le.expertise_id = e.expertise_id) AS usage_count
          FROM expertise e
          JOIN expertise_categories c ON c.category_id = e.category_id';
$params = [];
if ($categoryFilter > 0) {
    $sql .= ' WHERE e.category_id = ?';
    $params[] = $categoryFilter;
}
$sql .= ' ORDER BY c.category_name, e.expertise_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expertiseItems = $stmt->fetchAll();

$page_title = 'Expertise Categories';
$page_sub   = 'Urus kategori dan bidang kepakaran';
$active     = 'categories';

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

    <!-- ===================== CATEGORIES ===================== -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-diagram-3 text-primary"></i>
                <?= $editCategory ? 'Edit Category' : 'Add Expertise Category' ?>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/expertise-categories.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $editCategory ? 'update_category' : 'add_category' ?>">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="category_id" value="<?= (int)$editCategory['category_id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="category_name">Category Name <span class="text-danger">*</span></label>
                        <input class="form-control" id="category_name" name="category_name" required maxlength="100"
                               value="<?= e($editCategory['category_name'] ?? '') ?>"
                               placeholder="cth: Cybersecurity">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="category_description">Description</label>
                        <textarea class="form-control" id="category_description" name="description" rows="2"
                                  placeholder="Penerangan ringkas (pilihan)"><?= e($editCategory['description'] ?? '') ?></textarea>
                    </div>
                    <?php if ($editCategory): ?>
                        <div class="mb-3">
                            <label class="form-label" for="category_status">Status</label>
                            <select class="form-select" id="category_status" name="status">
                                <option value="active"   <?= $editCategory['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $editCategory['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <?php if ($editCategory): ?>
                            <a class="btn btn-light border" href="<?= e(url('admin/expertise-categories.php')) ?>">Cancel</a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i><?= $editCategory ? 'Save Changes' : 'Save Category' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul text-primary"></i> Categories
                <span class="badge text-bg-light ms-2"><?= count($categories) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th class="text-center">Bidang</th>
                            <th class="text-center">Pensyarah</th>
                            <th style="width:90px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td>
                                <b><?= e($c['category_name']) ?></b>
                                <?php if ($c['status'] === 'inactive'): ?>
                                    <span class="badge text-bg-secondary ms-1">inactive</span>
                                <?php endif; ?>
                                <?php if ($c['description']): ?>
                                    <br><small class="text-muted"><?= e($c['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a class="badge text-bg-light text-decoration-none"
                                   href="<?= e(url('admin/expertise-categories.php?category=' . (int)$c['category_id'])) ?>">
                                    <?= (int)$c['item_count'] ?>
                                </a>
                            </td>
                            <td class="text-center"><span class="badge text-bg-light"><?= (int)$c['lecturer_count'] ?></span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= e(url('admin/expertise-categories.php?edit_category=' . (int)$c['category_id'])) ?>"
                                       title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= (int)$c['category_id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Padam"
                                                data-confirm="Padam kategori '<?= e($c['category_name']) ?>'?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== EXPERTISE ITEMS ===================== -->
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-lightbulb text-warning"></i>
                <?= $editExpertise ? 'Edit Expertise Area' : 'Add Expertise Area' ?>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/expertise-categories.php')) ?>" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $editExpertise ? 'update_expertise' : 'add_expertise' ?>">
                    <?php if ($editExpertise): ?>
                        <input type="hidden" name="expertise_id" value="<?= (int)$editExpertise['expertise_id'] ?>">
                    <?php endif; ?>

                    <div class="col-md-4">
                        <label class="form-label" for="ex_category">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="ex_category" name="category_id" required>
                            <option value="">— Pilih —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['category_id'] ?>"
                                    <?= (int)($editExpertise['category_id'] ?? 0) === (int)$c['category_id'] ? 'selected' : '' ?>>
                                    <?= e($c['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ex_name">Expertise Area <span class="text-danger">*</span></label>
                        <input class="form-control" id="ex_name" name="expertise_name" required maxlength="100"
                               value="<?= e($editExpertise['expertise_name'] ?? '') ?>"
                               placeholder="cth: Digital Forensics">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ex_desc">Description</label>
                        <input class="form-control" id="ex_desc" name="description"
                               value="<?= e($editExpertise['description'] ?? '') ?>" placeholder="Pilihan">
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <?php if ($editExpertise): ?>
                            <a class="btn btn-light border" href="<?= e(url('admin/expertise-categories.php')) ?>">Cancel</a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i><?= $editExpertise ? 'Save Changes' : 'Save Expertise' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-bookmarks text-primary"></i> Expertise Areas
                <span class="badge text-bg-light ms-2"><?= count($expertiseItems) ?></span>
                <?php if ($categoryFilter): ?>
                    <a class="ms-auto small" href="<?= e(url('admin/expertise-categories.php')) ?>">
                        Tunjuk semua kategori
                    </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bidang Kepakaran</th>
                            <th>Kategori</th>
                            <th class="text-center">Digunakan</th>
                            <th>Status</th>
                            <th style="width:90px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$expertiseItems): ?>
                        <tr><td colspan="5"><div class="empty-state">
                            <i class="bi bi-lightbulb"></i>Tiada bidang kepakaran.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($expertiseItems as $item): ?>
                            <tr>
                                <td><b><?= e($item['expertise_name']) ?></b></td>
                                <td><small class="text-muted"><?= e($item['category_name']) ?></small></td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$item['usage_count'] > 0 ? 'text-bg-success' : 'text-bg-light' ?>">
                                        <?= (int)$item['usage_count'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['status'] === 'active'): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= e(url('admin/expertise-categories.php?edit_expertise=' . (int)$item['expertise_id'])) ?>"
                                           title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_expertise">
                                            <input type="hidden" name="expertise_id" value="<?= (int)$item['expertise_id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Padam"
                                                    data-confirm="Padam bidang '<?= e($item['expertise_name']) ?>'?">
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
                Bidang yang sudah digunakan oleh pensyarah tidak boleh dipadam — ini melindungi rekod kepakaran sedia ada.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
