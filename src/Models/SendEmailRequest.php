<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Models;

class SendEmailRequest
{
    /**
     * @param array<string, mixed> $data
     * @param string|SendEmailRecipient|array<string, mixed> $recipient
     */
    public function __construct(
        public readonly string $templateKey,
        public readonly array $data,
        public readonly string|SendEmailRecipient|array $recipient,
        public readonly ?string $provider = null,
    ) {}

    public function toArray(): array
    {
        $result = [
            'templateKey' => $this->templateKey,
            'recipient' => $this->serializeRecipient($this->recipient),
            'data' => $this->data,
        ];
        if ($this->provider !== null) {
            $result['providerType'] = $this->provider;
        }
        return $result;
    }

    /**
     * @param string|SendEmailRecipient|array<string, mixed> $recipient
     *
     * @return string|array<string, mixed>
     */
    private function serializeRecipient(string|SendEmailRecipient|array $recipient): string|array
    {
        if (is_string($recipient)) {
            return trim($recipient);
        }

        if ($recipient instanceof SendEmailRecipient) {
            return $recipient->toArray();
        }

        $normalized = $recipient;
        if (isset($normalized['email']) && is_string($normalized['email'])) {
            $normalized['email'] = trim($normalized['email']);
        }
        if (isset($normalized['type']) && is_string($normalized['type'])) {
            $normalized['type'] = strtolower(trim($normalized['type']));
        }

        return $normalized;
    }
}
