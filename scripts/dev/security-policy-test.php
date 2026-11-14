<?php
require '/checks/security-policy.php';
$good = ['php' => '8.4.27', 'upload_progress_disabled' => true,
    'curl' => ['version' => '8.23.0', 'brotli_version' => '1.2.1'],
    'ini' => ['openssl.cafile' => '/etc/ssl/certs/ca-certificates.crt', 'curl.cainfo' => '/etc/ssl/certs/ca-certificates.crt']];
$checks = [];
$checks['valid runtime accepted'] = nhRuntimeIssues($good) === [];
$prefixed = $good; $prefixed['curl']['brotli_version'] = 'brotli/1.2.1';
$checks['observed prefixed Brotli version accepted'] = nhRuntimeIssues($prefixed) === [];
$prefixed['curl']['brotli_version'] = 'brotli/1.2.0';
$checks['vulnerable prefixed Brotli version rejected'] = nhRuntimeIssues($prefixed) !== [];
$prefixed['curl']['brotli_version'] = 'garbage1.2.1';
$checks['malformed Brotli version rejected'] = nhRuntimeIssues($prefixed) !== [];
$prefixed['curl']['brotli_version'] = 'brotli/1.2.1junk';
$checks['Brotli suffix junk rejected'] = nhRuntimeIssues($prefixed) !== [];
$bad = $good; $bad['php'] = '8.4.26';
$checks['vulnerable runtime rejected'] = nhRuntimeIssues($bad) !== [];
$bad = $good; $bad['upload_progress_disabled'] = false;
$checks['unsafe ini rejected'] = nhRuntimeIssues($bad) !== [];
$checks['failed audit query rejected'] = nhAuditIssues([]) !== [];
$checks['clean audit accepted'] = nhAuditIssues(['advisories' => []]) === [];
$checks['audit advisory rejected'] = nhAuditIssues(['advisories' => ['example/package' => [['severity' => 'critical']]]]) !== [];
$checks['inconclusive TLS rejected'] = nhTrustIssues([]) !== [];
$checks['affected hierarchy rejected'] = nhTrustIssues(['unique_certificates' => 1, 'affected_trust_keys_found' => [['sha256_spki' => 'fixture']]]) !== [];
$checks['missing SARIF rejected'] = nhSarifIssues([], 'app', 'fixture', []) !== [];
$report = ['version' => '2.1.0', 'runs' => [['tool' => ['driver' => ['rules' => [['id' => 'CVE-TEST', 'properties' => ['cvssV3_severity' => 'HIGH']]]]], 'results' => []]]];
$checks['high advisory rejected'] = nhSarifIssues($report, 'app', 'fixture', []) !== [];
$report['runs'][0]['tool']['driver']['rules'][0]['properties']['cvssV3_severity'] = 'CRITICAL';
$checks['critical advisory rejected'] = nhSarifIssues($report, 'app', 'fixture', []) !== [];
$report['runs'][0]['tool']['driver']['rules'][0] = ['id' => 'CVE-2008-0888', 'properties' => ['cvssV3_severity' => 'HIGH', 'purls' => ['pkg:apk/alpine/unzip@6.0-r16?os_name=alpine&os_version=3.24']]];
$decision = ['reviews' => [['advisory' => 'CVE-2008-0888', 'result' => 'not_affected_by_CNA_version_range']]];
$checks['decision cannot apply to another image'] = nhSarifIssues($report, 'app', 'different-image', $decision) !== [];
$checks['exact reviewed image decision accepted'] = nhSarifIssues($report, 'app', 'sha256:7cea7546d993f42742334b632e2d349285779918cc3c08923fbb68fa8475e839', $decision) === [];
$tls = ['checked_at' => gmdate(DATE_ATOM), 'bundle_sha256' => str_repeat('a', 64), 'checks' => []];
foreach (['repo.packagist.org' => null,
    'dv-g3b-emudhra.we-have-a-problem.com' => '42c4accd12c4f59597925e1a08a7a90102608543809308d50ec427e118ba1493',
    'dv-g3b-aaa-emudhra.we-have-a-problem.com' => '45a883f349aedfd5f3a649b5498ffb121712ba8b448f3f2a91dbc5a766fcddbe'] as $host => $fingerprint) {
    $tls['checks'][] = ['host' => $host, 'expected' => $fingerprint ? 'reject' : 'accept', 'result' => 'pass',
        'openssl' => $fingerprint ? 'trust_rejection_confirmed' : 'valid_chain_accepted',
        'curl' => $fingerprint ? 'trust_rejection_confirmed' : 'valid_chain_accepted',
        'php_streams' => $fingerprint ? 'certificate_rejection_confirmed' : 'valid_chain_accepted',
        'observed_intermediate_der_sha256' => $fingerprint];
}
$checks['complete scoped TLS outcomes accepted'] = nhTlsChainIssues($tls, str_repeat('a', 64)) === [];
$checks['TLS report bound to another bundle rejected'] = nhTlsChainIssues($tls, str_repeat('b', 64)) !== [];
$badTls = $tls; $badTls['checks'][1]['result'] = 'inconclusive_or_failed';
$checks['network or inconclusive TLS result rejected'] = nhTlsChainIssues($badTls, str_repeat('a', 64)) !== [];
$badTls = $tls; $badTls['checked_at'] = '2000-01-01T00:00:00Z';
$checks['stale TLS report rejected'] = nhTlsChainIssues($badTls, str_repeat('a', 64)) !== [];
$wrappedComposer = ['expected' => 'reject', 'exit' => 100, 'result' => 'inconclusive_or_failed',
    'composer' => 'inconclusive_or_failed', 'curl_error_60' => true, 'issuer_trust_error' => false,
    'diagnostic' => "curl error 60 while downloading https://dv-g3b-aaa-emudhra.we-have-a-proble m.com/packages.json: SSL certificate OpenSSL verify result: unable to get l ocal issuer certificate (20)"];
$checks['observed mid-word Composer wrapping recognized'] = nhComposerTlsOutcomeAccepted($wrappedComposer);
$badComposer = $wrappedComposer; $badComposer['diagnostic'] = 'curl error 6: could not resolve host';
$checks['Composer DNS failure never counts as TLS rejection'] = !nhComposerTlsOutcomeAccepted($badComposer);
$badComposer = $wrappedComposer; $badComposer['diagnostic'] = 'curl error 60: certificate hostname mismatch';
$checks['Composer hostname mismatch never counts as trust rejection'] = !nhComposerTlsOutcomeAccepted($badComposer);
$badComposer = $wrappedComposer; $badComposer['exit'] = 124;
$checks['Composer timeout never counts as trust rejection'] = !nhComposerTlsOutcomeAccepted($badComposer);
$badComposer = $wrappedComposer; $badComposer['diagnostic'] = 'unrelated error';
$checks['Composer exit 100 alone never counts as trust rejection'] = !nhComposerTlsOutcomeAccepted($badComposer);
$scanner = ['version' => "version: v1.24.0\ngit commit: b1c9331b2166aef7ec690aa16fd655b8798ea4c6",
    'binary_checksum' => "11c19e990ecc3ee9a3159208b407e143d6beb601dea42ff5745e9cf7d1a22bfe  /fixture/scout",
    'identity' => "Executable=/Applications/Docker.app/Contents/Resources/cli-plugins/docker-scout\nAuthority=Developer ID Application: Docker Inc (9BNSXJN65R)\nAuthority=Apple Root CA\nTeamIdentifier=9BNSXJN65R",
    'signature_verification' => 'valid on disk; satisfies its Designated Requirement',
    'context' => 'finished_utc='.gmdate(DATE_ATOM),
    'mailpit_sarif' => ['runs' => [['tool' => ['driver' => ['rules' => [['id' => 'CVE-2026-78661'], ['id' => 'CVE-2026-97045']]]]]]]];
$checks['reviewed signed Scout and recent source controls accepted'] = nhScannerIssues($scanner) === [];
$badScanner = $scanner; $badScanner['binary_checksum'] = str_repeat('0', 64).' /fixture/scout';
$checks['different Scout binary rejected'] = nhScannerIssues($badScanner) !== [];
$badScanner = $scanner; $badScanner['identity'] = 'TeamIdentifier=someone-else';
$checks['unexpected signing publisher rejected'] = nhScannerIssues($badScanner) !== [];
$badScanner = $scanner; $badScanner['context'] = 'finished_utc=2000-01-01T00:00:00Z';
$checks['old scan collection rejected'] = nhScannerIssues($badScanner) !== [];
$badScanner = $scanner; $badScanner['mailpit_sarif'] = [];
$checks['missing recent advisory controls rejected'] = nhScannerIssues($badScanner) !== [];
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ').$name.PHP_EOL;
}
exit(in_array(false, $checks, true) ? 1 : 0);
