<?php

// Ask the installed Composer binary which CA input it validates.
// Capture diagnostic output privately; print only the selected public CA path/hash.
$command = ['/usr/local/bin/composer', '--no-plugins', '--no-scripts', 'diagnose', '-vvv'];
$diagnostic = tempnam(sys_get_temp_dir(), 'composer-ca-');
if ($diagnostic === false) {
    throw new RuntimeException('Cannot create temporary diagnostic file.');
}
chmod($diagnostic, 0600);
try {
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'],
        1 => ['file', $diagnostic, 'a'], 2 => ['file', $diagnostic, 'a']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot invoke Composer diagnostic.');
    }
    $status = proc_close($process);
    $output = file_get_contents($diagnostic);
    if (!preg_match('/Checked CA file (.+?): valid(?:\r?$)/m', $output, $match)) {
        echo json_encode(['result' => 'INCONCLUSIVE', 'diagnose_exit' => $status,
            'reason' => 'Composer did not report a validated CA file. No raw diagnostic output is disclosed.'],
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
        exit(1);
    }
    $detected = trim($match[1]);
    if (!is_file($detected) || !is_readable($detected)) {
        throw new RuntimeException('Composer-reported CA file is not readable.');
    }
    $hash = hash_file('sha256', $detected);
    $phpBundle = ini_get('openssl.cafile');
    $curlBundle = ini_get('curl.cainfo');
    echo json_encode([
        'source' => 'Installed Composer diagnose -vvv (selected CA-file diagnostic only)',
        'diagnose_exit' => $status,
        'detected_bundle' => $detected,
        'sha256' => $hash,
        'same_as_php_bundle' => is_file($phpBundle) && hash_equals(hash_file('sha256', $phpBundle), $hash),
        'same_as_curl_bundle' => is_file($curlBundle) && hash_equals(hash_file('sha256', $curlBundle), $hash),
        'limits' => 'Confirms validated CA-file selection only. Other diagnose checks may fail independently; does not establish TLS chain rejection.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    unlink($diagnostic);
}
