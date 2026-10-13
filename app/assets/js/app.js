/* ============================================================================
   FSTP Talent Map — shared front-end behaviour
   ========================================================================= */
(function () {
    'use strict';

    /* Sidebar toggle on small screens */
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('#sidebarToggle');
        if (toggle) {
            var sidebar = document.getElementById('appSidebar');
            if (sidebar) { sidebar.classList.toggle('is-open'); }
            return;
        }
        // tapping outside an open sidebar closes it
        var sidebar = document.getElementById('appSidebar');
        if (sidebar && sidebar.classList.contains('is-open')
            && !event.target.closest('#appSidebar')) {
            sidebar.classList.remove('is-open');
        }
    });

    /* Show/hide password */
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.pw-toggle');
        if (!button) { return; }
        var input = button.closest('.login-input, .input-group, .pw-wrap');
        input = input ? input.querySelector('input') : null;
        if (!input) { return; }

        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-eye', !show);
            icon.classList.toggle('bi-eye-slash', show);
        }
    });

    /* Disable a submit button after the first click so a slow save cannot be
       double-submitted (no page here is safe to submit twice). */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.classList.contains('no-double-submit')) { return; }
        var button = form.querySelector('button[type="submit"]');
        if (button && !form.classList.contains('allow-double')) {
            setTimeout(function () {
                button.disabled = true;
                button.dataset.originalText = button.dataset.originalText || button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
            }, 0);
        }
    });

    /* Ask before anything destructive */
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-confirm]');
        if (link && !window.confirm(link.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    /* Auto-dismiss success notices */
    document.querySelectorAll('.alert-success, .alert-info').forEach(function (alert) {
        setTimeout(function () {
            if (window.bootstrap && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        }, 6000);
    });
})();
