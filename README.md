# teracrafts/huefy-sdk

Official PHP SDK for [Huefy](https://huefy.dev) — transactional email delivery made simple.

## Installation

```bash
composer require teracrafts/huefy-sdk
```

To use a custom PSR-18 HTTP client instead of the bundled Guzzle adapter:

```bash
composer require teracrafts/huefy-sdk psr/http-client your-psr18-client
```

## Requirements

- PHP 8.1+
- `guzzlehttp/guzzle` (installed automatically, or bring your own PSR-18 client)

## Quick Start

```php
use Teracrafts\Huefy\HuefyEmailClient;
use Teracrafts\Huefy\Models\SendEmailRequest;
use Teracrafts\Huefy\Models\SendEmailRecipient;

$client = new HuefyEmailClient([
    'apiKey' => 'sdk_your_api_key',
]);

$response = $client->sendEmail(new SendEmailRequest(
    templateKey: 'welcome-email',
    data: ['firstName' => 'Alice', 'trialDays' => 14],
    recipient: new SendEmailRecipient(
        email: 'alice@example.com',
        type: 'cc',
        data: ['locale' => 'en'],
    ),
));

echo 'Email ID: ' . $response->data->emailId . PHP_EOL;
```

## Key Features

- **PSR-18 compatible** — swap in any PSR-18 HTTP client
- **PSR-3 logger** — plug in any PSR-3 logger (Monolog, etc.)
- **PHP 8.1 enums** — `EmailProvider` is a native enum
- **Named arguments** — all constructors support PHP 8 named argument syntax
- **Retry with exponential backoff** — configurable attempts, base delay, ceiling, and jitter
- **Circuit breaker** — opens after 5 consecutive failures, probes after 30 s
- **HMAC-SHA256 signing** — optional request signing for additional integrity verification
- **Key rotation** — primary + secondary API key with seamless failover
- **Rate limit callbacks** — `onRateLimitUpdate` fires whenever rate-limit headers change
- **PII detection** — warns when template variables contain sensitive field patterns

## Configuration Reference

| Parameter | Default | Description |
|-----------|---------|-------------|
| `apiKey` | — | **Required.** Must have prefix `sdk_`, `srv_`, or `cli_` |
| `baseUrl` | `https://api.huefy.dev/api/v1/sdk` | Override the API base URL |
| `timeout` | `30.0` | Request timeout in seconds |
| `retryConfig->maxAttempts` | `3` | Total attempts including the first |
| `retryConfig->baseDelay` | `0.5` | Exponential backoff base delay (seconds) |
| `retryConfig->maxDelay` | `10.0` | Maximum backoff delay (seconds) |
| `retryConfig->jitter` | `0.2` | Random jitter factor (0–1) |
| `circuitBreakerConfig->failureThreshold` | `5` | Consecutive failures before circuit opens |
| `circuitBreakerConfig->resetTimeout` | `30.0` | Seconds before half-open probe |
| `logger` | `null` | PSR-3 logger instance |
| `secondaryApiKey` | `null` | Backup key used during key rotation |
| `enableRequestSigning` | `false` | Enable HMAC-SHA256 request signing |
| `onRateLimitUpdate` | `null` | Callable fired on rate-limit header changes |

## Bulk Email

```php
use Teracrafts\Huefy\Models\BulkRecipient;
use Teracrafts\Huefy\Models\SendBulkEmailsRequest;

$bulk = $client->sendBulkEmails(new SendBulkEmailsRequest(
    templateKey: 'promo',
    recipients: [
        new BulkRecipient(email: 'bob@example.com'),
        new BulkRecipient(email: 'carol@example.com'),
    ]
));

echo "Sent: {$bulk->data->successCount}, Failed: {$bulk->data->failureCount}" . PHP_EOL;
```

## Error Handling

```php
use Teracrafts\Huefy\Errors\ErrorCode;
use Teracrafts\Huefy\Errors\HuefyException;

try {
    $response = $client->sendEmail($request);
    echo 'Delivered: ' . $response->data->emailId . PHP_EOL;
} catch (HuefyException $e) {
    if ($e->getErrorCode() === ErrorCode::AUTHENTICATION_ERROR) {
        echo 'Invalid API key' . PHP_EOL;
    } elseif ($e->getErrorCode() === ErrorCode::INSUFFICIENT_QUOTA) {
        echo 'Quota exhausted. Upgrade or wait for the next billing period' . PHP_EOL;
    } elseif ($e->getErrorCode() === ErrorCode::RATE_LIMIT_ERROR) {
        echo 'Rate limited' . PHP_EOL;
    } elseif ($e->getErrorCode() === ErrorCode::CIRCUIT_BREAKER_OPEN) {
        echo 'Circuit open — service unavailable, backing off' . PHP_EOL;
    } else {
        echo "Huefy error [{$e->getErrorCode()}]: {$e->getMessage()}" . PHP_EOL;
    }
}
```

### Error Code Reference

| Exception | Code | Meaning |
|-----------|------|---------|
| `HuefyInitException` | 1001 | Client failed to initialise |
| `HuefyAuthException` | 1102 | API key rejected |
| `HuefyException` | `INSUFFICIENT_QUOTA` | Account or organization quota exhausted |
| `HuefyNetworkException` | 1201 | Upstream request failed |
| `HuefyCircuitOpenException` | 1301 | Circuit breaker tripped |
| `HuefyRateLimitException` | 2003 | Rate limit exceeded |
| `HuefyTemplateMissingException` | 2005 | Template key not found |

## Health Check

```php
$health = $client->healthCheck();
if (($health['data']['status'] ?? null) !== 'healthy') {
    error_log('Huefy degraded: ' . ($health['data']['status'] ?? 'unknown'));
}
```

## Local Development

The PHP SDK does not resolve `HUEFY_MODE` automatically. To match the local Caddy setup, set `baseUrl` to `https://api.huefy.on/api/v1/sdk`. To bypass Caddy and hit the raw app port directly, use `http://localhost:3140/api/v1/sdk` instead:

```php
$client = new HuefyEmailClient([
    'apiKey' => 'sdk_local_key',
    'baseUrl' => 'https://api.huefy.on/api/v1/sdk',
]);
```

## Namespace Compatibility

The canonical PHP namespace is `Teracrafts\Huefy\...`. Existing `Huefy\...` references remain supported through the bundled compatibility aliases.

## Developer Guide

Full documentation, advanced patterns, and provider configuration are in the [PHP Developer Guide](../../docs/spec/guides/php.guide.md).

## License

MIT
