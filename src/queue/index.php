<?php

require __DIR__ . '/../../vendor/autoload.php';
/**
 * @var Predis\Client $redis
 */
$redis = require_once __DIR__ . '/../config/redis.php';

define("PROCESSOR_DEFAULT_URL", getenv('PROCESSOR_DEFAULT_URL'));
define("PROCESSOR_FALLBACK_URL", getenv('PROCESSOR_FALLBACK_URL'));
const DEFAULT_PAYMENT = 'default_payment';
const FALLBACK_PAYMENT = 'fallback_payment';

$dateCheckDefaultHealth = null;
$defaultHealth = null;
$dateCheckFallbackHealth = null;
$fallbackHealth = null;

/**
 * @throws DateMalformedStringException
 */
function doPayment(string $url, string $correlationId, float $amount): array
{
    $data = [
        'correlationId' => $correlationId,
        'amount' => $amount,
        'requestedAt' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z')
    ];

    $ch = curl_init($url . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$http_code, $data];
}

/**
 * @param string $url
 * @param ?DateTime $dateCheck
 * @param array|null $check
 * @return array{failing: bool, minResponseTime: int}
 */
function checkServiceHealth(string $url, ?DateTime &$dateCheck, ?array $check): array
{
    $now = new DateTime('now');
    $timestamp = $now->getTimestamp();

    if (empty($dateCheck) || ($timestamp - $dateCheck->getTimestamp()) >= 5) {
        $ch = curl_init($url. '/payments/service-health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $dateCheck = $now;
        return json_decode($response, true);
    }

    return [
        'failing' => $check['failing'],
        'minResponseTime' => 0
    ];
}

function updatePayments(string $key, array $payment, Predis\Client $redis): void
{
    $timestamp = (new DateTime($payment['requestedAt']))->getTimestamp();
    $redis->rpush($key, json_encode(['d' => $timestamp, 'a' => $payment['amount']]));
}


while (true) {
    $payment = $redis->lpop('payment-queue');

    if (! empty($payment)) {
        $defaultHealth = checkServiceHealth(PROCESSOR_DEFAULT_URL, $dateCheckDefaultHealth, $defaultHealth);
        $fallbackHealth = checkServiceHealth(PROCESSOR_FALLBACK_URL, $dateCheckFallbackHealth, $fallbackHealth);

        if ($defaultHealth['failing'] && $fallbackHealth['failing']) {
            continue;
        }

        $payment = json_decode($payment, true);

        if (! $defaultHealth['failing']) {
            [$http_code, $data] = doPayment(PROCESSOR_DEFAULT_URL, $payment['correlationId'], $payment['amount']);

            if ((int)$http_code >= 200 && (int)$http_code < 300) {
                updatePayments(DEFAULT_PAYMENT, $data, $redis);
                continue;
            }

            if (! $fallbackHealth['failing']) {
                [$http_code, $data] = doPayment(PROCESSOR_FALLBACK_URL, $payment['correlationId'], $payment['amount']);
                if ((int)$http_code >= 200 && (int)$http_code < 300) {
                    updatePayments(FALLBACK_PAYMENT, $data, $redis);
                }
            }
            continue;
        }

        if (! $fallbackHealth['failing']) {
            [$http_code, $data] = doPayment(PROCESSOR_FALLBACK_URL, $payment['correlationId'], $payment['amount']);
            if ((int)$http_code >= 200 && (int)$http_code < 300) {
                updatePayments(FALLBACK_PAYMENT, $data, $redis);
            }
        }
    }
}

