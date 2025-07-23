<?php

require __DIR__ . '/vendor/autoload.php';
/**
 * @var Predis\Client $redis
 */
$redis = require_once __DIR__ . '/src/config/redis.php';

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = explode('?', $_SERVER['REQUEST_URI'])[0];

const PAYMENTS_ROUTE = '/payments';
const PAYMENTS_SUMMARY_ROUTE = '/payments-summary';
const DEFAULT_PAYMENT = 'default_payment';
const FALLBACK_PAYMENT = 'fallback_payment';

if ($endpoint === PAYMENTS_ROUTE) {
    $input = file_get_contents('php://input');
    $redis->rpush('payment-queue', $input);
    http_response_code(200);
    return;
}

if (str_contains($endpoint, PAYMENTS_SUMMARY_ROUTE)) {
    $from = $_GET['from'];
    $to = $_GET['to'];

    $fromTimestamp = $from ? (new DateTime($from))->getTimestamp() : null;
    $toTimestamp = $to ? (new DateTime($to))->getTimestamp() : null;

    $defaultPayments = $redis->lrange(DEFAULT_PAYMENT, 0, -1);
    $defaultPayments = array_map(fn($item) => json_decode($item, true), $defaultPayments);

    $fallbackPayments = $redis->lrange(FALLBACK_PAYMENT, 0, -1);
    $fallbackPayments = array_map(fn($item) => json_decode($item, true), $fallbackPayments);

    $maxLength = max(count($fallbackPayments), count($defaultPayments));
    $defaultPaymentsAmount = 0;
    $defaultPaymentsCount = 0;
    $defaultFallbackAmount = 0;
    $defaultFallbackCount = 0;
    for ($i = 0; $i < $maxLength; $i++) {
        if (isset($fallbackPayments[$i])) {
            $d = $fallbackPayments[$i]['d'];
            if ((is_null($fromTimestamp) || $d >= $fromTimestamp) &&
                (is_null($toTimestamp) || $d <= $toTimestamp)) {
                $defaultFallbackAmount += $fallbackPayments[$i]['a'];
                $defaultFallbackCount++;
            }
        }
        if (isset($defaultPayments[$i])) {
            $d = $defaultPayments[$i]['d'];
            if ((is_null($fromTimestamp) || $d >= $fromTimestamp) &&
                (is_null($toTimestamp) || $d <= $toTimestamp)) {
                $defaultPaymentsAmount += $defaultPayments[$i]['a'];
                $defaultPaymentsCount++;
            }
        }
    }

    http_response_code(200);
    echo json_encode([
        'default' => [
            'totalRequests' => $defaultPaymentsCount,
            'totalAmount' => $defaultPaymentsAmount
        ],
        'fallback' => [
            'totalRequests' => $defaultFallbackCount,
            'totalAmount' => $defaultFallbackAmount
        ],
    ]);
}
