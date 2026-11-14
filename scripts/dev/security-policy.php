<?php

// Pure policy functions shared by the live gate and deterministic negative tests.
function nhRuntimeIssues(array $runtime): array
{
    $issues = [];
    if (!is_string($runtime['php'] ?? null) || version_compare($runtime['php'], '8.4.27', '<')) {
        $issues[] = 'PHP is missing or below 8.4.27.';
    }
    if (($runtime['upload_progress_disabled'] ?? null) !== true) { $issues[] = 'PHP upload progress must be disabled.'; }
    foreach (['version' => '8.23.0', 'brotli_version' => '1.2.1'] as $key => $minimum) {
        $version = $runtime['curl'][$key] ?? '';
        $pattern = $key === 'brotli_version' ? '/^(?:brotli\/)?(\d+\.\d+\.\d+)$/D' : '/^(\d+\.\d+\.\d+)$/D';
        if (!is_string($version) || !preg_match($pattern, $version, $match) || version_compare($match[1], $minimum, '<')) {
            $issues[] = 'cURL '.$key.' missing or below '.$minimum.'.';
        }
    }
    foreach (['openssl.cafile', 'curl.cainfo'] as $key) {
        if (($runtime['ini'][$key] ?? '') !== '/etc/ssl/certs/ca-certificates.crt') { $issues[] = 'Unexpected effective '.$key.'.'; }
    }
    return $issues;
}
function nhAuditIssues(array $audit): array
{
    if (!array_key_exists('advisories', $audit) || !is_array($audit['advisories'])) { return ['Composer audit is incomplete.']; }
    return $audit['advisories'] ? ['Composer reports vulnerability advisories.'] : [];
}
function nhTrustIssues(array $trust): array
{
    if (!is_int($trust['unique_certificates'] ?? null) || $trust['unique_certificates'] < 1
        || !is_array($trust['affected_trust_keys_found'] ?? null)) { return ['Trust inventory is incomplete.']; }
    return $trust['affected_trust_keys_found'] ? ['Affected trust hierarchy is present.'] : [];
}
function nhSarifIssues(array $sarif, string $service, string $image, array $review): array
{
    if (($sarif['version'] ?? '') !== '2.1.0' || count($sarif['runs'] ?? []) !== 1
        || !isset($sarif['runs'][0]['tool']['driver']['rules']) || !is_array($sarif['runs'][0]['results'] ?? null)) {
        return ['SARIF missing or unexpected schema.'];
    }
    $issues = [];
    foreach ($sarif['runs'][0]['tool']['driver']['rules'] as $rule) {
        $properties = $rule['properties'] ?? [];
        $severity = strtoupper($properties['cvssV3_severity'] ?? 'UNSPECIFIED');
        $score = $properties['security-severity'] ?? null;
        $blocking = in_array($severity, ['HIGH', 'CRITICAL'], true) || (is_numeric($score) && (float) $score >= 7);
        if (!$blocking) { continue; }
        $accepted = false;
        // Exact reviewed image, advisory and package only; never an ID-wide ignore.
        foreach ($review['reviews'] ?? [] as $decision) {
            if (($decision['advisory'] ?? '') !== ($rule['id'] ?? '')) { continue; }
            $purls = $properties['purls'] ?? [];
            if ($service === 'app' && $image === 'sha256:7cea7546d993f42742334b632e2d349285779918cc3c08923fbb68fa8475e839'
                && $decision['advisory'] === 'CVE-2008-0888'
                && ($decision['result'] ?? '') === 'not_affected_by_CNA_version_range'
                && $purls === ['pkg:apk/alpine/unzip@6.0-r16?os_name=alpine&os_version=3.24']) { $accepted = true; }
            if ($service === 'mailpit' && $image === ($decision['image'] ?? '')
                && $decision['advisory'] === 'CVE-2023-42821'
                && ($decision['result'] ?? '') === 'fix_ancestry_and_source_guard_confirmed'
                && $purls === ['pkg:golang/github.com/gomarkdown/markdown@0.0.0-20261022144421-6c848fa1b723']) { $accepted = true; }
        }
        if (!$accepted) { $issues[] = $service.': blocking advisory '.($rule['id'] ?? 'unknown').'.'; }
    }
    return $issues;
}

function nhTlsChainIssues(array $report, string $bundleHash): array
{
    $checked = strtotime($report['checked_at'] ?? '');
    if ($checked === false || $checked > time() + 300 || $checked < time() - 86400
        || ($report['bundle_sha256'] ?? '') !== $bundleHash || !preg_match('/^[a-f0-9]{64}$/D', $bundleHash)) {
        return ['TLS chain report missing, stale or bound to another CA bundle.'];
    }
    $expected = [
        'repo.packagist.org' => ['accept', null],
        'dv-g3b-emudhra.we-have-a-problem.com' => ['reject', '42c4accd12c4f59597925e1a08a7a90102608543809308d50ec427e118ba1493'],
        'dv-g3b-aaa-emudhra.we-have-a-problem.com' => ['reject', '45a883f349aedfd5f3a649b5498ffb121712ba8b448f3f2a91dbc5a766fcddbe'],
    ];
    $checks = $report['checks'] ?? [];
    if (!is_array($checks) || count($checks) !== count($expected)) { return ['TLS chain matrix incomplete.']; }
    foreach ($checks as $check) {
        $host = $check['host'] ?? '';
        if (!isset($expected[$host])) { return ['Unexpected or duplicate TLS chain target.']; }
        [$outcome, $fingerprint] = $expected[$host];
        unset($expected[$host]);
        $openssl = $outcome === 'accept' ? 'valid_chain_accepted' : 'trust_rejection_confirmed';
        $streams = $outcome === 'accept' ? 'valid_chain_accepted' : 'certificate_rejection_confirmed';
        if (($check['expected'] ?? '') !== $outcome || ($check['result'] ?? '') !== 'pass'
            || ($check['openssl'] ?? '') !== $openssl || ($check['curl'] ?? '') !== $openssl
            || ($check['php_streams'] ?? '') !== $streams
            || ($fingerprint !== null && ($check['observed_intermediate_der_sha256'] ?? '') !== $fingerprint)) {
            return ['TLS chain outcome or identity inconclusive for '.$host.'.'];
        }
    }
    return [];
}

function nhComposerTlsOutcomeAccepted(array $check): bool
{
    if (($check['expected'] ?? '') === 'accept') {
        return ($check['exit'] ?? null) === 0 && ($check['result'] ?? '') === 'pass'
            && ($check['composer'] ?? '') === 'valid_chain_accepted';
    }
    $exit = $check['exit'] ?? null;
    if (($check['expected'] ?? '') !== 'reject' || !is_int($exit) || $exit === 0 || $exit === 124) { return false; }
    if (($check['result'] ?? '') === 'pass' && ($check['composer'] ?? '') === 'trust_rejection_confirmed'
        && ($check['curl_error_60'] ?? false) === true && ($check['issuer_trust_error'] ?? false) === true) { return true; }
    // Retain original reports and reinterpret the exact diagnostic from helper v2.
    // Console can insert newlines inside words as well as between words.
    $diagnostic = $check['diagnostic'] ?? '';
    if (!is_string($diagnostic)) { return false; }
    $compact = strtolower(preg_replace('/\s+/', '', $diagnostic));
    return str_contains($compact, 'curlerror60')
        && (str_contains($compact, 'unabletogetlocalissuercertificate')
            || str_contains($compact, 'unabletoverifythefirstcertificate')
            || str_contains($compact, 'unabletoverifyfirstcertificate'));
}

function nhScannerIssues(array $evidence): array
{
    if (!str_contains($evidence['version'] ?? '', 'version: v1.24.0')
        || !str_contains($evidence['version'] ?? '', 'b1c9331b2166aef7ec690aa16fd655b8798ea4c6')
        || !preg_match('/^11c19e990ecc3ee9a3159208b407e143d6beb601dea42ff5745e9cf7d1a22bfe\s/', $evidence['binary_checksum'] ?? '')) {
        return ['Scout version/commit/binary differs from reviewed installation.'];
    }
    $identity = $evidence['identity'] ?? '';
    if (!str_contains($identity, 'Executable=/Applications/Docker.app/Contents/Resources/cli-plugins/docker-scout')
        || !str_contains($identity, 'Authority=Developer ID Application: Docker Inc (9BNSXJN65R)')
        || !str_contains($identity, 'TeamIdentifier=9BNSXJN65R')
        || !str_contains($identity, 'Authority=Apple Root CA')
        || !str_contains($evidence['signature_verification'] ?? '', 'valid on disk')
        || !str_contains($evidence['signature_verification'] ?? '', 'satisfies its Designated Requirement')) {
        return ['Scout installation signature or publisher identity not established.'];
    }
    $context = $evidence['context'] ?? '';
    if (!preg_match('/^finished_utc=(.+)$/m', $context, $match)
        || ($finished = strtotime($match[1])) === false || $finished > time() + 300 || $finished < time() - 86400) {
        return ['Scan collection is missing or older than the one-day acceptance window.'];
    }
    // Published by the Go CNA on 2026-10-27. This establishes a minimum observed
    // advisory age; it is not an immutable database snapshot/version identifier.
    $ids = array_column($evidence['mailpit_sarif']['runs'][0]['tool']['driver']['rules'] ?? [], 'id');
    if (!in_array('CVE-2026-78661', $ids, true) || !in_array('CVE-2026-97045', $ids, true)) {
        return ['Recent official advisory freshness controls missing; review source age before acceptance.'];
    }
    return [];
}
