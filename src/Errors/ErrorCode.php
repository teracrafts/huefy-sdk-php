<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Errors;

/**
 * Error code constants for the Huefy SDK.
 *
 * Each constant maps to a unique numeric code for programmatic handling.
 */
class ErrorCode
{
    public const UNKNOWN_ERROR = 'UNKNOWN_ERROR';
    public const NETWORK_ERROR = 'NETWORK_ERROR';
    public const TIMEOUT_ERROR = 'TIMEOUT_ERROR';
    public const AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR';
    public const FORBIDDEN_ERROR = 'FORBIDDEN_ERROR';
    public const NOT_FOUND_ERROR = 'NOT_FOUND_ERROR';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const RATE_LIMIT_ERROR = 'RATE_LIMIT_ERROR';
    public const SERVER_ERROR = 'SERVER_ERROR';
    public const CIRCUIT_BREAKER_OPEN = 'CIRCUIT_BREAKER_OPEN';
    public const SIGNING_ERROR = 'SIGNING_ERROR';
    public const SECURITY_ERROR = 'SECURITY_ERROR';

    /**
     * Map of error code names to their numeric codes.
     *
     * @var array<string, int>
     */
    public const NUMERIC_CODES = [
        self::UNKNOWN_ERROR => 1000,
        self::NETWORK_ERROR => 1001,
        self::TIMEOUT_ERROR => 1002,
        self::AUTHENTICATION_ERROR => 2001,
        self::FORBIDDEN_ERROR => 2002,
        self::NOT_FOUND_ERROR => 2003,
        self::VALIDATION_ERROR => 3001,
        self::RATE_LIMIT_ERROR => 3002,
        self::SERVER_ERROR => 4001,
        self::CIRCUIT_BREAKER_OPEN => 5001,
        self::SIGNING_ERROR => 6001,
        self::SECURITY_ERROR => 6002,
    ];

    /**
     * Returns the numeric code for a given error code string.
     */
    public static function numericCode(string $errorCode): int
    {
        return self::NUMERIC_CODES[$errorCode] ?? self::NUMERIC_CODES[self::UNKNOWN_ERROR];
    }

    /**
     * Returns the error code string for a given numeric code.
     */
    public static function fromNumericCode(int $code): string
    {
        $flipped = array_flip(self::NUMERIC_CODES);

        return $flipped[$code] ?? self::UNKNOWN_ERROR;
    }
}
