<?php
/**
 * Minimal cURL client for the gateway REST APIs.
 *
 * Composer is not available in this deployment, so we speak the gateways'
 * documented REST endpoints directly instead of pulling in their SDKs.
 * TLS verification is always on — never disable it to "make it work".
 */

/**
 * @param string $method  GET|POST|PATCH
 * @param string $url
 * @param array  $opts    body(array|string) json(bool) headers(array)
 *                        basic([user,pass]) bearer(string) timeout(int)
 * @return array{ok:bool,status:int,body:string,json:array|null,error:string}
 */
function pay_http(string $method, string $url, array $opts = []): array
{
    $timeout = (int) ($opts['timeout'] ?? 30);
    $headers = $opts['headers'] ?? [];
    $body    = $opts['body'] ?? null;

    $ch = curl_init();
    $curlOpts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_ENCODING       => '',
    ];

    if ($body !== null) {
        if (!empty($opts['json'])) {
            $payload   = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        } else {
            $payload = is_string($body) ? $body : http_build_query($body);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $curlOpts[CURLOPT_POSTFIELDS] = $payload;
    }

    if (!empty($opts['basic'])) {
        $curlOpts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        $curlOpts[CURLOPT_USERPWD]  = $opts['basic'][0] . ':' . $opts['basic'][1];
    }
    if (!empty($opts['bearer'])) {
        $headers[] = 'Authorization: Bearer ' . $opts['bearer'];
    }
    $headers[] = 'Accept: application/json';
    $curlOpts[CURLOPT_HTTPHEADER] = $headers;

    curl_setopt_array($ch, $curlOpts);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null,
                'error' => $err ?: 'Could not reach the payment gateway.'];
    }

    $json = json_decode($raw, true);
    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => $status,
        'body'   => $raw,
        'json'   => is_array($json) ? $json : null,
        'error'  => '',
    ];
}

/** Pull a human-readable error out of a gateway error body (never a secret). */
function pay_http_error(array $res, string $fallback = 'The payment gateway rejected the request.'): string
{
    $j = $res['json'] ?? null;
    if (is_array($j)) {
        foreach ([['error', 'description'], ['error', 'message'], ['message'], ['error_description'], ['detail']] as $path) {
            $cur = $j;
            foreach ($path as $seg) {
                if (!is_array($cur) || !isset($cur[$seg])) { $cur = null; break; }
                $cur = $cur[$seg];
            }
            if (is_string($cur) && $cur !== '') {
                return mb_substr($cur, 0, 300);
            }
        }
        if (!empty($j['details'][0]['description'])) {
            return mb_substr((string) $j['details'][0]['description'], 0, 300);
        }
    }
    if ($res['error'] !== '') {
        return mb_substr($res['error'], 0, 300);
    }
    return $fallback;
}
