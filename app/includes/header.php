<?php
/**
 * Shared page header: <head>, top bar and the role-aware sidebar.
 *
 * A page includes this after setting:
 *   $page_title  string  browser + top bar title
 *   $active      string  nav key to highlight (e.g. 'dashboard')
 *   $page_sub    string  optional subtitle under the title
 *   $extra_css   array   optional extra stylesheet paths
 *   $extra_js    array   optional extra script paths
 */

require_once __DIR__ . '/auth.php';

$__user      = current_user();
$__role      = $__user['role'] ?? 'guest';
$__isAdmin   = $__role === 'admin';
$page_title  = $page_title ?? 'Dashboard';
$page_sub    = $page_sub   ?? '';
$active      = $active     ?? '';
$messages    = take_flash();
$extra_css   = $extra_css  ?? [];
$extra_js    = $extra_js   ?? [];

/* ---------------------------------------------------------------------------
   $__role / $__isAdmin are prefixed so this include can never clobber a page's
   own variable of a similar name (a page filtering by role used to have its
   value overwritten here and silently render the wrong <select> option).
   --------------------------------------------------------------------------- */

/**
 * Sidebar definition.
 * A $url of null renders the item as a planned module that is not built yet,
 * so the menu shows the whole system structure while we build it module by
 * module. Replace the null with the real path as each page is completed.
 */
if ($__isAdmin) {
    $nav = [
        'dashboard'   => ['Dashboard',            'admin/dashboard.php',            'bi-speedometer2'],
        'users'       => ['Manage Users',         'admin/users.php',                'bi-people-fill'],
        'lecturers'   => ['Lecturers',            'admin/lecturers.php',            'bi-person-badge'],
        'categories'  => ['Expertise Categories', 'admin/expertise-categories.php', 'bi-diagram-3'],
        'mapping'     => ['Talent Mapping',       'admin/talent-mapping.php',       'bi-search'],
        'research'    => ['Research',             'admin/research.php',             'bi-journal-text'],
        'publications'=> ['Publications',         'admin/publications.php',         'bi-book'],
        'certifications' => ['Certifications',    'admin/certifications.php',       'bi-patch-check'],
        'training'    => ['Training',             'admin/training.php',             'bi-mortarboard'],
        'consultancy' => ['Consultancy',          'admin/consultancy.php',          'bi-briefcase'],
        'achievements'=> ['Achievements',         'admin/achievements.php',         'bi-trophy'],
        'documents'   => ['Documents',            'admin/documents.php',            'bi-folder2-open'],
        'statistics'  => ['Statistics',           'admin/statistics.php',           'bi-bar-chart-line'],
        'reports'     => ['Reports',              'admin/reports.php',              'bi-file-earmark-text'],
    ];
} else {
    $nav = [
        'dashboard'   => ['Dashboard',            'lecturer/dashboard.php',         'bi-speedometer2'],
        'profile'     => ['My Profile',           'lecturer/profile.php',           'bi-person-circle'],
        'expertise'   => ['My Expertise',         'lecturer/expertise.php',         'bi-lightbulb'],
        'research'    => ['My Research',          'lecturer/research.php',          'bi-journal-text'],
        'publications'=> ['My Publications',      'lecturer/publications.php',      'bi-book'],
        'certifications' => ['My Certifications', 'lecturer/certifications.php',    'bi-patch-check'],
        'training'    => ['My Training',          'lecturer/training.php',          'bi-mortarboard'],
        'consultancy' => ['My Consultancy',       'lecturer/consultancy.php',       'bi-briefcase'],
        'achievements'=> ['My Achievements',      'lecturer/achievements.php',      'bi-trophy'],
        'documents'   => ['My Documents',         'lecturer/documents.php',         'bi-folder2-open'],
    ];
}

$avatarPhoto = $__user['profile_photo'] ?? null;
$avatarPath  = $avatarPhoto && is_file(UPLOAD_PHOTO_DIR . '/' . $avatarPhoto)
    ? UPLOAD_PHOTO_URL . '/' . rawurlencode($avatarPhoto)
    : null;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= e(ASSETS_URL) ?>/img/favicon.svg">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/vendor/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/app.css">
<?php foreach ($extra_css as $css): ?>
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/<?= e($css) ?>">
<?php endforeach; ?>
</head>
<body class="app-body">

<div class="app-shell">

    <!-- ================= SIDEBAR ================= -->
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <span class="brand-crest" aria-hidden="true">
                <svg viewBox="0 0 32 36" width="28" height="32">
                    <path d="M16 1 3 6v13c0 8 5.6 13.3 13 16 7.4-2.7 13-8 13-16V6L16 1z" fill="#1e4f9c"/>
                    <path d="M16 4.6 6 8.4v10.4c0 6.4 4.4 10.7 10 12.8 5.6-2.1 10-6.4 10-12.8V8.4L16 4.6z" fill="#f2f6fc"/>
                    <path d="M16 4.6 6 8.4v4.2h20V8.4L16 4.6z" fill="#b8242a"/>
                    <path d="M14.6 15.2h2.8v9.4h-2.8z" fill="#1e4f9c"/>
                    <path d="M11.2 18.6h9.6v2.6h-9.6z" fill="#1e4f9c"/>
                </svg>
            </span>
            <span class="brand-text">
                <strong>FSTP</strong>
                <small>FAKULTI SAINS DAN<br>TEKNOLOGI PERTAHANAN</small>
            </span>
        </div>

        <div class="sidebar-product">FSTP TALENT MAP</div>

        <nav class="sidebar-nav">
            <?php foreach ($nav as $key => [$label, $path, $icon]): ?>
                <?php if ($path !== null): ?>
                    <a class="nav-item <?= $active === $key ? 'is-active' : '' ?>"
                       href="<?= e(url($path)) ?>">
                        <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
                    </a>
                <?php else: ?>
                    <span class="nav-item is-disabled" title="Modul ini akan dibina">
                        <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
                        <small class="soon">soon</small>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <a class="nav-item" href="<?= e(url('logout.php')) ?>">
                <i class="bi bi-box-arrow-right"></i><span>Log Keluar</span>
            </a>
        </div>
    </aside>

    <!-- ================= MAIN ================= -->
    <div class="app-main">

        <header class="app-topbar">
            <button class="btn btn-link sidebar-toggle" type="button" id="sidebarToggle"
                    aria-label="Togol menu"><i class="bi bi-list"></i></button>

            <div class="topbar-title">
                <h1><?= e($page_title) ?></h1>
                <?php if ($page_sub !== ''): ?><p><?= e($page_sub) ?></p><?php endif; ?>
            </div>

            <div class="topbar-right">
                <span class="role-chip role-<?= e($__role) ?>">
                    <?= $__isAdmin ? 'Pentadbir' : 'Pensyarah' ?>
                </span>

                <button class="btn btn-link topbar-icon" type="button" aria-label="Notifikasi">
                    <i class="bi bi-bell"></i>
                </button>

                <div class="dropdown">
                    <button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($avatarPath): ?>
                            <img class="avatar" src="<?= e($avatarPath) ?>" alt="">
                        <?php else: ?>
                            <span class="avatar avatar-initials"><?= e(initials(display_name())) ?></span>
                        <?php endif; ?>
                        <span class="user-menu-name"><?= e(display_name()) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted">
                            <?= e($__user['email'] ?? '') ?>
                        </span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= e(url(home_for_role())) ?>">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(url('logout.php')) ?>">
                            <i class="bi bi-box-arrow-right me-2"></i>Log Keluar</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <?php foreach ($messages as $m): ?>
                <div class="alert alert-<?= e($m['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($m['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endforeach; ?>
