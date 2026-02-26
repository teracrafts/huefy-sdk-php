<?php

declare(strict_types=1);

namespace Huefy\Models;

class SendEmailResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly string $messageId,
        public readonly string $provider,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            message: $data['message'] ?? '',
            messageId: $data['messageId'] ?? '',
            provider: $data['provider'] ?? '',
        );
    }
}
