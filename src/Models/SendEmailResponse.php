<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Models;

class SendEmailResponseData
{
    public function __construct(
        public readonly string $emailId,
        public readonly string $status,
        /** @var RecipientStatus[] */
        public readonly array $recipients,
    ) {}

    public static function fromArray(array $data): self
    {
        $recipients = array_map(
            fn(array $r) => RecipientStatus::fromArray($r),
            $data['recipients'] ?? [],
        );

        return new self(
            emailId: $data['emailId'] ?? '',
            status: $data['status'] ?? '',
            recipients: $recipients,
        );
    }
}

class SendEmailResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly SendEmailResponseData $data,
        public readonly string $correlationId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            data: SendEmailResponseData::fromArray($data['data'] ?? []),
            correlationId: $data['correlationId'] ?? '',
        );
    }
}
