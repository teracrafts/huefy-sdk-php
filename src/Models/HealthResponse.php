<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Models;

class HealthResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $timestamp,
        public readonly string $version,
    ) {}

    public static function fromArray(array $data): self
    {
        $payload = $data['data'] ?? [];

        return new self(
            status: $payload['status'] ?? '',
            timestamp: $payload['timestamp'] ?? '',
            version: $payload['version'] ?? '',
        );
    }

    public function isHealthy(): bool
    {
        $status = strtolower($this->status);

        return $status === 'healthy' || $status === 'ok';
    }
}
