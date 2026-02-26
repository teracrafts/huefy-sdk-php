<?php

declare(strict_types=1);

namespace Huefy\Models;

class BulkEmailResult
{
    public function __construct(
        public readonly string $email,
        public readonly bool $success,
        public readonly ?SendEmailResponse $result = null,
        public readonly ?array $error = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $result = isset($data['result']) ? SendEmailResponse::fromArray($data['result']) : null;
        return new self(
            email: $data['email'] ?? '',
            success: $data['success'] ?? false,
            result: $result,
            error: $data['error'] ?? null,
        );
    }
}
