<?php

declare(strict_types=1);

namespace Huefy\Validators;

/**
 * Validation utilities for email-related inputs.
 */
class EmailValidators
{
    private const EMAIL_REGEX = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    private const MAX_EMAIL_LENGTH = 254;
    private const MAX_TEMPLATE_KEY_LENGTH = 100;
    private const MAX_BULK_EMAILS = 1000;

    /**
     * Validates an email address.
     *
     * @return string|null Error message, or null if valid.
     */
    public static function validateEmail(string $email): ?string
    {
        if ($email === '') {
            return 'Recipient email is required';
        }
        $trimmed = trim($email);
        if (strlen($trimmed) > self::MAX_EMAIL_LENGTH) {
            return sprintf('Email exceeds maximum length of %d characters', self::MAX_EMAIL_LENGTH);
        }
        if (!preg_match(self::EMAIL_REGEX, $trimmed)) {
            return sprintf('Invalid email address: %s', $trimmed);
        }
        return null;
    }

    /**
     * Validates a template key.
     *
     * @return string|null Error message, or null if valid.
     */
    public static function validateTemplateKey(string $templateKey): ?string
    {
        if ($templateKey === '') {
            return 'Template key is required';
        }
        $trimmed = trim($templateKey);
        if ($trimmed === '') {
            return 'Template key cannot be empty';
        }
        if (strlen($trimmed) > self::MAX_TEMPLATE_KEY_LENGTH) {
            return sprintf('Template key exceeds maximum length of %d characters', self::MAX_TEMPLATE_KEY_LENGTH);
        }
        return null;
    }

    /**
     * Validates template data.
     *
     * @param array<string, mixed> $data
     *
     * @return string|null Error message, or null if valid.
     */
    public static function validateEmailData(array $data): ?string
    {
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                return sprintf('Template data value for key "%s" must be a string', $key);
            }
        }
        return null;
    }

    /**
     * Validates the count for a bulk email operation.
     *
     * @return string|null Error message, or null if valid.
     */
    public static function validateBulkCount(int $count): ?string
    {
        if ($count <= 0) {
            return 'At least one email is required';
        }
        if ($count > self::MAX_BULK_EMAILS) {
            return sprintf('Maximum of %d emails per bulk request', self::MAX_BULK_EMAILS);
        }
        return null;
    }

    /**
     * Validates all inputs for a send email request.
     *
     * @param array<string, mixed> $data
     *
     * @return string[] Array of error messages. Empty if valid.
     */
    public static function validateSendEmailInput(string $templateKey, array $data, string $recipient): array
    {
        $errors = [];
        $keyError = self::validateTemplateKey($templateKey);
        if ($keyError !== null) {
            $errors[] = $keyError;
        }
        $dataError = self::validateEmailData($data);
        if ($dataError !== null) {
            $errors[] = $dataError;
        }
        $emailError = self::validateEmail($recipient);
        if ($emailError !== null) {
            $errors[] = $emailError;
        }
        return $errors;
    }
}
