<?php

declare(strict_types=1);

namespace Huefy\Models;

class HealthResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $timestamp,
        public readonly string $version,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? '',
            timestamp: $data['timestamp'] ?? '',
            version: $data['version'] ?? '',
        );
    }

    public function isHealthy(): bool
    {
        return strtolower($this->status) === 'ok';
    }
}
