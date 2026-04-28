<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Models;

class RecipientStatus
{
    public function __construct(
        public readonly string $email,
        public readonly string $status,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly ?string $sentAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? '',
            status: $data['status'] ?? '',
            messageId: $data['messageId'] ?? null,
            error: $data['error'] ?? null,
            sentAt: $data['sentAt'] ?? null,
        );
    }
}
