<?php

declare(strict_types=1);

namespace Huefy;

use Huefy\Errors\HuefyException;
use Huefy\Utils\Logger;
use Huefy\Utils\NullLogger;

/**
 * Configuration value object for the Huefy SDK.
 */
class HuefyConfig
{
    public readonly string $apiKey;
    public readonly ?string $baseUrl;
    public readonly int $timeout;
    public readonly int $maxRetries;
    public readonly int $baseDelayMs;
    public readonly int $maxDelayMs;
    /** @var int[] */
    public readonly array $retryableStatusCodes;
    public readonly int $circuitBreakerThreshold;
    public readonly int $circuitBreakerResetMs;
    public readonly int $circuitBreakerHalfOpenMax;
    public readonly ?string $secondaryApiKey;
    public readonly bool $enableRequestSigning;
    public readonly bool $enableErrorSanitization;
    public readonly Logger $logger;

    /**
     * @param array<string, mixed> $config Configuration array with the following keys:
     *   - apiKey (string, required): Primary API key.
     *   - baseUrl (?string): Base URL override. Defaults to https://api.huefy.dev/api/v1/sdk.
     *   - timeout (int): Request timeout in ms. Defaults to 30000.
     *   - maxRetries (int): Maximum retry attempts. Defaults to 3.
     *   - baseDelayMs (int): Base delay for exponential backoff. Defaults to 1000.
     *   - maxDelayMs (int): Maximum delay between retries. Defaults to 30000.
     *   - retryableStatusCodes (int[]): Status codes that trigger retries.
     *   - circuitBreakerThreshold (int): Failures before circuit opens. Defaults to 5.
     *   - circuitBreakerResetMs (int): Time before half-open attempt. Defaults to 60000.
     *   - circuitBreakerHalfOpenMax (int): Max half-open probes. Defaults to 1.
     *   - secondaryApiKey (?string): Optional failover API key.
     *   - enableRequestSigning (bool): Enable HMAC signing. Defaults to false.
     *   - enableErrorSanitization (bool): Enable PII sanitization. Defaults to true.
     *   - logger (?Logger): Logger instance. Defaults to NullLogger.
     *
     * @throws HuefyException If required configuration is missing or invalid.
     */
    public function __construct(array $config)
    {
        if (empty($config['apiKey']) || !is_string($config['apiKey'])) {
            throw HuefyException::validationError('API key is required and must be a non-empty string', 'apiKey');
        }

        $this->apiKey = $config['apiKey'];
        $this->baseUrl = $config['baseUrl'] ?? null;
        $this->timeout = (int) ($config['timeout'] ?? 30_000);
        $this->maxRetries = (int) ($config['maxRetries'] ?? 3);
        $this->baseDelayMs = (int) ($config['baseDelayMs'] ?? 1_000);
        $this->maxDelayMs = (int) ($config['maxDelayMs'] ?? 30_000);
        $this->retryableStatusCodes = $config['retryableStatusCodes'] ?? [408, 429, 500, 502, 503, 504];
        $this->circuitBreakerThreshold = (int) ($config['circuitBreakerThreshold'] ?? 5);
        $this->circuitBreakerResetMs = (int) ($config['circuitBreakerResetMs'] ?? 60_000);
        $this->circuitBreakerHalfOpenMax = (int) ($config['circuitBreakerHalfOpenMax'] ?? 1);
        $this->secondaryApiKey = $config['secondaryApiKey'] ?? null;
        $this->enableRequestSigning = (bool) ($config['enableRequestSigning'] ?? false);
        $this->enableErrorSanitization = (bool) ($config['enableErrorSanitization'] ?? true);
        $this->logger = $config['logger'] ?? new NullLogger();

        if ($this->timeout <= 0) {
            throw HuefyException::validationError('Timeout must be positive', 'timeout');
        }
        if ($this->maxRetries < 0) {
            throw HuefyException::validationError('maxRetries must be non-negative', 'maxRetries');
        }
        if ($this->baseDelayMs <= 0) {
            throw HuefyException::validationError('baseDelayMs must be positive', 'baseDelayMs');
        }
        if ($this->maxDelayMs < $this->baseDelayMs) {
            throw HuefyException::validationError('maxDelayMs must be >= baseDelayMs', 'maxDelayMs');
        }
        if ($this->circuitBreakerResetMs <= 0) {
            throw HuefyException::validationError('circuitBreakerResetMs must be positive', 'circuitBreakerResetMs');
        }
        if ($this->circuitBreakerThreshold < 1) {
            throw HuefyException::validationError('circuitBreakerThreshold must be >= 1', 'circuitBreakerThreshold');
        }
        if ($this->circuitBreakerHalfOpenMax < 1) {
            throw HuefyException::validationError('circuitBreakerHalfOpenMax must be >= 1', 'circuitBreakerHalfOpenMax');
        }
    }

    /**
     * Resolves the effective base URL.
     */
    public function resolvedBaseUrl(): string
    {
        return $this->baseUrl ?? 'https://api.huefy.dev/api/v1/sdk';
    }
}
