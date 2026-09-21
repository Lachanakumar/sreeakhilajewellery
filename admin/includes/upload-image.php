<?php
/**
 * Synchronous single-image upload for admin forms (sliders, banners, ...).
 * Returns [ok, pathOrError|null]. If no file was submitted, returns [true, null]
 * so the caller can keep the existing image.
 */

require_once __DIR__ . '/../../includes/functions.php';

/**
 * Did the form actually send a file for this field?
 *
 * Checked before any other work so a form that is going to be rejected never
 * reaches move_uploaded_file() — the old order uploaded first and validated
 * after, which left an orphaned file in uploads/ every time a required field
 * was missing.
 */
function admin_has_upload($fileKey) {
    return !empty($_FILES[$fileKey])
        && ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function admin_upload_image($fileKey, $subdir) {
    if (empty($_FILES[$fileKey]) || ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null];
    }

    [$ok, $extOrError] = validate_uploaded_image($_FILES[$fileKey]);
    if (!$ok) {
        return [false, $extOrError];
    }

    $absDir = realpath(__DIR__ . '/../../') . '/uploads/' . trim($subdir, '/');
    if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
        return [false, 'Upload directory is not writable.'];
    }

    $filename = unique_filename($extOrError);
    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $absDir . '/' . $filename)) {
        return [false, 'Could not save the uploaded file.'];
    }

    return [true, 'uploads/' . trim($subdir, '/') . '/' . $filename];
}

/** Delete a previously uploaded file (only inside uploads/). */
function admin_delete_upload($path) {
    $path = trim((string) $path);
    if ($path === '' || strpos($path, 'uploads/') !== 0) {
        return;
    }
    $abs = realpath(__DIR__ . '/../../') . '/' . $path;
    if (is_file($abs)) {
        @unlink($abs);
    }
}
