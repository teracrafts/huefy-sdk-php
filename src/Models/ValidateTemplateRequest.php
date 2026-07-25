<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Models;

class ValidateTemplateRequest
{
    /**
     * @param array<string, mixed>|null $testData
     */
    public function __construct(
        public readonly string $templateKey,
        public readonly ?int $templateVersion = null,
        public readonly ?array $testData = null,
        public readonly ?string $correlationId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'templateKey' => trim($this->templateKey),
                'templateVersion' => $this->templateVersion,
                'testData' => $this->testData,
                'correlationId' => $this->correlationId,
            ],
            fn($value) => $value !== null,
        );
    }
}
