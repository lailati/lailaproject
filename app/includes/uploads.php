<?php
/**
 * File uploads
 *
 * One place where an uploaded file is validated and stored, so every module
 * (documents, certificates, CVs, supporting evidence) applies the same rules.
 *
 * What is enforced:
 *   - the PHP upload error code is checked before anything else
 *   - size against the configured limit
 *   - the extension against an allow-list
 *   - the MIME type detected from the FILE CONTENTS (finfo), never from the
 *     browser-supplied type, which is trivially forged
 *   - the stored name is generated, so a crafted filename cannot escape the
 *     directory or overwrite an existing file
 *   - assets/uploads/.htaccess refuses to serve anything executable
 *
 * The original filename is kept only for display.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Validate and move one uploaded file into $dir.
 *
 * @return array{ok:bool,error:string,stored:string,original:string,size:int}
 */
function store_upload(
    array $file,
    array $allowedExt,
    array $allowedMime,
    int $maxBytes,
    string $dir
): array {
    $fail = fn(string $message): array => [
        'ok' => false, 'error' => $message, 'stored' => '', 'original' => '', 'size' => 0,
    ];

    if (!isset($file['error']) || is_array($file['error'])) {
        return $fail('Fail tidak sah.');
    }

    switch ((int)$file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return $fail('Tiada fail dipilih.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $fail('Saiz fail melebihi had pelayan.');
        case UPLOAD_ERR_PARTIAL:
            return $fail('Fail hanya sebahagian sahaja dimuat naik. Sila cuba lagi.');
        default:
            return $fail('Muat naik gagal (kod ' . (int)$file['error'] . ').');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('Fail tidak sah.');
    }

    if ((int)$file['size'] <= 0) {
        return $fail('Fail kosong.');
    }
    if ((int)$file['size'] > $maxBytes) {
        return $fail('Saiz fail melebihi had ' . bytes_human($maxBytes) . '.');
    }

    $original = basename((string)$file['name']);
    $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if ($ext === '' || !in_array($ext, $allowedExt, true)) {
        return $fail('Jenis fail tidak dibenarkan. Benarkan: ' . strtoupper(implode(', ', $allowedExt)) . '.');
    }

    // Trust the content, not the browser's Content-Type.
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)($finfo->file($file['tmp_name']) ?: '');
    }
    if ($mime === '' || !in_array($mime, $allowedMime, true)) {
        return $fail('Kandungan fail tidak sepadan dengan jenis yang dibenarkan.');
    }

    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return $fail('Direktori muat naik tidak boleh diakses.');
    }

    // Generated name: date + 16 random hex chars. The extension is taken from
    // the validated allow-list value, never from the raw user string.
    $stored = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = rtrim(str_replace('\\', '/', $dir), '/') . '/' . $stored;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return $fail('Gagal menyimpan fail. Sila cuba lagi.');
    }

    @chmod($target, 0644);

    return [
        'ok'       => true,
        'error'    => '',
        'stored'   => $stored,
        'original' => mb_substr($original, 0, 240),
        'size'     => (int)$file['size'],
    ];
}

/**
 * Remove a previously stored file. Never throws: a missing file on disk is not
 * a reason to fail the user's delete action.
 */
function delete_stored_file(?string $path, string $dir = UPLOAD_DOC_DIR): bool
{
    if ($path === null || $path === '') {
        return false;
    }

    $path = str_replace('\\', '/', $path);
    // Only ever touch a plain filename inside the upload directory.
    $name = basename($path);
    if ($name === '' || $name === '.' || $name === '..') {
        return false;
    }

    $target = rtrim(str_replace('\\', '/', $dir), '/') . '/' . $name;

    return is_file($target) && @unlink($target);
}

/**
 * Public URL for a stored document, or null when the file is not on disk.
 *
 * Two shapes are supported:
 *   - a bare filename (what new uploads store)
 *   - a path relative to the app root (what the sample data uses)
 * Returning null for a missing file means the UI shows "—" instead of a
 * download link that 404s.
 */
function document_url(?string $path): ?string
{
    if ($path === null || trim($path) === '') {
        return null;
    }

    $path = str_replace('\\', '/', trim($path));

    if (str_contains($path, '/')) {
        $relative = ltrim($path, '/');
        return is_file(APP_ROOT . '/' . $relative) ? BASE_URL . '/' . $relative : null;
    }

    return is_file(UPLOAD_DOC_DIR . '/' . $path)
        ? UPLOAD_DOC_URL . '/' . rawurlencode($path)
        : null;
}

/** URL for a profile photo, or null when it is missing. */
function photo_url(?string $filename): ?string
{
    if ($filename === null || trim($filename) === '') {
        return null;
    }
    $filename = basename(str_replace('\\', '/', $filename));
    return is_file(UPLOAD_PHOTO_DIR . '/' . $filename)
        ? UPLOAD_PHOTO_URL . '/' . rawurlencode($filename)
        : null;
}
