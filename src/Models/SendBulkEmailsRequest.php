<?php

declare(strict_types=1);

namespace Huefy\Models;

class SendBulkEmailsRequest
{
    /**
     * @param string          $templateKey The template identifier.
     * @param BulkRecipient[] $recipients  Array of recipient objects.
     * @param string|null     $provider    Optional email provider (ses, sendgrid, mailgun, mailchimp).
     */
    public function __construct(
        public readonly string $templateKey,
        public readonly array $recipients,
        public readonly ?string $provider = null,
    ) {}
}
