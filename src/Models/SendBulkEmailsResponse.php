<?php

declare(strict_types=1);

namespace Huefy\Models;

class SendBulkEmailsResponseData
{
    public function __construct(
        public readonly string $batchId,
        public readonly string $status,
        public readonly string $templateKey,
        public readonly int $templateVersion,
        public readonly string $senderUsed,
        public readonly bool $senderVerified,
        public readonly int $totalRecipients,
        public readonly int $processedCount,
        public readonly int $successCount,
        public readonly int $failureCount,
        public readonly int $suppressedCount,
        public readonly string $startedAt,
        public readonly ?string $completedAt,
        /** @var RecipientStatus[] */
        public readonly array $recipients,
        /** @var array<int, array<string, mixed>> */
        public readonly array $errors,
        /** @var array<string, mixed>|null */
        public readonly ?array $metadata,
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
            templateVersion: $data['templateVersion'] ?? 0,
            senderUsed: $data['senderUsed'] ?? '',
            senderVerified: $data['senderVerified'] ?? false,
            totalRecipients: $data['totalRecipients'] ?? 0,
            processedCount: $data['processedCount'] ?? 0,
            successCount: $data['successCount'] ?? 0,
            failureCount: $data['failureCount'] ?? 0,
            suppressedCount: $data['suppressedCount'] ?? 0,
            startedAt: $data['startedAt'] ?? '',
            completedAt: $data['completedAt'] ?? null,
            recipients: $recipients,
            errors: $data['errors'] ?? [],
            metadata: $data['metadata'] ?? null,
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
