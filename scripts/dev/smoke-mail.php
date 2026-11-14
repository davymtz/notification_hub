<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('queue.default') !== 'redis' || config('mail.mailers.smtp.host') !== 'mailpit') {
    fwrite(STDERR, "Expected Redis queue and local Mailpit SMTP. No notification sent.\n");
    exit(1);
}

function requestJson(string $url, ?array $body = null): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $result = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if ($result === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Local request failed, HTTP '.$status.' '.curl_error($handle));
    }
    return [$status, json_decode($result, true, 512, JSON_THROW_ON_ERROR)];
}

try {
    $marker = 'nh-smoke-'.bin2hex(random_bytes(12));
    $recipient = $marker.'@example.test';
    [$status, $response] = requestJson('http://nginx/v1/notifications/dispatch', [
        'event_type' => 'development.smoke',
        'channels' => ['email'],
        'idempotency_key' => $marker,
        'payload' => ['email' => $recipient, 'subject' => $marker, 'message' => $marker],
    ]);
    if ($status !== 202 || ($response['status'] ?? null) !== 'queued'
        || ($response['channels_queued'] ?? null) !== ['email']) {
        throw new RuntimeException('Expected HTTP 202 and email queued.');
    }
    echo "HTTP 202: email queued. Waiting for the exact message in Mailpit...\n";
    $timeout = (int) (getenv('SMOKE_TIMEOUT_SECONDS') ?: 120);
    if ($timeout < 1 || $timeout > 120) {
        throw new RuntimeException('SMOKE_TIMEOUT_SECONDS must be between 1 and 120.');
    }
    $deadline = microtime(true) + $timeout;
    do {
        [, $messages] = requestJson('http://mailpit:8025/api/v1/messages?limit=200');
        foreach ($messages['messages'] ?? [] as $message) {
            if (($message['Subject'] ?? null) !== $marker) {
                continue;
            }
            $addresses = array_column($message['To'] ?? [], 'Address');
            if (!in_array($recipient, $addresses, true)) {
                continue;
            }
            [, $detail] = requestJson('http://mailpit:8025/api/v1/message/'.rawurlencode($message['ID']));
            if (trim($detail['Text'] ?? '') !== $marker) {
                throw new RuntimeException('Matching subject/recipient but unexpected body.');
            }
            echo "PASS: HTTP -> Redis -> worker -> Mailpit, recipient and body verified.\n";
            exit(0);
        }
        sleep(1);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Mailpit did not receive this message within '.$timeout.' seconds. Check worker logs.', 2);
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: ".$error->getMessage().PHP_EOL);
    exit($error->getCode() === 2 ? 2 : 1);
}
