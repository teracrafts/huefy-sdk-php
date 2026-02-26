<?php

declare(strict_types=1);

namespace Huefy\Security;

/**
 * Security utilities for the Huefy SDK.
 *
 * Provides PII detection, HMAC request signing, and API key helpers.
 */
class Security
{
    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';
    private const PHONE_PATTERN = '/\+?[1-9]\d{1,14}/';
    private const SSN_PATTERN = '/\b\d{3}-\d{2}-\d{4}\b/';
    private const CREDIT_CARD_PATTERN = '/\b(?:\d{4}[\s\-]?){3}\d{4}\b/';

    private const HMAC_ALGORITHM = 'sha256';

    /**
     * Detects whether the given text contains PII patterns.
     *
     * @return string[] List of detected PII types (e.g., "email", "phone").
     */
    public static function detectPii(string $text): array
    {
        $detected = [];

        if (preg_match(self::EMAIL_PATTERN, $text)) {
            $detected[] = 'email';
        }
        if (preg_match(self::PHONE_PATTERN, $text)) {
            $detected[] = 'phone';
        }
        if (preg_match(self::SSN_PATTERN, $text)) {
            $detected[] = 'ssn';
        }
        if (preg_match(self::CREDIT_CARD_PATTERN, $text)) {
            $detected[] = 'credit_card';
        }

        return $detected;
    }

    /**
     * Generates an HMAC-SHA256 signature for request signing.
     *
     * @return string Hex-encoded HMAC signature.
     */
    public static function hmacSign(string $payload, string $secret): string
    {
        $hash = hash_hmac(self::HMAC_ALGORITHM, $payload, $secret);
        if ($hash === false) {
            throw new \RuntimeException('HMAC signature generation failed');
        }

        return $hash;
    }

    /**
     * Verifies an HMAC-SHA256 signature.
     *
     * Uses constant-time comparison to prevent timing attacks.
     */
    public static function hmacVerify(string $payload, string $signature, string $secret): bool
    {
        $expected = self::hmacSign($payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Masks an API key for safe display in logs.
     *
     * Shows only the first 4 and last 4 characters.
     */
    public static function maskApiKey(string $apiKey): string
    {
        $length = strlen($apiKey);
        if ($length <= 8) {
            return '****';
        }

        $prefix = substr($apiKey, 0, 4);
        $suffix = substr($apiKey, -4);
        $masked = str_repeat('*', $length - 8);

        return $prefix . $masked . $suffix;
    }

    /**
     * Validates that an API key meets minimum format requirements.
     */
    public static function validateApiKeyFormat(string $apiKey): bool
    {
        $trimmed = trim($apiKey);
        if ($trimmed === '' || strlen($trimmed) < 16) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9\-_.]+$/', $trimmed);
    }

    /**
     * Generates a cryptographically secure random string suitable for nonces.
     */
    public static function generateNonce(int $length = 32): string
    {
        $bytes = random_bytes($length);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
