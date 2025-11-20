<?php

declare(strict_types=1);

namespace Huefy\SDK;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Huefy\SDK\Config\HuefyConfig;
use Huefy\SDK\Exceptions\HuefyException;
use Huefy\SDK\Exceptions\NetworkException;
use Huefy\SDK\Exceptions\TimeoutException;
use JsonException;

/**
 * HTTP client for direct API communication with the Huefy API.
 */
class HttpClient
{
    private const USER_AGENT = 'Huefy-PHP-SDK/1.0.0';

    private GuzzleClient $httpClient;
    private string $apiKey;
    private HuefyConfig $config;

    public function __construct(string $apiKey, HuefyConfig $config)
    {
        $this->apiKey = $apiKey;
        $this->config = $config;
        $this->httpClient = $this->createHttpClient();
    }

    /**
     * Send an email using direct HTTP API.
     */
    public function sendEmail(array $requestData): array
    {
        return $this->makeRequest('POST', '/emails/send', $requestData);
    }

    /**
     * Send bulk emails using direct HTTP API.
     */
    public function sendBulkEmails(array $requests): array
    {
        return $this->makeRequest('POST', '/emails/bulk', ['emails' => $requests]);
    }

    /**
     * Check API health using direct HTTP API.
     */
    public function healthCheck(): array
    {
        return $this->makeRequest('GET', '/health');
    }

    /**
     * Get the base URL.
     */
    public function getEndpoint(): string
    {
        return $this->config->getBaseUrl();
    }

    /**
     * Get the timeout in milliseconds.
     */
    public function getTimeout(): int
    {
        return (int) ($this->config->getTimeout() * 1000);
    }

    /**
     * Make HTTP request with retry logic.
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->config->getBaseUrl(), '/') . $endpoint;
        $options = [];

        if (!empty($data)) {
            $options[RequestOptions::JSON] = $data;
        }

        $attempt = 0;
        $maxAttempts = $this->config->getRetryConfig()->getMaxRetries();
        $lastException = null;

        while ($attempt <= $maxAttempts) {
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $body = $response->getBody()->getContents();

                if (empty($body)) {
                    throw new HuefyException('Empty response body received');
                }

                try {
                    $responseData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new HuefyException('Failed to decode JSON response: ' . $e->getMessage(), 0, $e);
                }

                return $responseData;

            } catch (RequestException $e) {
                $lastException = $e;

                // Don't retry client errors (4xx)
                if ($e->hasResponse() && $e->getResponse()->getStatusCode() < 500) {
                    $this->handleRequestException($e);
                }

                $attempt++;
                if ($attempt <= $maxAttempts) {
                    $delay = min(
                        $this->config->getRetryConfig()->getBaseDelay() * (2 ** ($attempt - 1)),
                        $this->config->getRetryConfig()->getMaxDelay()
                    );
                    usleep((int) ($delay * 1000000));
                }

            } catch (GuzzleException $e) {
                throw new NetworkException('Network error: ' . $e->getMessage(), 0, $e);
            }
        }

        // All retries failed
        if ($lastException) {
            if ($lastException->hasResponse()) {
                $this->handleRequestException($lastException);
            }

            throw new NetworkException(
                'Request failed after ' . ($maxAttempts + 1) . ' attempts: ' . $lastException->getMessage(),
                0,
                $lastException
            );
        }

        throw new HuefyException('Unknown error occurred');
    }

    /**
     * Handle Guzzle request exceptions.
     */
    private function handleRequestException(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response === null) {
            if (str_contains($e->getMessage(), 'timeout')) {
                throw new TimeoutException('Request timed out: ' . $e->getMessage(), 0, $e);
            }
            throw new NetworkException('Network error: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        try {
            $errorData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $errorData = [
                'error' => [
                    'code' => 'HTTP_' . $statusCode,
                    'message' => $body ?: 'HTTP ' . $statusCode,
                ],
            ];
        }

        throw Exceptions\ExceptionFactory::createFromResponse($errorData, $statusCode, $e);
    }

    /**
     * Create and configure the HTTP client.
     */
    private function createHttpClient(): GuzzleClient
    {
        $config = [
            'base_uri' => $this->config->getBaseUrl(),
            'timeout' => $this->config->getTimeout(),
            'connect_timeout' => $this->config->getConnectTimeout(),
            'headers' => [
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ],
        ];

        return new GuzzleClient($config);
    }
}
