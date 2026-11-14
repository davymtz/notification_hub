<?php
// Public researcher test hosts linked by the Kudelski incident advisory.
// No private keys, unverified downloads, TLS bypasses or installed packages.
$targets = [
    ['host' => 'repo.packagist.org', 'expect' => 'accept'],
    ['host' => 'dv-g3b-emudhra.we-have-a-problem.com', 'expect' => 'reject',
        'intermediate_der_sha256' => '42c4accd12c4f59597925e1a08a7a90102608543809308d50ec427e118ba1493'],
    ['host' => 'dv-g3b-aaa-emudhra.we-have-a-problem.com', 'expect' => 'reject',
        'intermediate_der_sha256' => '45a883f349aedfd5f3a649b5498ffb121712ba8b448f3f2a91dbc5a766fcddbe'],
];
function nhTlsCommand(array $command): array
{
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start TLS check process.'); }
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $output."\n".$error];
}
$results = [];
foreach ($targets as $target) {
    $host = $target['host']; $reject = $target['expect'] === 'reject';
    $row = ['host' => $host, 'expected' => $target['expect']];
    try {
        [$status, $output] = nhTlsCommand(['timeout', '25', 'openssl', 's_client', '-connect', $host.':443',
            '-servername', $host, '-verify_hostname', $host, '-verify_return_error', '-showcerts',
            '-CAfile', '/etc/ssl/certs/ca-certificates.crt']);
        if ($reject) {
            if ($status === 0 || $status === 124 || !preg_match('/(?:verify error:num=|Verify return code: )(20|21)\b/', $output)) {
                throw new RuntimeException('OpenSSL did not conclusively reject for missing trust (20/21).');
            }
            preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $output, $certs);
            $intermediate = null;
            foreach ($certs[0] as $pem) {
                $parsed = openssl_x509_parse($pem);
                if (!$parsed || ($parsed['validFrom_time_t'] ?? PHP_INT_MAX) > time() || ($parsed['validTo_time_t'] ?? 0) <= time()) {
                    throw new RuntimeException('Presented certificate validity inconclusive; not a pure trust-rejection test.');
                }
                if (openssl_x509_fingerprint($pem, 'sha256') === $target['intermediate_der_sha256']) { $intermediate = $pem; }
            }
            if ($intermediate === null || !isset($certs[0][0]) || openssl_x509_verify($certs[0][0], openssl_pkey_get_public($intermediate)) !== 1) {
                throw new RuntimeException('Known affected intermediate and its signature on the leaf were not established.');
            }
            $leaf = tempnam('/tmp', 'nh-tls-leaf-');
            if ($leaf === false) { throw new RuntimeException('Cannot stage public leaf certificate.'); }
            try {
                file_put_contents($leaf, $certs[0][0]);
                [$nameStatus] = nhTlsCommand(['openssl', 'x509', '-in', $leaf, '-noout', '-checkhost', $host]);
                if ($nameStatus !== 0) { throw new RuntimeException('Leaf hostname mismatch; trust-only rejection inconclusive.'); }
            } finally { unlink($leaf); }
            $row['observed_intermediate_der_sha256'] = $target['intermediate_der_sha256'];
        } elseif ($status !== 0 || !str_contains($output, 'Verify return code: 0 (ok)')) {
            throw new RuntimeException('OpenSSL valid-chain control failed.');
        }
        $row['openssl'] = $reject ? 'trust_rejection_confirmed' : 'valid_chain_accepted';
        $curl = curl_init('https://'.$host.'/');
        curl_setopt_array($curl, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false]);
        $response = curl_exec($curl); $errno = curl_errno($curl);
        $verification = curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT);
        if ($reject ? ($errno !== 60 || !in_array($verification, [20, 21], true)) : ($response === false || $errno !== 0 || $verification !== 0)) {
            throw new RuntimeException('cURL outcome inconclusive: errno '.$errno.', verify '.$verification.'.');
        }
        $row['curl'] = $reject ? 'trust_rejection_confirmed' : 'valid_chain_accepted';
        $warning = '';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true,
            'allow_self_signed' => false, 'peer_name' => $host, 'SNI_enabled' => true,
            'cafile' => ini_get('openssl.cafile')]]);
        set_error_handler(static function ($severity, $message) use (&$warning) { $warning .= $message; return true; });
        try { $stream = stream_socket_client('tls://'.$host.':443', $streamErrno, $streamError, 25, STREAM_CLIENT_CONNECT, $context); }
        finally { restore_error_handler(); }
        if (is_resource($stream)) { fclose($stream); }
        if ($reject ? ($stream !== false || !str_contains($warning, 'certificate verify failed')) : ($stream === false)) {
            throw new RuntimeException('PHP streams did not produce the expected certificate result.');
        }
        $row['php_streams'] = $reject ? 'certificate_rejection_confirmed' : 'valid_chain_accepted';
        $row['result'] = 'pass';
    } catch (Throwable $error) {
        $row['result'] = 'inconclusive_or_failed'; $row['reason'] = $error->getMessage();
    }
    $results[] = $row;
}
echo json_encode(['checked_at' => gmdate(DATE_ATOM), 'bundle_sha256' => hash_file('sha256', '/etc/ssl/certs/ca-certificates.crt'),
    'sources' => ['https://emudhra.we-have-a-problem.com/',
        'https://raw.githubusercontent.com/galadran/we-have-a-problem.com/main/impacted-intermediates.json'],
    'checks' => $results,
    'limits' => 'Two affected TLS paths only. Composer transport still requires its own check; no global incident acceptance. Researcher endpoints may change or be unavailable; that remains inconclusive.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
foreach ($results as $result) { if ($result['result'] !== 'pass') { exit(1); } }
