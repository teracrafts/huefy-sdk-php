<?php

declare(strict_types=1);

namespace Huefy\Models;

class EmailProvider
{
    public const SES = 'ses';
    public const SENDGRID = 'sendgrid';
    public const MAILGUN = 'mailgun';
    public const MAILCHIMP = 'mailchimp';

    public const ALL = [self::SES, self::SENDGRID, self::MAILGUN, self::MAILCHIMP];

    public static function isValid(string $provider): bool
    {
        return in_array($provider, self::ALL, true);
    }
}
