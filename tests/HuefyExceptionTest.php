<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Teracrafts\Huefy\Errors\ErrorCode;
use Teracrafts\Huefy\Errors\HuefyException;

final class HuefyExceptionTest extends TestCase
{
    public function testMapsPaymentRequiredToInsufficientQuota(): void
    {
        $error = HuefyException::fromStatusCode(
            402,
            '{"error":"Quota exceeded","code":"INSUFFICIENT_QUOTA"}',
            'req_123',
        );

        self::assertSame(ErrorCode::INSUFFICIENT_QUOTA, $error->getErrorCode());
        self::assertSame(3003, ErrorCode::numericCode($error->getErrorCode()));
        self::assertSame(402, $error->getStatusCode());
        self::assertSame('req_123', $error->getRequestId());
        self::assertFalse($error->isRecoverable());
        self::assertStringContainsString('Quota exceeded', $error->getMessage());
    }
}
