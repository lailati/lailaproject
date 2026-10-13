<?php
/**
 * Closes the markup opened by includes/header.php.
 * Any page-specific scripts go in $extra_js before this file is included.
 */
?>
        </main>

        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(APP_UNIVERSITY) ?> &middot; <?= e(APP_FACULTY) ?></span>
            <span class="ms-auto"><?= e(APP_NAME) ?> v1.0 &middot; PSM 2241073</span>
        </footer>
    </div><!-- /.app-main -->
</div><!-- /.app-shell -->

<script src="<?= e(ASSETS_URL) ?>/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= e(ASSETS_URL) ?>/js/app.js"></script>
<?php foreach ($extra_js as $js): ?>
<script src="<?= e(ASSETS_URL) ?>/<?= e($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
