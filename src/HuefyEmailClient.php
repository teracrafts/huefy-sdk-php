<?php

declare(strict_types=1);

namespace Huefy;

use Huefy\Errors\HuefyException;
use Huefy\Models\BulkRecipient;
use Huefy\Models\EmailProvider;
use Huefy\Models\HealthResponse;
use Huefy\Models\SendEmailRequest;
use Huefy\Models\SendEmailResponse;
use Huefy\Models\SendBulkEmailsResponse;
use Huefy\Security\Security;
use Huefy\Validators\EmailValidators;

/**
 * Email-specific client that extends the base HuefyClient with email
 * domain operations.
 *
 * Usage:
 * ```php
 * $client = new HuefyEmailClient([
 *     'apiKey' => 'your-api-key',
 * ]);
 * $response = $client->sendEmail('welcome', ['name' => 'John'], 'john@example.com');
 * $client->close();
 * ```
 */
class HuefyEmailClient extends HuefyClient
{
    private const SEND_EMAIL_PATH = '/emails/send';
    private const SEND_BULK_EMAIL_PATH = '/emails/send-bulk';
    private const HEALTH_PATH = '/health';

    /**
     * Sends a single email using the Huefy API.
     *
     * @param string               $templateKey The template identifier.
     * @param array<string, string> $data       Template merge data (all values must be strings).
     * @param string               $recipient   The recipient email address.
     * @param string|null          $provider    Optional email provider (ses, sendgrid, mailgun, mailchimp).
     *
     * @return SendEmailResponse
     *
     * @throws HuefyException If validation fails or the request fails.
     */
    public function sendEmail(
        string $templateKey,
        array $data,
        string $recipient,
        ?string $provider = null,
    ): SendEmailResponse {
        $errors = EmailValidators::validateSendEmailInput($templateKey, $data, $recipient);
        if (!empty($errors)) {
            throw HuefyException::validationError(implode('; ', $errors));
        }

        if ($provider !== null && !EmailProvider::isValid($provider)) {
            throw HuefyException::validationError(
                sprintf('Invalid provider: %s. Must be one of: %s', $provider, implode(', ', EmailProvider::ALL)),
                'provider',
            );
        }

        // Check for potential PII in template data
        $dataJson = json_encode($data);
        if ($dataJson !== false) {
            $piiTypes = Security::detectPii($dataJson);
            if (!empty($piiTypes)) {
                trigger_error(
                    sprintf(
                        'Potential PII detected in email template data. Types: [%s]. '
                        . 'Please review whether this data should be transmitted and ensure '
                        . 'compliance with your data protection policies.',
                        implode(', ', $piiTypes),
                    ),
                    E_USER_WARNING,
                );
            }
        }

        $request = new SendEmailRequest(
            templateKey: $templateKey,
            recipient: $recipient,
            data: $data,
            provider: $provider,
        );

        $response = $this->request('POST', self::SEND_EMAIL_PATH, $request->toArray());

        return SendEmailResponse::fromArray($response);
    }

    /**
     * Sends emails to multiple recipients using the same template via the bulk API.
     *
     * @param string          $templateKey The template identifier.
     * @param BulkRecipient[] $recipients  Array of recipient objects.
     * @param array<string, mixed> $options Optional additional parameters (fromEmail, fromName, providerType, etc.).
     *
     * @return SendBulkEmailsResponse
     *
     * @throws HuefyException If validation fails or the request fails.
     */
    public function sendBulkEmails(
        string $templateKey,
        array $recipients,
        array $options = [],
    ): SendBulkEmailsResponse {
        $countError = EmailValidators::validateBulkCount(count($recipients));
        if ($countError !== null) {
            throw HuefyException::validationError($countError);
        }

        $body = [
            'templateKey' => $templateKey,
            'recipients' => array_map(
                fn(BulkRecipient $r) => array_filter(
                    ['email' => $r->email, 'type' => $r->type ?? 'to', 'data' => $r->data],
                    fn($v) => $v !== null,
                ),
                $recipients,
            ),
            ...$options,
        ];

        $response = $this->request('POST', self::SEND_BULK_EMAIL_PATH, $body);

        return SendBulkEmailsResponse::fromArray($response);
    }

    /**
     * Performs a typed health check against the API.
     *
     * @return HealthResponse
     *
     * @throws HuefyException If the request fails.
     */
    public function emailHealthCheck(): HealthResponse
    {
        $response = $this->request('GET', self::HEALTH_PATH);

        return HealthResponse::fromArray($response);
    }
}
