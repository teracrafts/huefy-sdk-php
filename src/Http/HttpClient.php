<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Teracrafts\Huefy\Errors\ErrorSanitizer;
use Teracrafts\Huefy\Errors\HuefyException;
use Teracrafts\Huefy\HuefyConfig;
use Teracrafts\Huefy\RateLimitInfo;
use Teracrafts\Huefy\Security\Security;
use Teracrafts\Huefy\Utils\Version;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client for making API requests with retry and circuit breaker support.
 *
 * Uses Guzzle for HTTP transport.
 */
class HttpClient
{
    private Client $client;
    private HuefyConfig $config;
    private RetryHandler $retryHandler;
    private CircuitBreaker $circuitBreaker;
    private bool $rotatedToSecondary = false;

    public function __construct(HuefyConfig $config)
    {
        $this->config = $config;
        $baseUri = rtrim($config->resolvedBaseUrl(), '/') . '/';
        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout' => $config->timeout / 1000.0,
            'http_errors' => false,
        ]);
        $this->retryHandler = new RetryHandler(
            maxRetries: $config->maxRetries,
            baseDelayMs: $config->baseDelayMs,
            maxDelayMs: $config->maxDelayMs,
            retryableStatusCodes: $config->retryableStatusCodes,
        );
        $this->circuitBreaker = new CircuitBreaker(
            failureThreshold: $config->circuitBreakerThreshold,
            resetTimeoutMs: $config->circuitBreakerResetMs,
            halfOpenMaxRequests: $config->circuitBreakerHalfOpenMax,
        );
    }

    /**
     * Performs a GET request.
     *
     * @return array<string, mixed>
     */
    public function get(string $path): array
    {
        return $this->executeWithResilience(function () use ($path): array {
            return $this->doRequest('GET', $path);
        });
    }

    /**
     * Performs a POST request.
     *
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    public function post(string $path, ?array $body = null): array
    {
        return $this->executeWithResilience(function () use ($path, $body): array {
            return $this->doRequest('POST', $path, $body);
        });
    }

    /**
     * Performs a PUT request.
     *
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    public function put(string $path, ?array $body = null): array
    {
        return $this->executeWithResilience(function () use ($path, $body): array {
            return $this->doRequest('PUT', $path, $body);
        });
    }

    /**
     * Performs a DELETE request.
     *
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->executeWithResilience(function () use ($path): array {
            return $this->doRequest('DELETE', $path);
        });
    }

    /**
     * Closes the HTTP client (releases resources).
     */
    public function close(): void
    {
        // Guzzle does not require explicit close, but this satisfies the interface.
    }

    /**
     * @return array<string, mixed>
     */
    private function doRequest(string $method, string $path, ?array $body = null): array
    {
        $options = [
            'headers' => [
                'X-API-Key' => $this->config->apiKey,
                'User-Agent' => 'huefy-php/' . Version::SDK_VERSION,
                'Accept' => 'application/json',
            ],
        ];

        $serializedBody = null;
        if ($body !== null) {
            $encoded = json_encode($body);
            if ($encoded === false) {
                throw HuefyException::serverError(
                    'Failed to serialize request body: ' . json_last_error_msg(),
                    0,
                );
            }
            $serializedBody = $encoded;
        }

        if ($serializedBody !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = $serializedBody;
        }

        if ($this->config->enableRequestSigning) {
            $timestamp = (int) (microtime(true) * 1000);
            $bodyForSigning = $serializedBody ?? '';
            $message = "{$timestamp}.{$bodyForSigning}";
            $signature = Security::hmacSign($message, $this->config->apiKey);

            $options['headers']['X-Timestamp'] = (string) $timestamp;
            $options['headers']['X-Signature'] = $signature;
            $options['headers']['X-Key-Id'] = substr($this->config->apiKey, 0, 8);
        }

        try {
            $response = $this->client->request($method, ltrim($path, '/'), $options);
        } catch (ConnectException $e) {
            throw HuefyException::networkError('Connection failed: ' . $e->getMessage(), $e);
        } catch (RequestException $e) {
            throw HuefyException::networkError('Request failed: ' . $e->getMessage(), $e);
        }

        $statusCode = $response->getStatusCode();
        $bodyString = (string) $response->getBody();

        if ($statusCode >= 200 && $statusCode <= 299) {
            $decoded = json_decode($bodyString, true);
            if (!is_array($decoded)) {
                throw HuefyException::serverError(
                    'Failed to parse response body',
                    $statusCode,
                );
            }

            $this->parseRateLimitHeaders($response);

            return $decoded;
        }

        // Handle 401 with key rotation: swap to secondary key and retry once.
        if ($statusCode === 401 && !$this->rotatedToSecondary && $this->config->secondaryApiKey !== null) {
            $this->rotatedToSecondary = true;
            $this->config->apiKey = $this->config->secondaryApiKey;
            $options['headers']['X-API-Key'] = $this->config->apiKey;
            try {
                $retryResponse = $this->client->request($method, ltrim($path, '/'), $options);
            } catch (ConnectException $e) {
                throw HuefyException::networkError('Connection failed: ' . $e->getMessage(), $e);
            } catch (RequestException $e) {
                throw HuefyException::networkError('Request failed: ' . $e->getMessage(), $e);
            }
            $statusCode = $retryResponse->getStatusCode();
            $bodyString = (string) $retryResponse->getBody();
            if ($statusCode >= 200 && $statusCode <= 299) {
                $decoded = json_decode($bodyString, true);
                if (!is_array($decoded)) {
                    throw HuefyException::serverError('Failed to parse response body', $statusCode);
                }
                $this->parseRateLimitHeaders($retryResponse);
                return $decoded;
            }
            $response = $retryResponse;
        }

        $message = $this->config->enableErrorSanitization
            ? ErrorSanitizer::sanitize($bodyString)
            : $bodyString;

        $requestId = $response->getHeaderLine('X-Request-Id') ?: null;

        $retryAfterMs = null;
        $retryAfterHeader = $response->getHeaderLine('Retry-After');
        if ($retryAfterHeader !== '') {
            if (is_numeric($retryAfterHeader)) {
                $retryAfterMs = (int) ((float) $retryAfterHeader * 1000);
            } else {
                $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, $retryAfterHeader);
                if ($date !== false) {
                    $delaySeconds = $date->getTimestamp() - time();
                    $retryAfterMs = max(0, $delaySeconds * 1000);
                }
            }
        }

        throw HuefyException::fromStatusCode($statusCode, $message, $requestId, $retryAfterMs);
    }

    private function parseRateLimitHeaders(ResponseInterface $response): void
    {
        if ($this->config->onRateLimitUpdate === null && $this->config->onRateLimitWarning === null) {
            return;
        }

        $limitHeader = $response->getHeaderLine('X-RateLimit-Limit');
        $remainingHeader = $response->getHeaderLine('X-RateLimit-Remaining');
        $resetHeader = $response->getHeaderLine('X-RateLimit-Reset');

        if ($limitHeader === '' || $remainingHeader === '' || $resetHeader === '') {
            return;
        }

        if (!is_numeric($limitHeader) || !is_numeric($remainingHeader) || !is_numeric($resetHeader)) {
            return;
        }

        $limit = (int) $limitHeader;
        $remaining = (int) $remainingHeader;
        $resetAt = new \DateTimeImmutable('@' . (int) $resetHeader);
        $info = new RateLimitInfo($limit, $remaining, $resetAt);

        if ($this->config->onRateLimitUpdate !== null) {
            ($this->config->onRateLimitUpdate)($info);
        }

        if ($this->config->onRateLimitWarning !== null && $limit > 0 && $remaining < $limit * 0.2) {
            ($this->config->onRateLimitWarning)($info);
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function executeWithResilience(callable $fn): mixed
    {
        return $this->retryHandler->executeWithRetry(function () use ($fn): mixed {
            return $this->circuitBreaker->execute($fn);
        });
    }
}
