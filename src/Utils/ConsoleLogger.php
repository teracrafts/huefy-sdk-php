<?php

declare(strict_types=1);

namespace Huefy\Utils;

class ConsoleLogger implements Logger
{
    public function debug(string $message): void
    {
        error_log("[DEBUG] [Huefy] $message");
    }

    public function info(string $message): void
    {
        error_log("[INFO] [Huefy] $message");
    }

    public function warning(string $message): void
    {
        error_log("[WARNING] [Huefy] $message");
    }

    public function error(string $message): void
    {
        error_log("[ERROR] [Huefy] $message");
    }
}
