# teracrafts/huefy

Official PHP SDK for [Huefy](https://huefy.dev) — transactional email delivery made simple.

## Installation

```bash
composer require teracrafts/huefy
```

To use a custom PSR-18 HTTP client instead of the bundled Guzzle adapter:

```bash
composer require teracrafts/huefy psr/http-client your-psr18-client
```

## Requirements

- PHP 8.1+
- `guzzlehttp/guzzle` (installed automatically, or bring your own PSR-18 client)

## Quick Start

```php
use Teracrafts\Huefy\HuefyEmailClient;
use Teracrafts\Huefy\HuefyConfig;
use Teracrafts\Huefy\Model\Recipient;
use Teracrafts\Huefy\Model\SendEmailRequest;

$client = new HuefyEmailClient(new HuefyConfig(
    apiKey: 'sdk_your_api_key',
));

$response = $client->sendEmail(new SendEmailRequest(
    templateKey: 'welcome-email',
    recipient: new Recipient(email: 'alice@example.com', name: 'Alice'),
    variables: ['firstName' => 'Alice', 'trialDays' => 14],
));

echo 'Message ID: ' . $response->messageId . PHP_EOL;
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
use Teracrafts\Huefy\Model\BulkEmailRequest;

$bulk = $client->sendBulkEmails(new BulkEmailRequest(
    emails: [
        new SendEmailRequest(templateKey: 'promo', recipient: new Recipient('bob@example.com')),
        new SendEmailRequest(templateKey: 'promo', recipient: new Recipient('carol@example.com')),
    ]
));

echo "Sent: {$bulk->totalSent}, Failed: {$bulk->totalFailed}" . PHP_EOL;
```

## Error Handling

```php
use Teracrafts\Huefy\Exception\HuefyAuthException;
use Teracrafts\Huefy\Exception\HuefyRateLimitException;
use Teracrafts\Huefy\Exception\HuefyCircuitOpenException;
use Teracrafts\Huefy\Exception\HuefyException;

try {
    $response = $client->sendEmail($request);
    echo 'Delivered: ' . $response->messageId . PHP_EOL;
} catch (HuefyAuthException $e) {
    echo 'Invalid API key' . PHP_EOL;
} catch (HuefyRateLimitException $e) {
    echo "Rate limited. Retry after {$e->retryAfter}s" . PHP_EOL;
} catch (HuefyCircuitOpenException $e) {
    echo 'Circuit open — service unavailable, backing off' . PHP_EOL;
} catch (HuefyException $e) {
    echo "Huefy error [{$e->getCode()}]: {$e->getMessage()}" . PHP_EOL;
}
```

### Error Code Reference

| Exception | Code | Meaning |
|-----------|------|---------|
| `HuefyInitException` | 1001 | Client failed to initialise |
| `HuefyAuthException` | 1102 | API key rejected |
| `HuefyNetworkException` | 1201 | Upstream request failed |
| `HuefyCircuitOpenException` | 1301 | Circuit breaker tripped |
| `HuefyRateLimitException` | 2003 | Rate limit exceeded |
| `HuefyTemplateMissingException` | 2005 | Template key not found |

## Health Check

```php
$health = $client->healthCheck();
if ($health->status !== 'healthy') {
    error_log('Huefy degraded: ' . $health->status);
}
```

## Local Development

Set `HUEFY_MODE=local` to point the SDK at a local Huefy server, or override `baseUrl` in the config:

```php
$client = new HuefyEmailClient(new HuefyConfig(
    apiKey: 'sdk_local_key',
    baseUrl: 'http://localhost:3000/api/v1/sdk',
));
```

## Developer Guide

Full documentation, advanced patterns, and provider configuration are in the [PHP Developer Guide](../../docs/spec/guides/php.guide.md).

## License

MIT
