<?php

declare(strict_types=1);

namespace Teracrafts\Huefy;

use Teracrafts\Huefy\Errors\HuefyException;
use Teracrafts\Huefy\Http\HttpClient;
use Teracrafts\Huefy\Utils\Version;

/**
 * Main client for the Huefy SDK.
 *
 * Usage:
 * ```php
 * $client = new HuefyClient([
 *     'apiKey' => 'your-api-key',
 * ]);
 * $health = $client->healthCheck();
 * $client->close();
 * ```
 */
class HuefyClient
{
    private HuefyConfig $config;
    private HttpClient $httpClient;

    /**
     * @param array<string, mixed> $config Configuration options.
     *
     * @throws HuefyException If the configuration is invalid.
     */
    public function __construct(array $config)
    {
        $this->config = new HuefyConfig($config);
        $this->httpClient = new HttpClient($this->config);
    }

    /**
     * Performs a health check against the API.
     *
     * @return array<string, mixed>
     *
     * @throws HuefyException If the request fails.
     */
    public function healthCheck(): array
    {
        try {
            return $this->httpClient->get('/health');
        } catch (HuefyException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw HuefyException::networkError('Health check failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Sends a request to the API.
     *
     * @param string                    $method HTTP method (GET, POST, PUT, DELETE).
     * @param string                    $path   API endpoint path.
     * @param array<string, mixed>|null $body   Optional request body.
     *
     * @return array<string, mixed> Parsed JSON response.
     *
     * @throws HuefyException If the request fails.
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        return match (strtoupper($method)) {
            'GET' => $this->httpClient->get($path),
            'POST' => $this->httpClient->post($path, $body),
            'PUT' => $this->httpClient->put($path, $body),
            'DELETE' => $this->httpClient->delete($path),
            default => throw HuefyException::validationError("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Returns the current configuration.
     */
    public function getConfig(): HuefyConfig
    {
        return $this->config;
    }

    /**
     * Closes the underlying HTTP client and releases resources.
     */
    public function close(): void
    {
        $this->httpClient->close();
    }
}
