<?php
/**
 * Shared bootstrap for gateway webhooks.
 *
 * Webhooks are server-to-server: no session, no CSRF, no auth cookie.
 * Trust comes from the gateway's signature — nothing else.
 */

// Defence in depth: a library, never an endpoint. webhooks/.htaccess blocks it
// under Apache; this covers servers that ignore .htaccess.
if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/payments/manager.php';

/** Raw request body — must be read before any parsing so signatures still match. */
function hook_raw_body(): string
{
    static $raw = null;
    if ($raw === null) {
        $raw = (string) file_get_contents('php://input');
    }
    return $raw;
}

/** Case-insensitive request headers. */
function hook_headers(): array
{
    static $h = null;
    if ($h !== null) {
        return $h;
    }
    $h = [];
    if (function_exists('getallheaders')) {
        foreach ((array) getallheaders() as $k => $v) {
            $h[strtoupper($k)] = $v;
        }
    }
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $h[strtoupper(str_replace('_', '-', substr($k, 5)))] = $v;
        }
    }
    return $h;
}

function hook_header(string $name): string
{
    $h = hook_headers();
    return (string) ($h[strtoupper($name)] ?? '');
}

/**
 * Terminate with a status the gateway understands.
 * 2xx = "we've got it, stop retrying". 4xx = "bad request, don't retry".
 */
function hook_respond(int $status, string $message = 'ok'): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status < 300 ? 'ok' : 'error', 'message' => $message]);
    exit;
}

/** Only POST is ever valid for a webhook. */
function hook_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        hook_respond(405, 'Method not allowed');
    }
}

/** Guard against absurd payloads before doing any work. */
function hook_guard_size(int $maxBytes = 262144): void
{
    if (strlen(hook_raw_body()) > $maxBytes) {
        hook_respond(413, 'Payload too large');
    }
}
