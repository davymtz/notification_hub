<?php

// Public-key identifiers from AWS bulletin 2026-150; read-only trust inventory.
$affectedKeys = [
    'bd153ed7b0434f6886b17bce8bbe84ed340c7132d702a8f4fa318f756ecbd6f3' => 'AAA Certificate Services (cross-signing path)',
    'c619e4780ed8e0abeda24ad732567de4383a7ea941338f6d1a7d1b49ac792fe9' => 'emSign Root TLS CA - G3',
    '8d417db2dd8bf5e3084d1e3f196d583849d81bdd4c00c70b9d39369e96b8c782' => 'emSign ECC Root CA - G3',
];
$locations = openssl_get_cert_locations();
$bundlePaths = [ini_get('openssl.cafile'), ini_get('curl.cainfo'), $locations['default_cert_file'] ?? '', '/etc/ssl/certs/ca-certificates.crt'];
$directories = [ini_get('openssl.capath'), $locations['default_cert_dir'] ?? ''];
foreach (['SSL_CERT_FILE', 'CURL_CA_BUNDLE', 'COMPOSER_CAFILE'] as $name) {
    if (getenv($name)) { $bundlePaths[] = getenv($name); }
}
if (getenv('SSL_CERT_DIR')) { $directories[] = getenv('SSL_CERT_DIR'); }
foreach (array_filter($directories) as $directoryList) {
    foreach (explode(PATH_SEPARATOR, $directoryList) as $directory) {
        if (is_dir($directory)) {
            foreach (glob(rtrim($directory, '/').'/*') ?: [] as $file) {
                if (is_file($file)) { $bundlePaths[] = $file; }
            }
        }
    }
}
$seenFiles = [];
$certificates = [];
$matches = [];
foreach (array_unique(array_filter($bundlePaths)) as $file) {
    if (!is_file($file) || !is_readable($file)) { continue; }
    $real = realpath($file);
    if (isset($seenFiles[$real])) { continue; }
    $seenFiles[$real] = true;
    preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', file_get_contents($file), $pems);
    foreach ($pems[0] as $pem) {
        $cert = openssl_x509_read($pem);
        if ($cert === false) { throw new RuntimeException('Unreadable PEM certificate.'); }
        $key = openssl_pkey_get_public($cert);
        if ($key === false) { throw new RuntimeException('Cannot extract certificate key.'); }
        $public = openssl_pkey_get_details($key)['key'];
        $encoded = preg_replace('/-----[^-]+-----|\s+/', '', $public);
        $der = base64_decode($encoded, true);
        if ($der === false) { throw new RuntimeException('Invalid SPKI encoding.'); }
        $spki = hash('sha256', $der);
        $fingerprint = openssl_x509_fingerprint($cert, 'sha256');
        $certificates[$fingerprint] = true;
        if (isset($affectedKeys[$spki])) {
            $matches[] = ['file' => $file, 'sha256_der' => $fingerprint,
                'sha256_spki' => $spki, 'hierarchy' => $affectedKeys[$spki]];
        }
    }
}
if (!$certificates) { throw new RuntimeException('No trust certificates inspected; inconclusive.'); }
echo json_encode([
    'source' => 'https://aws.amazon.com/security/security-bulletins/2026-150-aws/',
    'files_inspected' => count($seenFiles), 'unique_certificates' => count($certificates),
    'affected_trust_keys_found' => $matches,
    'result' => $matches ? 'REVIEW_REQUIRED: affected hierarchy keys remain in trust inputs' : 'No listed hierarchy keys found in inspected inputs',
    'limits' => 'Read-only inventory, not chain-rejection proof; Composer-specific cafile must be checked separately. No host/daemon changes.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
