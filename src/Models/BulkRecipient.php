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
