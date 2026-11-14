<?php
// Verify served UI and its local JS/CSS assets, never execute downloaded content.
function nhMailpitAsset(string $path): string
{
    $parts = parse_url($path);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
        || str_starts_with($path, '//') || str_contains($path, '..')) {
        throw new RuntimeException('Unexpected non-local Mailpit asset URL.');
    }
    $curl = curl_init('http://mailpit:8025/'.ltrim($path, './'));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTP, CURLOPT_FOLLOWLOCATION => false]);
    $content = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($content === false || $status !== 200 || strlen($content) < 1) {
        throw new RuntimeException('Mailpit UI/asset missing or empty (HTTP '.$status.').');
    }
    return $content;
}
$html = nhMailpitAsset('/');
if (stripos($html, '<html') === false || stripos($html, 'mailpit') === false) {
    throw new RuntimeException('Mailpit UI HTML is missing.');
}
$document = new DOMDocument();
$previous = libxml_use_internal_errors(true);
try { $document->loadHTML($html, LIBXML_NONET); }
finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
$xpath = new DOMXPath($document);
$assets = [];
foreach ($xpath->query('//script[@src] | //link[@rel="stylesheet"][@href]') as $node) {
    $path = $node->getAttribute($node->nodeName === 'script' ? 'src' : 'href');
    if ($path !== '') { $assets[$path] = true; }
}
$javascript = false;
foreach (array_keys($assets) as $asset) {
    nhMailpitAsset($asset);
    if (preg_match('/\.js(?:\?|$)/', $asset)) { $javascript = true; }
}
if (!$javascript) { throw new RuntimeException('No Mailpit JavaScript bundle verified.'); }
echo 'PASS: Mailpit UI and '.count($assets).' local JS/CSS assets served (no browser execution).'.PHP_EOL;
