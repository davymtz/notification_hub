<?php

// Read-only inventory. Never loads .env or prints phpinfo/environment values.
$extensions = [];
foreach (get_loaded_extensions() as $extension) {
    $extensions[$extension] = phpversion($extension) ?: 'built-in';
}
ksort($extensions);
$locations = openssl_get_cert_locations();
$ini = [];
foreach (['session.upload_progress.enabled', 'openssl.cafile', 'openssl.capath', 'curl.cainfo'] as $name) {
    $ini[$name] = ini_get($name);
}
$bundles = array_unique(array_filter([
    ini_get('openssl.cafile'), ini_get('curl.cainfo'),
    $locations['default_cert_file'] ?? null,
    '/etc/ssl/certs/ca-certificates.crt',
]));
$bundleInventory = [];
foreach ($bundles as $bundle) {
    if (!is_file($bundle) || !is_readable($bundle)) {
        $bundleInventory[] = ['path' => $bundle, 'readable' => false];
        continue;
    }
    $content = file_get_contents($bundle);
    preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $content, $matches);
    $emsign = [];
    foreach ($matches[0] as $pem) {
        $cert = openssl_x509_parse($pem);
        if ($cert === false) {
            throw new RuntimeException('Invalid certificate in bundle.');
        }
        if (preg_match('/emsign|emudhra/i', json_encode($cert['subject'] ?? []))) {
            $emsign[] = ['subject' => $cert['subject'], 'sha256' => openssl_x509_fingerprint($pem, 'sha256')];
        }
    }
    $bundleInventory[] = ['path' => $bundle, 'sha256' => hash('sha256', $content),
        'certificate_count' => count($matches[0]), 'emsign_candidates' => $emsign];
}
$curl = curl_version();
echo json_encode([
    'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'architecture' => php_uname('m'),
    'openssl' => OPENSSL_VERSION_TEXT,
    'curl' => array_intersect_key($curl, array_flip(['version', 'ssl_version', 'libz_version', 'brotli_version'])),
    'ini' => $ini, 'extensions' => $extensions, 'ca_bundles' => $bundleInventory,
    'php_minimum_pass' => version_compare(PHP_VERSION, '8.4.27', '>='),
    'upload_progress_disabled' => !filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN),
    'notice' => 'Inventory only. Certificate name matches do not establish compromise or remediation; verify exact official fingerprints and chain behavior.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
