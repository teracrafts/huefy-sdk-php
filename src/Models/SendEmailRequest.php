<?php

declare(strict_types=1);

namespace Huefy\Models;

class SendEmailRequest
{
    public function __construct(
        public readonly string $templateKey,
        public readonly array $data,
        public readonly string $recipient,
        public readonly ?string $provider = null,
    ) {}

    public function toArray(): array
    {
        $result = [
            'templateKey' => $this->templateKey,
            'recipient' => $this->recipient,
            'data' => $this->data,
        ];
        if ($this->provider !== null) {
            $result['providerType'] = $this->provider;
        }
        return $result;
    }
}
