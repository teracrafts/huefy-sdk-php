<?php

declare(strict_types=1);

namespace Huefy\Models;

class BulkRecipient
{
    public function __construct(
        public readonly string $email,
        public readonly string $type = 'to',
        public readonly ?array $data = null,
    ) {}
}

class SendBulkEmailsResponseData
{
    public function __construct(
        public readonly string $batchId,
        public readonly string $status,
        public readonly string $templateKey,
        public readonly int $totalRecipients,
        public readonly int $successCount,
        public readonly int $failureCount,
        public readonly int $suppressedCount,
        public readonly string $startedAt,
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
            batchId: $data['batchId'] ?? '',
            status: $data['status'] ?? '',
            templateKey: $data['templateKey'] ?? '',
            totalRecipients: $data['totalRecipients'] ?? 0,
            successCount: $data['successCount'] ?? 0,
            failureCount: $data['failureCount'] ?? 0,
            suppressedCount: $data['suppressedCount'] ?? 0,
            startedAt: $data['startedAt'] ?? '',
            recipients: $recipients,
        );
    }
}

class SendBulkEmailsResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly SendBulkEmailsResponseData $data,
        public readonly string $correlationId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            data: SendBulkEmailsResponseData::fromArray($data['data'] ?? []),
            correlationId: $data['correlationId'] ?? '',
        );
    }
}
