<?php
/**
 * Logout — destroys the session and the remember-me cookie, then returns to
 * the login page with a confirmation notice.
 */

require_once __DIR__ . '/includes/auth.php';

logout_user();

// A fresh session is started purely to carry the goodbye flash message.
session_name(SESSION_NAME);
session_start();
flash('success', 'Anda telah log keluar.');

redirect('login.php');
