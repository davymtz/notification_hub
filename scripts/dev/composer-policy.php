<?php
$values = [];
foreach (['secure-http', 'disable-tls', 'allow-plugins'] as $setting) {
    $process = proc_open(['composer', '--no-plugins', '--no-scripts', 'config', '--json', $setting],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot query Composer policy.'); }
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    if (proc_close($process) !== 0) { throw new RuntimeException('Composer policy query failed.'); }
    $values[str_replace('-', '_', $setting)] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}
echo json_encode($values, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
