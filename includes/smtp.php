<?php
/**
 * Minimal SMTP client.
 *
 * Deliberately dependency-free: this install has no Composer and no vendor
 * directory, so pulling in PHPMailer would mean vendoring a library and keeping
 * it patched. Everything needed here is in core PHP — stream_socket_client()
 * plus the openssl extension, both confirmed available.
 *
 * Supports the two setups a shared host or Gmail will ask for:
 *   - port 587 with STARTTLS (encryption = 'tls')
 *   - port 465 with implicit TLS (encryption = 'ssl')
 *   - port 25 plain (encryption = 'none') for a local relay
 * and AUTH LOGIN / AUTH PLAIN.
 *
 * Every failure comes back as [false, 'reason'] rather than an exception or a
 * warning, because the caller (includes/mailer.php) must never let a mail
 * problem reach the customer's response body.
 */

/** Read one SMTP reply, following multi-line continuations ("250-" vs "250 "). */
function smtp_read($sock, &$code) {
    $code = 0;
    $out  = '';
    while (($line = fgets($sock, 1024)) !== false) {
        $out .= $line;
        // "250-EXTENSION" continues; "250 OK" is the last line
        if (strlen($line) >= 4 && $line[3] === ' ') {
            $code = (int) substr($line, 0, 3);
            break;
        }
        if (strlen($line) < 4) {
            break;
        }
    }
    return $out;
}

/** Send one command and return true when the reply code is expected. */
function smtp_cmd($sock, $cmd, array $expect, &$reply) {
    if ($cmd !== null) {
        fwrite($sock, $cmd . "\r\n");
    }
    $reply = smtp_read($sock, $code);
    return in_array($code, $expect, true);
}

/**
 * Deliver one message over SMTP.
 *
 * @param array  $cfg      host, port, encryption, auth, username, password, timeout
 * @param string $from     envelope + header From address
 * @param string $fromName display name, may be ''
 * @param array  $to       one or more recipient addresses
 * @param string $subject  raw subject (encoded here)
 * @param string $body     plain-text body
 * @param string $replyTo  optional Reply-To
 * @return array           [bool $ok, string $error]
 */
function smtp_send(array $cfg, $from, $fromName, array $to, $subject, $body, $replyTo = '') {
    $host    = trim((string) ($cfg['host'] ?? ''));
    $port    = (int) ($cfg['port'] ?? 587);
    $enc     = strtolower((string) ($cfg['encryption'] ?? 'tls'));
    $timeout = (int) ($cfg['timeout'] ?? 15);

    if ($host === '') {
        return [false, 'No SMTP host configured.'];
    }
    if (!$to) {
        return [false, 'No recipient.'];
    }
    if ($from === '') {
        return [false, 'No From address configured.'];
    }

    // 465 speaks TLS from the first byte; 587 upgrades with STARTTLS later.
    $prefix = ($enc === 'ssl') ? 'ssl://' : '';
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $sock = @stream_socket_client(
        $prefix . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    if (!$sock) {
        return [false, 'Could not connect to ' . $host . ':' . $port . ' — ' . ($errstr ?: 'no route') . '.'];
    }
    stream_set_timeout($sock, $timeout);

    $reply = '';
    $fail = function ($msg) use ($sock, &$reply) {
        @fwrite($sock, "QUIT\r\n");
        @fclose($sock);
        return [false, trim($msg . ' ' . trim((string) $reply))];
    };

    // greeting
    if (!smtp_cmd($sock, null, [220], $reply)) {
        return $fail('Server did not greet us.');
    }

    $ehloHost = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $ehloHost = preg_replace('/:\d+$/', '', $ehloHost) ?: 'localhost';

    if (!smtp_cmd($sock, 'EHLO ' . $ehloHost, [250], $reply)) {
        // some very old servers only speak HELO
        if (!smtp_cmd($sock, 'HELO ' . $ehloHost, [250], $reply)) {
            return $fail('EHLO refused.');
        }
    }

    if ($enc === 'tls') {
        if (!smtp_cmd($sock, 'STARTTLS', [220], $reply)) {
            return $fail('Server refused STARTTLS. Try port 465 with SSL, or no encryption.');
        }
        $ok = @stream_socket_enable_crypto(
            $sock,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
        );
        if ($ok !== true) {
            return $fail('TLS handshake failed.');
        }
        // the session resets after the upgrade, so greet again
        if (!smtp_cmd($sock, 'EHLO ' . $ehloHost, [250], $reply)) {
            return $fail('EHLO after STARTTLS refused.');
        }
    }

    if (!empty($cfg['auth'])) {
        $user = (string) ($cfg['username'] ?? '');
        $pass = (string) ($cfg['password'] ?? '');
        if ($user === '') {
            return $fail('SMTP authentication is on but no username is set.');
        }
        // AUTH LOGIN first (what Gmail and most shared hosts advertise),
        // falling back to AUTH PLAIN.
        if (smtp_cmd($sock, 'AUTH LOGIN', [334], $reply)) {
            if (!smtp_cmd($sock, base64_encode($user), [334], $reply)) {
                return $fail('Username rejected.');
            }
            if (!smtp_cmd($sock, base64_encode($pass), [235], $reply)) {
                return $fail('Password rejected. For Gmail use a 16-character App Password, not the account password.');
            }
        } else {
            $plain = base64_encode("\0" . $user . "\0" . $pass);
            if (!smtp_cmd($sock, 'AUTH PLAIN ' . $plain, [235], $reply)) {
                return $fail('Authentication failed.');
            }
        }
    }

    if (!smtp_cmd($sock, 'MAIL FROM:<' . $from . '>', [250], $reply)) {
        return $fail('Server rejected the From address <' . $from . '>.');
    }

    $accepted = 0;
    foreach ($to as $rcpt) {
        if (smtp_cmd($sock, 'RCPT TO:<' . $rcpt . '>', [250, 251], $reply)) {
            $accepted++;
        }
    }
    if ($accepted === 0) {
        return $fail('Every recipient was rejected.');
    }

    if (!smtp_cmd($sock, 'DATA', [354], $reply)) {
        return $fail('Server refused DATA.');
    }

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . ($fromName !== ''
        ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>'
        : '<' . $from . '>');
    $headers[] = 'To: ' . implode(', ', array_map(fn($a) => '<' . $a . '>', $to));
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: <' . $replyTo . '>';
    }
    $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: base64';
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>';

    // base64 sidesteps both the 998-character line limit and dot-stuffing
    $payload = implode("\r\n", $headers) . "\r\n\r\n"
             . chunk_split(base64_encode($body), 76, "\r\n");

    fwrite($sock, $payload . "\r\n.\r\n");
    if (!smtp_cmd($sock, null, [250], $reply)) {
        return $fail('Server did not accept the message.');
    }

    @fwrite($sock, "QUIT\r\n");
    @fclose($sock);
    return [true, ''];
}
