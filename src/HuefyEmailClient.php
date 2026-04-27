<?php

declare(strict_types=1);

namespace Huefy;

use Huefy\Errors\HuefyException;
use Huefy\Models\BulkRecipient;
use Huefy\Models\EmailProvider;
use Huefy\Models\HealthResponse;
use Huefy\Models\SendBulkEmailsRequest;
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
 * $client = new HuefyEmailClient(['apiKey' => 'your-api-key']);
 * $response = $client->sendEmail(new SendEmailRequest(
 *     templateKey: 'welcome',
 *     data: ['name' => 'John'],
 *     recipient: new SendEmailRecipient(email: 'john@example.com', type: 'cc'),
 * ));
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
     * @param SendEmailRequest $request The email request object.
     *
     * @return SendEmailResponse
     *
     * @throws HuefyException If validation fails or the request fails.
     */
    public function sendEmail(SendEmailRequest $request): SendEmailResponse
    {
        $errors = EmailValidators::validateSendEmailInput($request->templateKey, $request->data, $request->recipient);
        if (!empty($errors)) {
            throw HuefyException::validationError(implode('; ', $errors));
        }

        if ($request->provider !== null && !EmailProvider::isValid($request->provider)) {
            throw HuefyException::validationError(
                sprintf('Invalid provider: %s. Must be one of: %s', $request->provider, implode(', ', EmailProvider::ALL)),
                'provider',
            );
        }

        // Check for potential PII in template data
        $dataJson = json_encode($request->data);
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

        $this->warnIfPotentialRecipientPii($request->recipient);

        $response = $this->request('POST', self::SEND_EMAIL_PATH, $request->toArray());

        return SendEmailResponse::fromArray($response);
    }

    /**
     * Sends emails to multiple recipients using the same template via the bulk API.
     *
     * @param SendBulkEmailsRequest $request The bulk email request object.
     *
     * @return SendBulkEmailsResponse
     *
     * @throws HuefyException If validation fails or the request fails.
     */
    public function sendBulkEmails(SendBulkEmailsRequest $request): SendBulkEmailsResponse
    {
        $countError = EmailValidators::validateBulkCount(count($request->recipients));
        if ($countError !== null) {
            throw HuefyException::validationError($countError);
        }

        foreach ($request->recipients as $i => $r) {
            $emailError = EmailValidators::validateEmail($r->email);
            if ($emailError !== null) {
                throw HuefyException::validationError("recipients[$i]: $emailError");
            }
        }

        $body = [
            'templateKey' => $request->templateKey,
            'recipients' => array_map(
                fn(BulkRecipient $r) => array_filter(
                    ['email' => $r->email, 'type' => $r->type ?? 'to', 'data' => $r->data],
                    fn($v) => $v !== null,
                ),
                $request->recipients,
            ),
        ];

        if ($request->provider !== null) {
            $body['providerType'] = $request->provider;
        }

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

    /**
     * @param string|\Huefy\Models\SendEmailRecipient|array<string, mixed> $recipient
     */
    private function warnIfPotentialRecipientPii(string|object|array $recipient): void
    {
        $recipientData = null;

        if ($recipient instanceof \Huefy\Models\SendEmailRecipient) {
            $recipientData = $recipient->data;
        } elseif (is_array($recipient) && isset($recipient['data']) && is_array($recipient['data'])) {
            $recipientData = $recipient['data'];
        }

        if ($recipientData === null) {
            return;
        }

        $dataJson = json_encode($recipientData);
        if ($dataJson === false) {
            return;
        }

        $piiTypes = Security::detectPii($dataJson);
        if (!empty($piiTypes)) {
            trigger_error(
                sprintf(
                    'Potential PII detected in recipient data. Types: [%s]. '
                    . 'Please review whether this data should be transmitted and ensure '
                    . 'compliance with your data protection policies.',
                    implode(', ', $piiTypes),
                ),
                E_USER_WARNING,
            );
        }
    }
}
