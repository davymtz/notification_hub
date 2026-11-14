<?php

// Fetch authenticated advisory DATA only, never executable code.
$release = trim(file_get_contents('/etc/alpine-release'));
if (!preg_match('/^(\d+\.\d+)\./', $release, $matches)) {
    throw new RuntimeException('Unknown Alpine release format.');
}
$url = 'https://secdb.alpinelinux.org/v'.$matches[1].'/main.json';
$handle = curl_init($url);
curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
$body = curl_exec($handle);
$status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
if ($body === false || $status !== 200) {
    fwrite(STDERR, 'Inconclusive Alpine advisory fetch: HTTP '.$status.' '.curl_error($handle).PHP_EOL);
    exit(1);
}
$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
if (!isset($data['packages']) || !is_array($data['packages'])) {
    throw new RuntimeException('Unexpected Alpine security database schema.');
}
$found = [];
foreach ($data['packages'] as $entry) {
    $package = $entry['pkg'] ?? [];
    if (($package['name'] ?? '') === 'brotli') {
        $found[] = $package;
    }
}
echo json_encode(['source' => $url, 'sha256' => hash('sha256', $body),
    'alpine_release' => $release, 'database_updated' => $data['last_update'] ?? null,
    'brotli_records' => $found,
    'notice' => 'Missing records do not establish absence of vulnerability. Compare the installed revision to vendor fixed revisions.'],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
