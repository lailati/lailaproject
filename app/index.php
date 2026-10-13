<?php
/**
 * Front controller — sends each visitor to the right place.
 *
 *   not signed in -> login page
 *   Pentadbir     -> admin dashboard
 *   Pensyarah     -> lecturer dashboard
 */

require_once __DIR__ . '/includes/auth.php';

redirect(is_logged_in() ? home_for_role() : 'login.php');
