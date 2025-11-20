<?php

declare(strict_types=1);

namespace Huefy\SDK;

use Huefy\SDK\Config\HuefyConfig;
use Huefy\SDK\KernelClient;
use Huefy\SDK\HttpClient;
use Huefy\SDK\Exceptions\HuefyException;
use Huefy\SDK\Exceptions\NetworkException;
use Huefy\SDK\Exceptions\TimeoutException;
use Huefy\SDK\Exceptions\ValidationException;
use Huefy\SDK\Models\BulkEmailResponse;
use Huefy\SDK\Models\HealthResponse;
use Huefy\SDK\Models\SendEmailRequest;
use Huefy\SDK\Models\SendEmailResponse;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main client for the Huefy email sending platform.
 *
 * The HuefyClient provides a simple interface for sending template-based emails
 * through the Huefy API with support for multiple email providers, retry logic,
 * and comprehensive error handling.
 *
 * @example
 * ```php
 * use Huefy\SDK\HuefyClient;
 * use Huefy\SDK\Models\SendEmailRequest;
 * use Huefy\SDK\Models\EmailProvider;
 *
 * $client = new HuefyClient('your-api-key');
 *
 * $request = new SendEmailRequest(
 *     templateKey: 'welcome-email',
 *     recipient: 'john@example.com',
 *     data: ['name' => 'John Doe', 'company' => 'Acme Corp'],
 *     provider: EmailProvider::SENDGRID
 * );
 *
 * $response = $client->sendEmail($request);
 * echo "Email sent: {$response->messageId}";
 * ```
 *
 * @author Huefy Team
 * @since 1.0.0
 */
class HuefyClient
{

    private KernelClient|HttpClient $client;
    private HuefyConfig $config;
    private LoggerInterface $logger;

    /**
     * Create a new Huefy client.
     *
     * @param string $apiKey The Huefy API key
     * @param HuefyConfig|null $config Optional client configuration
     * @param LoggerInterface|null $logger Optional logger instance
     *
     * @throws InvalidArgumentException If API key is empty
     */
    public function __construct(
        private readonly string $apiKey,
        ?HuefyConfig $config = null,
        ?LoggerInterface $logger = null
    ) {
        if (empty(trim($this->apiKey))) {
            throw new InvalidArgumentException('API key cannot be empty');
        }

        $this->config = $config ?? new HuefyConfig();
        $this->logger = $logger ?? new NullLogger();

        // Create appropriate client based on transport config
        if ($this->config->isHttpTransport()) {
            $this->client = new HttpClient($this->apiKey, $this->config);
        } else {
            $this->client = new KernelClient($this->apiKey, $this->config);
        }

        $this->logger->debug('HuefyClient initialized', [
            'transport' => $this->config->getTransport(),
            'endpoint' => $this->client->getEndpoint(),
            'timeout' => $this->client->getTimeout(),
        ]);
    }

    /**
     * Send a single email using a template.
     *
     * @param SendEmailRequest $request The email request
     *
     * @return SendEmailResponse The email response
     *
     * @throws HuefyException If the request fails
     * @throws ValidationException If the request is invalid
     */
    public function sendEmail(SendEmailRequest $request): SendEmailResponse
    {
        $request->validate();

        $responseData = $this->client->sendEmail($request->toArray());

        return SendEmailResponse::fromArray($responseData);
    }

    /**
     * Send multiple emails in a single request.
     *
     * @param SendEmailRequest[] $requests Array of email requests
     *
     * @return BulkEmailResponse The bulk email response
     *
     * @throws HuefyException If the request fails
     * @throws InvalidArgumentException If requests array is empty
     * @throws ValidationException If any request is invalid
     */
    public function sendBulkEmails(array $requests): BulkEmailResponse
    {
        if (empty($requests)) {
            throw new InvalidArgumentException('Requests array cannot be empty');
        }

        // Validate all requests
        foreach ($requests as $index => $request) {
            if (!$request instanceof SendEmailRequest) {
                throw new InvalidArgumentException(
                    sprintf('Request at index %d must be an instance of SendEmailRequest', $index)
                );
            }

            try {
                $request->validate();
            } catch (ValidationException $e) {
                throw new ValidationException(
                    sprintf('Validation failed for request %d: %s', $index, $e->getMessage()),
                    $e->getCode(),
                    $e
                );
            }
        }

        $payload = [
            'emails' => array_map(fn (SendEmailRequest $req) => $req->toArray(), $requests),
        ];

        $responseData = $this->client->sendBulkEmails($payload['emails']);

        return BulkEmailResponse::fromArray($responseData);
    }

    /**
     * Check the health status of the Huefy API.
     *
     * @return HealthResponse The health response
     *
     * @throws HuefyException If the request fails
     */
    public function healthCheck(): HealthResponse
    {
        $responseData = $this->client->healthCheck();
        return HealthResponse::fromArray($responseData);
    }

}
