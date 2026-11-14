<?php

// Read-only review of two exact findings. Remote responses are DATA, never code.
// Mailpit scope is checked against the scanned image by the shell wrapper.
$evidence = [];
function fetchReviewData(string $url): string
{
    global $evidence;
    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'NotificationHub-security-review',
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 8 * 1024 * 1024) { return 0; }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($ok === false || $status !== 200) {
        throw new RuntimeException('Source query inconclusive: HTTP '.$status.' at '.$url);
    }
    $evidence[] = ['url' => $url, 'sha256' => hash('sha256', $body)];
    return $body;
}

$reviews = [];
try {
    $record = json_decode(fetchReviewData('https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/2008/0xxx/CVE-2008-0888.json'), true, 512, JSON_THROW_ON_ERROR);
    $scope = null;
    foreach ($record['containers']['cna']['affected'] ?? [] as $entry) {
        if (($entry['vendor'] ?? '') === 'info-zip' && ($entry['packageName'] ?? '') === 'unzip') { $scope = $entry; }
    }
    if (($scope['defaultStatus'] ?? '') !== 'unaffected' || count($scope['versions'] ?? []) !== 1
        || ($scope['versions'][0]['lessThan'] ?? '') !== '6.0'
        || ($scope['versions'][0]['status'] ?? '') !== 'affected') {
        throw new RuntimeException('Unzip CNA range changed or is unexpected; manual review required.');
    }
    $process = proc_open(['apk', 'list', '--installed', 'unzip'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot inventory installed unzip.'); }
    $installed = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || !preg_match('/^unzip-(6\.0-r16)\s.*\[installed\]/m', $installed, $match)) {
        throw new RuntimeException('Installed unzip differs from reviewed 6.0-r16; manual review required.');
    }
    $reviews[] = ['advisory' => 'CVE-2008-0888', 'package' => 'unzip', 'installed' => $match[1],
        'result' => 'not_affected_by_CNA_version_range', 'official_affected_range' => '<6.0',
        'limits' => 'Only this advisory and exact installed revision; no blanket unzip exclusion.'];
} catch (Throwable $exception) {
    $reviews[] = ['advisory' => 'CVE-2008-0888', 'result' => 'inconclusive', 'reason' => $exception->getMessage()];
}

try {
    $image = getenv('NH_REVIEW_MAILPIT_IMAGE');
    if ($image !== 'sha256:acb86f3d4a89677b0667c304035147ea39b2adc232606e66257de929531bd9cf') {
        throw new RuntimeException('Mailpit image does not match reviewed SPDX image.');
    }
    $fixed = '14b16010c2ee7ff33a940a541d993bd043a88940';
    $installed = '6c848fa1b723';
    $comparison = json_decode(fetchReviewData('https://api.github.com/repos/gomarkdown/markdown/compare/'.$fixed.'...'.$installed.'?per_page=1'), true, 512, JSON_THROW_ON_ERROR);
    $head = $comparison['commits'][0]['sha'] ?? '';
    // Resolve the installed short reference independently; the first compare page
    // can contain an early descendant rather than the requested head.
    $commit = json_decode(fetchReviewData('https://api.github.com/repos/gomarkdown/markdown/commits/'.$installed), true, 512, JSON_THROW_ON_ERROR);
    $head = $commit['sha'] ?? '';
    if (!preg_match('/^[a-f0-9]{40}$/', $head) || !str_starts_with($head, $installed)
        || ($comparison['merge_base_commit']['sha'] ?? '') !== $fixed
        || !in_array($comparison['status'] ?? '', ['ahead', 'identical'], true)) {
        throw new RuntimeException('Cannot establish installed commit descends from the official fix.');
    }
    $source = fetchReviewData('https://raw.githubusercontent.com/gomarkdown/markdown/'.$head.'/parser/citation.go');
    // Ancestry alone does not exclude a revert: also check the relevant guard.
    if (!preg_match('/if\s+len\(citation\)\s*<\s*2\s*\{\s*continue\s*\}\s*j\s*=\s*1\s*switch\s+citation\[j\]/s', $source)) {
        throw new RuntimeException('Expected citation boundary guard not found; manual code review required.');
    }
    $reviews[] = ['advisory' => 'CVE-2023-42821', 'package' => 'github.com/gomarkdown/markdown',
        'installed_version' => '0.0.0-20261022144421-6c848fa1b723', 'image' => $image,
        'official_fix_commit' => $fixed, 'resolved_installed_commit' => $head,
        'result' => 'fix_ancestry_and_source_guard_confirmed',
        'limits' => 'Source-level evidence tied to the scanned image SPDX; not independent binary reproducibility proof or a review of other Go advisories.'];
} catch (Throwable $exception) {
    $reviews[] = ['advisory' => 'CVE-2023-42821', 'result' => 'inconclusive', 'reason' => $exception->getMessage()];
}

echo json_encode(['checked_at' => gmdate(DATE_ATOM), 'source_report' => 'artifacts-security.2hxBg1xH',
    'reviews' => $reviews, 'source_evidence' => $evidence,
    'limits' => 'No scanner suppression and no global security acceptance. Query failure remains inconclusive.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
foreach ($reviews as $review) {
    if ($review['result'] === 'inconclusive') { exit(1); }
}
