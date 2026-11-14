<?php

// Fetch authenticated advisory DATA only, never executable code.
$release = trim(file_get_contents('/etc/alpine-release'));
if (!preg_match('/^(\d+\.\d+)\./', $release, $matches)) {
    throw new RuntimeException('Unknown Alpine release format.');
}
$branch = getenv('NH_ADVISORY_BRANCH') ?: $matches[1];
if (!in_array($branch, ['3.23', '3.24'], true)) {
    throw new RuntimeException('Unsupported Alpine advisory branch.');
}
$url = 'https://secdb.alpinelinux.org/v'.$branch.'/main.json';
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
    if (in_array($package['name'] ?? '', ['brotli', 'curl', 'musl', 'libxml2', 'unzip', 'tar', 'busybox'], true)) {
        $fixes = [];
        foreach ($package['secfixes'] ?? [] as $version => $advisories) {
            $relevant = array_values(array_intersect($advisories, ['CVE-2026-111497', 'CVE-2026-92392', 'CVE-2026-107891', 'CVE-2026-86140', 'CVE-2008-0888', 'CVE-2025-60876', 'CVE-2025-45582', 'CVE-2026-18477', 'CVE-2026-18508', 'CVE-2026-11979', 'CVE-2025-8732', 'CVE-2026-86137', 'CVE-2026-86141', 'CVE-2026-0989', 'CVE-2026-86144', 'CVE-2026-86138', 'CVE-2026-86139', 'CVE-2026-86142', 'CVE-2026-86143']));
            if ($relevant) { $fixes[$version] = $relevant; }
        }
        $found[] = ['name' => $package['name'], 'relevant_secfixes' => $fixes];
    }
}
echo json_encode(['source' => $url, 'sha256' => hash('sha256', $body),
    'query_client_alpine_release' => $release, 'advisory_branch' => $branch, 'database_updated' => $data['last_update'] ?? null,
    'package_records' => $found,
    'notice' => 'Missing records do not establish absence of vulnerability. Compare the installed revision to vendor fixed revisions.'],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
