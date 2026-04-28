<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Models;

class SendEmailRecipient
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $type = null,
        public readonly ?array $data = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'email' => trim($this->email),
                'type' => $this->type !== null ? strtolower(trim($this->type)) : null,
                'data' => $this->data,
            ],
            static fn($value): bool => $value !== null,
        );
    }
}
