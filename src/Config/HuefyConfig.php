<?php

declare(strict_types=1);

namespace Huefy\SDK\Config;

use InvalidArgumentException;

/**
 * Configuration class for the Huefy client.
 *
 * This class provides configuration options for customizing the behavior
 * of the HuefyClient, including timeouts, retry settings, and API endpoint.
 *
 * @example
 * ```php
 * use Huefy\SDK\Config\HuefyConfig;
 * use Huefy\SDK\Config\RetryConfig;
 *
 * $config = new HuefyConfig(
 *     baseUrl: 'https://api.huefy.dev',
 *     timeout: 30.0,
 *     connectTimeout: 10.0,
 *     retryConfig: new RetryConfig(
 *         enabled: true,
 *         maxRetries: 5,
 *         baseDelay: 1.0,
 *         maxDelay: 30.0
 *     )
 * );
 *
 * $client = new HuefyClient('api-key', $config);
 * ```
 *
 * @author Huefy Team
 * @since 1.0.0
 */
class HuefyConfig
{
    public const TRANSPORT_KERNEL = 'kernel';
    public const TRANSPORT_HTTP = 'http';

    // Production endpoints (default)
    private const PRODUCTION_GRPC_ENDPOINT = 'api.huefy.dev:50051';
    private const PRODUCTION_HTTP_ENDPOINT = 'https://api.huefy.dev/api/v1/sdk';

    // Local development endpoints
    private const LOCAL_GRPC_ENDPOINT = 'localhost:50051';
    private const LOCAL_HTTP_ENDPOINT = 'http://localhost:8080/api/v1/sdk';

    private const DEFAULT_TIMEOUT = 30.0;
    private const DEFAULT_CONNECT_TIMEOUT = 10.0;
    private const DEFAULT_TRANSPORT = self::TRANSPORT_KERNEL;

    private ?string $baseUrl;
    private float $timeout;
    private float $connectTimeout;
    private RetryConfig $retryConfig;
    private string $transport;
    private bool $local;

    /**
     * Create a new Huefy configuration.
     *
     * @param string|null $baseUrl Custom endpoint (overrides local setting)
     * @param float $timeout Request timeout in seconds
     * @param float $connectTimeout Connection timeout in seconds
     * @param RetryConfig|null $retryConfig Retry configuration
     * @param string $transport Transport mode ('kernel' or 'http')
     * @param bool $local Use local development endpoints (default: false, uses production)
     *
     * @throws InvalidArgumentException If any parameter is invalid
     */
    public function __construct(
        ?string $baseUrl = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        ?RetryConfig $retryConfig = null,
        string $transport = self::DEFAULT_TRANSPORT,
        bool $local = false
    ) {
        $this->baseUrl = $baseUrl;
        $this->setTimeout($timeout);
        $this->setConnectTimeout($connectTimeout);
        $this->retryConfig = $retryConfig ?? new RetryConfig();
        $this->setTransport($transport);
        $this->local = $local;
    }

    /**
     * Get the gRPC endpoint for kernel transport.
     *
     * @return string
     */
    public function getGrpcEndpoint(): string
    {
        if ($this->baseUrl !== null) {
            return $this->baseUrl;
        }

        return $this->local ? self::LOCAL_GRPC_ENDPOINT : self::PRODUCTION_GRPC_ENDPOINT;
    }

    /**
     * Get the HTTP endpoint for direct API calls.
     *
     * @return string
     */
    public function getHttpEndpoint(): string
    {
        if ($this->baseUrl !== null) {
            return $this->baseUrl;
        }

        return $this->local ? self::LOCAL_HTTP_ENDPOINT : self::PRODUCTION_HTTP_ENDPOINT;
    }

    /**
     * Check if using local development endpoints.
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->local;
    }

    /**
     * Set custom base URL (overrides local setting).
     *
     * @param string|null $baseUrl
     */
    public function setBaseUrl(?string $baseUrl): void
    {
        $this->baseUrl = $baseUrl !== null ? rtrim(trim($baseUrl), '/') : null;
    }

    /**
     * Get the request timeout in seconds.
     *
     * @return float
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Set the request timeout in seconds.
     *
     * @param float $timeout
     *
     * @throws InvalidArgumentException If timeout is not positive
     */
    public function setTimeout(float $timeout): void
    {
        if ($timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->timeout = $timeout;
    }

    /**
     * Get the connection timeout in seconds.
     *
     * @return float
     */
    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    /**
     * Set the connection timeout in seconds.
     *
     * @param float $connectTimeout
     *
     * @throws InvalidArgumentException If timeout is not positive
     */
    public function setConnectTimeout(float $connectTimeout): void
    {
        if ($connectTimeout <= 0) {
            throw new InvalidArgumentException('Connect timeout must be positive');
        }

        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Get the retry configuration.
     *
     * @return RetryConfig
     */
    public function getRetryConfig(): RetryConfig
    {
        return $this->retryConfig;
    }

    /**
     * Set the retry configuration.
     *
     * @param RetryConfig $retryConfig
     */
    public function setRetryConfig(RetryConfig $retryConfig): void
    {
        $this->retryConfig = $retryConfig;
    }

    /**
     * Create a configuration with retries disabled.
     *
     * @param float $timeout Request timeout in seconds
     * @param float $connectTimeout Connection timeout in seconds
     * @param bool $local Use local development endpoints
     *
     * @return self
     */
    public static function withoutRetries(
        float $timeout = self::DEFAULT_TIMEOUT,
        float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        bool $local = false
    ): self {
        return new self(
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            retryConfig: RetryConfig::disabled(),
            local: $local
        );
    }

    /**
     * Get the transport mode.
     *
     * @return string 'kernel' or 'http'
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * Set the transport mode.
     *
     * @param string $transport 'kernel' or 'http'
     *
     * @throws InvalidArgumentException If transport is invalid
     */
    public function setTransport(string $transport): void
    {
        $valid = [self::TRANSPORT_KERNEL, self::TRANSPORT_HTTP];
        if (!in_array($transport, $valid, true)) {
            throw new InvalidArgumentException(
                sprintf('Transport must be one of: %s', implode(', ', $valid))
            );
        }

        $this->transport = $transport;
    }

    /**
     * Check if using kernel transport.
     *
     * @return bool
     */
    public function isKernelTransport(): bool
    {
        return $this->transport === self::TRANSPORT_KERNEL;
    }

    /**
     * Check if using HTTP transport.
     *
     * @return bool
     */
    public function isHttpTransport(): bool
    {
        return $this->transport === self::TRANSPORT_HTTP;
    }
}
