<?php
/**
 * Outgoing notification mail.
 *
 * Every form on the site routes through here so there is exactly one place that
 * decides who gets told about what. Recipients are configured per module in
 * Admin -> Settings -> Notifications; nothing is hardcoded.
 *
 * Two rules this file exists to enforce:
 *
 *  1. Sending must never affect the response body. PHP's mail() emits a warning
 *     when no SMTP relay is reachable (the default on XAMPP), and that warning
 *     printed ahead of a JSON body is what made the Contact Us form report
 *     "Something went wrong" while the submission had in fact succeeded.
 *     Everything here is silenced and the return value is advisory only.
 *
 *  2. A failed send must never lose the message. Every attempt is appended to
 *     storage/mail.log (web-denied) whether or not the transport worked, so a
 *     box with no SMTP still leaves a full record. The submission itself is
 *     already stored in the database by the caller.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/smtp.php';

/**
 * Modules that can have their own recipient, and the label the admin sees.
 * Add a key here and it appears in Admin -> Settings automatically.
 */
function mail_modules() {
    return [
        'contact'     => 'Contact Us form',
        'appointment' => 'Appointment requests',
        'enquiry'     => 'Product & scheme enquiries',
        'feedback'    => 'Customer feedback',
        'order'       => 'New orders',
    ];
}

/**
 * Split a settings value into valid addresses.
 * Accepts comma or semicolon separated lists so a module can notify a team.
 *
 * @return string[]
 */
function mail_parse_addresses($raw) {
    $out = [];
    foreach (preg_split('/[,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $one) {
        $one = trim($one);
        if ($one !== '' && filter_var($one, FILTER_VALIDATE_EMAIL)) {
            $out[] = $one;
        }
    }
    return array_values(array_unique($out));
}

/**
 * Who should be told about $module.
 *
 * Falls back to the site contact address when the module has no override, so
 * an admin only has to fill in the ones that differ.
 *
 * @return string[]  zero or more validated addresses
 */
function mail_recipients($module) {
    $specific = mail_parse_addresses(get_setting('notify_email_' . $module, ''));
    if ($specific) {
        return $specific;
    }
    return mail_parse_addresses(SITE_EMAIL);
}

/** The From address. Must be a domain we control, never the customer's. */
function mail_from_address() {
    $from = mail_parse_addresses(get_setting('notify_email_from', ''));
    if ($from) {
        return $from[0];
    }
    $site = mail_parse_addresses(SITE_EMAIL);
    return $site ? $site[0] : '';
}

/** Are notification emails switched on at all? */
function mail_notifications_enabled() {
    return get_setting('notify_enabled', '1') === '1';
}

/** Append one attempt to storage/mail.log. Best effort, never throws. */
function mail_log($module, array $to, $subject, $body, $status) {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $entry = str_repeat('=', 70) . PHP_EOL
        . '[' . date('Y-m-d H:i:s') . '] module=' . $module . ' status=' . $status . PHP_EOL
        . 'To: ' . ($to ? implode(', ', $to) : '(no recipient configured)') . PHP_EOL
        . 'Subject: ' . $subject . PHP_EOL . PHP_EOL
        . $body . PHP_EOL;
    @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
}

/** 'smtp' or 'mail' — Admin -> Settings -> Mail Server. */
function mail_transport() {
    return get_setting('mail_transport', 'mail') === 'smtp' ? 'smtp' : 'mail';
}

/** The display name on outgoing mail. Falls back to the site name. */
function mail_from_name() {
    $name = trim((string) get_setting('notify_from_name', ''));
    return $name !== '' ? $name : SITE_NAME;
}

/**
 * Normalise a stored SMTP password.
 *
 * Google displays an App Password as four groups of four — "abcd efgh ijkl
 * mnop" — but the credential is the 16 characters without the spaces, and
 * Gmail rejects the spaced form. People copy what they see, so strip the
 * spaces when the value has exactly that shape.
 *
 * Only that shape. Other providers do allow spaces inside a mailbox password,
 * and silently mangling one of those would be a worse bug than the one this
 * avoids. Everything else just gets the ends trimmed.
 */
function mail_normalise_password($raw) {
    $raw = trim((string) $raw);
    if (preg_match('/^[A-Za-z0-9]{4}(?:[ \t]+[A-Za-z0-9]{4}){3}$/', $raw)) {
        return preg_replace('/\s+/', '', $raw);
    }
    return $raw;
}

/**
 * SMTP connection settings as smtp_send() wants them.
 *
 * $timeout is short for storefront notifications on purpose: the send happens
 * inline with the customer's form submit, so an SMTP host that accepts the TCP
 * connection but never answers would otherwise hold their request open for the
 * full timeout. The admin's test button passes a longer one, because there a
 * slow, truthful answer beats a fast, vague one.
 */
function mail_smtp_config($timeout = 8) {
    $enc = strtolower((string) get_setting('smtp_encryption', 'tls'));
    if (!in_array($enc, ['none', 'tls', 'ssl'], true)) {
        $enc = 'tls';
    }
    return [
        'host'       => trim((string) get_setting('smtp_host', '')),
        'port'       => (int) (get_setting('smtp_port', '587') ?: 587),
        'encryption' => $enc,
        'auth'       => get_setting('smtp_auth', '1') === '1',
        'username'   => trim((string) get_setting('smtp_username', '')),
        'password'   => mail_normalise_password(get_setting('smtp_password', '')),
        'timeout'    => max(3, (int) $timeout),
    ];
}

/**
 * Hand one message to whichever transport is configured.
 * @return array [bool $ok, string $error]
 */
function mail_deliver(array $to, $subject, $body, $replyTo = '', $timeout = 8) {
    $from = mail_from_address();
    if ($from === '') {
        return [false, 'No From address configured.'];
    }

    if (mail_transport() === 'smtp') {
        return smtp_send(mail_smtp_config($timeout), $from, mail_from_name(), $to, $subject, $body, $replyTo);
    }

    $name = mail_from_name();
    $headers = [];
    $headers[] = 'From: ' . ($name !== ''
        ? '=?UTF-8?B?' . base64_encode($name) . '?= <' . $from . '>'
        : '<' . $from . '>');
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    // Silenced deliberately - see the note at the top of this file.
    $sent = @mail(
        implode(', ', $to),
        '=?UTF-8?B?' . base64_encode($subject) . '?=',
        $body,
        implode("\r\n", $headers)
    );
    return [(bool) $sent, $sent ? '' : 'PHP mail() could not hand the message to a local relay.'];
}

/**
 * Send a notification about $module.
 *
 * @param string   $module   a key from mail_modules()
 * @param string   $subject  plain subject line (newlines stripped)
 * @param array    $fields   label => value pairs rendered as the body
 * @param string   $replyTo  the customer's address, if they gave one
 * @return bool              true when the transport accepted it; advisory only,
 *                           callers must not surface this to the customer
 */
function send_notification($module, $subject, array $fields, $replyTo = '') {
    $subject = trim(preg_replace('/\s+/', ' ', (string) $subject));

    $body = '';
    foreach ($fields as $label => $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $body .= $label . ': ' . $value . PHP_EOL;
    }
    $body .= PHP_EOL . 'Sent from ' . (SITE_NAME !== '' ? SITE_NAME : 'the website')
           . ' on ' . date('d M Y \a\t g:i A') . '.' . PHP_EOL;

    if (!mail_notifications_enabled()) {
        mail_log($module, [], $subject, $body, 'disabled');
        return false;
    }

    $to = mail_recipients($module);
    if (!$to) {
        // No address configured anywhere. The submission is already saved, so
        // this is a configuration gap to record, not a customer-facing error.
        mail_log($module, [], $subject, $body, 'no-recipient');
        return false;
    }

    // Always send as ourselves; the customer goes in Reply-To. Putting their
    // address in From fails SPF/DKIM at the receiving end and gets us dropped.
    [$sent, $error] = mail_deliver($to, $subject, $body, trim((string) $replyTo));

    mail_log(
        $module,
        $to,
        $subject,
        $body,
        ($sent ? 'sent' : 'transport-failed') . ' via ' . mail_transport()
            . ($error !== '' ? ' — ' . $error : '')
    );
    return (bool) $sent;
}
