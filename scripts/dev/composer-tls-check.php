<?php
// Query Composer repository metadata only. No package installation/plugins/scripts.
$bundle = ini_get('openssl.cafile');
if ($bundle !== '/etc/ssl/certs/ca-certificates.crt' || !is_readable($bundle)) {
    throw new RuntimeException('Composer test CA bundle differs from reviewed runtime.');
}
$directory = tempnam('/tmp', 'nh-composer-tls-');
if ($directory === false || !unlink($directory) || !mkdir($directory, 0700)) {
    throw new RuntimeException('Cannot create isolated Composer TLS workspace.');
}
$results = [];
try {
    foreach (['repo.packagist.org' => false,
        'dv-g3b-emudhra.we-have-a-problem.com' => true,
        'dv-g3b-aaa-emudhra.we-have-a-problem.com' => true] as $host => $reject) {
        $manifest = ['name' => 'notification-hub/tls-review', 'repositories' => [
            ['type' => 'composer', 'url' => 'https://'.$host], ['packagist.org' => false]],
            'config' => ['secure-http' => true, 'disable-tls' => false, 'allow-plugins' => false, 'cafile' => $bundle]];
        file_put_contents($directory.'/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $command = ['timeout', '45', '/usr/local/bin/composer', '--no-ansi', '--no-plugins', '--no-scripts',
            '--no-interaction', '--working-dir='.$directory, 'show', 'psr/log', '--all', '--format=json'];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
            $directory, ['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                'HOME' => $directory, 'COMPOSER_HOME' => $directory.'/home', 'COMPOSER_CACHE_DIR' => $directory.'/cache']);
        if (!is_resource($process)) { throw new RuntimeException('Cannot invoke Composer TLS control.'); }
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($process);
        // Console diagnostics may wrap; inspect both channels without ANSI.
        $diagnostic = preg_replace('/\s+/', ' ', str_replace($directory, '[temporary-workspace]', $error."\n".$output));
        $curl60 = (bool) preg_match('/curl error\s+60\b/i', $diagnostic);
        $compact = strtolower(preg_replace('/\s+/', '', $diagnostic));
        $issuerError = str_contains($compact, 'unabletogetlocalissuercertificate')
            || str_contains($compact, 'unabletoverifythefirstcertificate')
            || str_contains($compact, 'unabletoverifyfirstcertificate');
        $valid = false;
        if ($reject) {
            // A timeout, DNS failure, HTTP error or missing package never counts.
            $valid = $status !== 0 && $status !== 124 && $curl60 && $issuerError;
        } elseif ($status === 0) {
            $metadata = json_decode($output, true);
            $valid = ($metadata['name'] ?? '') === 'psr/log';
        }
        $results[] = ['host' => $host, 'expected' => $reject ? 'reject' : 'accept',
            'composer' => $valid ? ($reject ? 'trust_rejection_confirmed' : 'valid_chain_accepted') : 'inconclusive_or_failed',
            'exit' => $status, 'curl_error_60' => $curl60, 'issuer_trust_error' => $issuerError,
            // Only public URLs and an isolated temporary HOME/config were used.
            'diagnostic' => $valid ? null : substr($diagnostic, 0, 3000),
            'result' => $valid ? 'pass' : 'inconclusive_or_failed'];
    }
} finally {
    // Remove only the workspace generated above, never project vendor/config.
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') { $remove($path.'/'.$entry); }
            }
            rmdir($path);
        } else { unlink($path); }
    };
    $remove($directory);
}
echo json_encode(['check_version' => 2, 'checked_at' => gmdate(DATE_ATOM), 'bundle_sha256' => hash_file('sha256', $bundle),
    'checks' => $results,
    'limits' => 'Actual installed Composer transport with an isolated repository config and the reviewed effective CA bundle; no packages installed. Interpret alongside pinned-chain OpenSSL/PHP/cURL results. Two affected TLS paths only.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
foreach ($results as $result) { if ($result['result'] !== 'pass') { exit(1); } }
