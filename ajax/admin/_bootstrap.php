<?php
/**
 * Shared bootstrap for ADMIN AJAX endpoints.
 * Returns {"success":bool,"message":string,"data":{}} and requires an admin session.
 */

require_once __DIR__ . '/../../admin/includes/admin-auth.php';
require_once __DIR__ . '/../../includes/products.php';

header('Content-Type: application/json; charset=utf-8');

requireAdminAjax();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}
csrf_verify_or_fail();

/** Absolute path to project root. */
function project_root() {
    return realpath(__DIR__ . '/../../');
}

/** Render one thumbnail's JSON payload. */
function image_payload(array $img) {
    return [
        'id'         => (int) $img['id'],
        'path'       => $img['image_path'],
        'url'        => '../' . $img['image_path'],
        'is_primary' => (int) $img['is_primary'],
        'sort_order' => (int) $img['sort_order'],
    ];
}
