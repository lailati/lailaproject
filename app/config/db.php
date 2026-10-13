<?php
/**
 * Database connection (PDO)
 *
 * One shared PDO instance for the whole request. All queries in this project
 * use prepared statements so user input can never be injected into SQL.
 *
 * Usage:
 *     $pdo  = db();
 *     $rows = db()->query('SELECT ...')->fetchAll();
 */

require_once __DIR__ . '/config.php';

/**
 * Return the shared PDO connection, creating it on first use.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        http_response_code(500);
        $detail = APP_DEBUG ? $e->getMessage() : 'Sila hubungi pentadbir sistem.';
        exit(
            '<!doctype html><meta charset="utf-8">'
            . '<div style="font-family:system-ui,Segoe UI,sans-serif;max-width:640px;margin:80px auto;'
            . 'padding:24px;border:1px solid #e0a0a0;background:#fff5f5;border-radius:8px">'
            . '<h2 style="margin:0 0 8px;color:#8a1f1f">Ralat sambungan pangkalan data</h2>'
            . '<p style="margin:0 0 12px;color:#444">Pastikan <b>MySQL</b> sudah dimulakan di '
            . 'XAMPP Control Panel, dan pangkalan data <b>' . htmlspecialchars(DB_NAME, ENT_QUOTES) . '</b> '
            . 'sudah diimport dari <code>database/fstp_talent_map.sql</code>.</p>'
            . ($detail ? '<pre style="background:#fff;padding:12px;border-radius:6px;overflow:auto;'
            . 'font-size:12px;color:#8a1f1f">' . htmlspecialchars($detail, ENT_QUOTES) . '</pre>' : '')
            . '</div>'
        );
    }

    return $pdo;
}
