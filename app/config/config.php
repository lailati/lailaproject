<?php
/**
 * Application configuration
 * Web-Based Talent Mapping System for FSTP Lecturers
 *
 * UPNM | FSTP | PSM 2241073 Lailati binti Mohd
 *
 * Everything environment-specific lives in this one file so the rest of the
 * code never hard-codes a credential or a path.
 */

/* ---------------------------------------------------------------------------
   DATABASE
   XAMPP default: user "root" with an EMPTY password, only reachable from
   localhost. Change these before hosting the system anywhere else.
   --------------------------------------------------------------------------- */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'fstp_talent_map');
define('DB_USER', 'root');
define('DB_PASS', '');

/* ---------------------------------------------------------------------------
   APPLICATION
   BASE_URL is the folder this app is served from inside htdocs.
   If you rename the folder, change it here (or set the APP_BASE_URL env var).
   --------------------------------------------------------------------------- */
define('APP_NAME',      'FSTP Talent Map');
define('APP_FULL_NAME', 'Web-Based Talent Mapping System for FSTP Lecturers');
define('APP_FACULTY',   'Fakulti Sains dan Teknologi Pertahanan');
define('APP_UNIVERSITY','Universiti Pertahanan Nasional Malaysia');
define('BASE_URL', rtrim(getenv('APP_BASE_URL') ?: '/projectlaila', '/'));

/* ---------------------------------------------------------------------------
   PATHS
   --------------------------------------------------------------------------- */
define('APP_ROOT',         dirname(__DIR__));
define('UPLOAD_PHOTO_DIR', APP_ROOT . '/assets/uploads/photo');
define('UPLOAD_DOC_DIR',   APP_ROOT . '/assets/uploads/documents');
define('UPLOAD_PHOTO_URL', BASE_URL . '/assets/uploads/photo');
define('UPLOAD_DOC_URL',   BASE_URL . '/assets/uploads/documents');
define('ASSETS_URL',       BASE_URL . '/assets');

/* ---------------------------------------------------------------------------
   UPLOAD RULES
   --------------------------------------------------------------------------- */
define('MAX_PHOTO_BYTES', 2 * 1024 * 1024);    // 2 MB
define('MAX_DOC_BYTES',   10 * 1024 * 1024);   // 10 MB
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png']);
define('ALLOWED_IMAGE_MIME', ['image/jpeg', 'image/png']);
define('ALLOWED_DOC_EXT',  ['pdf', 'jpg', 'jpeg', 'png']);
define('ALLOWED_DOC_MIME', ['application/pdf', 'image/jpeg', 'image/png']);

/* ---------------------------------------------------------------------------
   SESSION
   "Remember me" extends the session cookie lifetime; there is no separate
   persistent-token table because the ERD has no such table.
   --------------------------------------------------------------------------- */
define('SESSION_NAME',  'FSTPTALENTMAP');
define('REMEMBER_DAYS', 30);

/* ---------------------------------------------------------------------------
   ENVIRONMENT
   APP_DEBUG = true  -> errors are shown on screen (development)
   APP_DEBUG = false -> errors are logged only (demonstration / submission)
   --------------------------------------------------------------------------- */
define('APP_DEBUG', true);

date_default_timezone_set('Asia/Kuala_Lumpur');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('log_errors', '1');
}
