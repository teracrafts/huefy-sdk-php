<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Errors;

/**
 * Sanitizes error messages and context data to remove PII and sensitive
 * information before logging or returning errors to callers.
 */
class ErrorSanitizer
{
    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';
    private const PHONE_PATTERN = '/\+?[1-9]\d{1,14}|\(\d{3}\)\s?\d{3}[\-.]?\d{4}/';
    private const API_KEY_PATTERN = '/(api[_\-]?key|token|secret|password|authorization)["\']?\s*[:=]\s*["\']?([^\s"\',}{}\]]+)/i';
    private const IP_ADDRESS_PATTERN = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';
    private const CREDIT_CARD_PATTERN = '/\b(?:\d{4}[\s\-]?){3}\d{4}\b/';

    private const EMAIL_REPLACEMENT = '[EMAIL_REDACTED]';
    private const PHONE_REPLACEMENT = '[PHONE_REDACTED]';
    private const KEY_REPLACEMENT = '[KEY_REDACTED]';
    private const IP_REPLACEMENT = '[IP_REDACTED]';
    private const CARD_REPLACEMENT = '[CARD_REDACTED]';

    /**
     * Sanitizes a string by replacing detected PII patterns with redaction
     * placeholders.
     */
    public static function sanitize(string $input): string
    {
        $result = $input;

        // Order matters: credit cards before phones (to avoid partial matches)
        $result = preg_replace(self::CREDIT_CARD_PATTERN, self::CARD_REPLACEMENT, $result) ?? $result;
        $result = preg_replace_callback(self::API_KEY_PATTERN, function (array $matches): string {
            return $matches[1] . '=' . self::KEY_REPLACEMENT;
        }, $result) ?? $result;
        $result = preg_replace(self::EMAIL_PATTERN, self::EMAIL_REPLACEMENT, $result) ?? $result;
        $result = preg_replace(self::PHONE_PATTERN, self::PHONE_REPLACEMENT, $result) ?? $result;
        $result = preg_replace(self::IP_ADDRESS_PATTERN, self::IP_REPLACEMENT, $result) ?? $result;

        return $result;
    }

    /**
     * Sanitizes all values in an associative array.
     *
     * @param array<string, string> $context
     *
     * @return array<string, string>
     */
    public static function sanitizeContext(array $context): array
    {
        return array_map([self::class, 'sanitize'], $context);
    }

    /**
     * Checks whether a string contains any detectable PII.
     */
    public static function containsPii(string $input): bool
    {
        return (bool) preg_match(self::EMAIL_PATTERN, $input)
            || (bool) preg_match(self::PHONE_PATTERN, $input)
            || (bool) preg_match(self::API_KEY_PATTERN, $input)
            || (bool) preg_match(self::CREDIT_CARD_PATTERN, $input);
    }
}
