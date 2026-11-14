<?php
require '/checks/security-policy.php';
function nhReadReport(string $path): array
{
    if (!is_file($path)) { throw new RuntimeException('Missing report '.basename($path)); }
    $report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($report)) { throw new RuntimeException('Invalid report '.basename($path)); }
    return $report;
}
$issues = [];
$record = static function (string $name, callable $check) use (&$issues): void {
    try {
        $found = $check();
        foreach ($found as $issue) { $issues[] = $name.': '.$issue; }
        echo ($found ? 'FAIL: ' : 'PASS: ').$name.PHP_EOL;
    } catch (Throwable $error) {
        $issues[] = $name.': '.$error->getMessage();
        echo 'INCONCLUSIVE: '.$name.PHP_EOL;
    }
};
foreach (['app', 'worker'] as $service) {
    $record($service.' runtime', fn() => nhRuntimeIssues(nhReadReport('/reports/'.$service.'-runtime.json')));
    $record($service.' trust inventory', fn() => nhTrustIssues(nhReadReport('/reports/'.$service.'-trust.json')));
    $record($service.' TLS chain checks (two affected paths)', function () use ($service) {
        $runtime = nhReadReport('/reports/'.$service.'-runtime.json');
        $hash = '';
        foreach ($runtime['ca_bundles'] ?? [] as $bundle) {
            if (($bundle['path'] ?? '') === '/etc/ssl/certs/ca-certificates.crt') { $hash = $bundle['sha256'] ?? ''; }
        }
        return nhTlsChainIssues(nhReadReport('/reports/'.$service.'-tls-chains.json'), $hash);
    });
}
foreach (['app', 'worker'] as $service) {
    $record($service.' Composer TLS transport', function () use ($service) {
        $transport = nhReadReport('/reports/'.$service.'-composer-tls.json');
        $chains = nhReadReport('/reports/'.$service.'-tls-chains.json');
        $time = strtotime($transport['checked_at'] ?? '');
        if ($time === false || $time < time() - 86400 || $time > time() + 300
            || ($transport['bundle_sha256'] ?? '') !== ($chains['bundle_sha256'] ?? '')
            || count($transport['checks'] ?? []) !== 3) { return ['Composer TLS evidence incomplete, stale or CA bundle differs.']; }
        $expected = ['repo.packagist.org' => 'accept', 'dv-g3b-emudhra.we-have-a-problem.com' => 'reject', 'dv-g3b-aaa-emudhra.we-have-a-problem.com' => 'reject'];
        foreach ($transport['checks'] as $check) {
            $host = $check['host'] ?? '';
            if (!isset($expected[$host])) { return ['Unexpected Composer TLS target.']; }
            $outcome = $expected[$host]; unset($expected[$host]);
            if (($check['expected'] ?? '') !== $outcome || !nhComposerTlsOutcomeAccepted($check)) {
                return ['Composer TLS result inconclusive for '.$host.'.'];
            }
        }
        return [];
    });
}
$record('Composer audit', fn() => nhAuditIssues(nhReadReport('/reports/composer-audit.json')));
$record('Composer CA selection', function () {
    $ca = nhReadReport('/reports/composer-ca.json');
    return ($ca['same_as_php_bundle'] ?? false) === true && ($ca['same_as_curl_bundle'] ?? false) === true
        ? [] : ['Effective Composer CA selection inconclusive or differs from reviewed PHP/cURL bundle.'];
});
$record('FPM policy', function () {
    $text = is_file('/reports/fpm-settings.txt') ? file_get_contents('/reports/fpm-settings.txt') : '';
    return preg_match('/session\.upload_progress\.enabled => Off/', $text)
        && str_contains($text, 'openssl.cafile => /etc/ssl/certs/ca-certificates.crt')
        && str_contains($text, 'curl.cainfo => /etc/ssl/certs/ca-certificates.crt') ? [] : ['FPM configuration missing or unsafe.'];
});
$record('Composer trust policy', function () {
    $policy = nhReadReport('/reports/composer-policy.json');
    return ($policy['secure_http'] ?? null) === true && ($policy['disable_tls'] ?? null) === false
        && ($policy['allow_plugins'] ?? null) === false ? [] : ['Composer TLS/plugin policy missing or unsafe.'];
});
foreach (['app', 'nginx', 'redis', 'mailpit'] as $service) {
    $record($service.' image scan', function () use ($service) {
        $metadata = file_get_contents('/reports/'.$service.'-image.txt');
        if (!preg_match('/ID=(sha256:[a-f0-9]{64}) Architecture=(arm64|amd64)/', $metadata, $matches)) {
            throw new RuntimeException('Image identity or architecture missing.');
        }
        $sbom = nhReadReport('/reports/'.$service.'.spdx.json');
        if (!isset($sbom['spdxVersion'], $sbom['packages'])) { throw new RuntimeException('SPDX report incomplete.'); }
        $review = nhReadReport('/reports/high-findings-review.json');
        if (strtotime($review['checked_at'] ?? '') < time() - 86400) { throw new RuntimeException('High-advisory review missing or older than one day.'); }
        return nhSarifIssues(nhReadReport('/reports/'.$service.'-cves.sarif.json'), $service, $matches[1], $review);
    });
}
$record('Scout installation and observed advisory freshness', function () {
    $files = ['version' => 'scout-version.txt', 'binary_checksum' => 'scout-binary-sha256.txt',
        'identity' => 'scout-signature-identity.txt', 'signature_verification' => 'scout-signature-verification.txt', 'context' => 'context.txt'];
    $evidence = [];
    foreach ($files as $key => $file) {
        if (!is_file('/reports/'.$file)) { throw new RuntimeException('Missing '.$file); }
        $evidence[$key] = file_get_contents('/reports/'.$file);
    }
    $evidence['mailpit_sarif'] = nhReadReport('/reports/mailpit-cves.sarif.json');
    return nhScannerIssues($evidence);
});
// These requirements have no conclusive executed evidence yet. Keep acceptance
// closed until actual fixtures/provenance/triage exist, not merely a green scan.
foreach (['individual assessment of remaining Go/system and historical libxml2 findings'] as $pending) {
    $issues[] = 'PENDING: '.$pending;
}
echo json_encode(['accepted' => !$issues, 'issues' => $issues,
    'limits' => 'A clean scan does not substitute chain tests, provenance, or review of backported packages.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($issues ? 1 : 0);
