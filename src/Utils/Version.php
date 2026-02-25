<?php

declare(strict_types=1);

namespace Huefy\Utils;

/**
 * Version information for the Huefy SDK.
 */
class Version
{
    /** The current SDK version. */
    public const SDK_VERSION = '1.0.0';

    /** The SDK identifier used in User-Agent headers. */
    public const SDK_IDENTIFIER = 'huefy-php';

    /** Returns the full User-Agent string. */
    public static function userAgent(): string
    {
        return self::SDK_IDENTIFIER . '/' . self::SDK_VERSION;
    }
}
