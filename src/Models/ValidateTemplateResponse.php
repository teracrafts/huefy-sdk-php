<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Models;

class ValidateTemplateResponseData
{
    /**
     * @param string[] $errors
     * @param string[] $warnings
     * @param string[] $variables
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly array $variables,
        public readonly string $validatedAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isValid: $data['isValid'] ?? false,
            errors: $data['errors'] ?? [],
            warnings: $data['warnings'] ?? [],
            variables: $data['variables'] ?? [],
            validatedAt: $data['validatedAt'] ?? '',
        );
    }
}

class ValidateTemplateResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ValidateTemplateResponseData $data,
        public readonly string $correlationId,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? false,
            data: ValidateTemplateResponseData::fromArray($data['data'] ?? []),
            correlationId: $data['correlationId'] ?? '',
        );
    }
}
