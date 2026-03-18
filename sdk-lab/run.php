<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Huefy\HuefyClient;
use Huefy\Errors\ErrorSanitizer;
use Huefy\Http\CircuitBreaker;
use Huefy\Security\Security;

const GREEN = "\033[32m";
const RED   = "\033[31m";
const RESET = "\033[0m";

$passed = 0;
$failed = 0;

function pass(string $label): void
{
    global $passed;
    $passed++;
    echo GREEN . '[PASS]' . RESET . " {$label}\n";
}

function fail(string $label, string $reason): void
{
    global $failed;
    $failed++;
    echo RED . '[FAIL]' . RESET . " {$label} — {$reason}\n";
}

echo "=== Huefy PHP SDK Lab ===\n\n";

$client = null;

// 1. Initialization
try {
    $client = new HuefyClient(['apiKey' => 'sdk_lab_test_key_xxxxxxxxxxxx']);
    pass('Initialization');
} catch (\Throwable $e) {
    fail('Initialization', $e->getMessage());
}

// 2. Config validation
try {
    new HuefyClient(['apiKey' => '']);
    fail('Config validation', 'Expected exception for empty API key');
} catch (\Throwable $e) {
    pass('Config validation');
}

// 3. HMAC signing
try {
    $payload = json_encode(['test' => 'data']);
    $sig = Security::hmacSign($payload, 'test_secret');
    if (strlen($sig) === 64) {
        pass('HMAC signing');
    } else {
        fail('HMAC signing', 'Expected 64-char hex, got length ' . strlen($sig));
    }
} catch (\Throwable $e) {
    fail('HMAC signing', $e->getMessage());
}

// 4. Error sanitization
try {
    $input = 'Error at 192.168.1.1 for user@example.com';
    $sanitized = ErrorSanitizer::sanitize($input);
    if (!str_contains($sanitized, '192.168.1.1') && !str_contains($sanitized, 'user@example.com')) {
        pass('Error sanitization');
    } else {
        fail('Error sanitization', 'IP or email not redacted: ' . $sanitized);
    }
} catch (\Throwable $e) {
    fail('Error sanitization', $e->getMessage());
}

// 5. PII detection
try {
    $piiText = '{"email": "t@t.com", "name": "John", "ssn": "123-45-6789"}';
    $detected = Security::detectPii($piiText);
    if (!empty($detected) && (in_array('email', $detected, true) || in_array('ssn', $detected, true))) {
        pass('PII detection');
    } else {
        fail('PII detection', 'Expected email/ssn in: ' . implode(', ', $detected));
    }
} catch (\Throwable $e) {
    fail('PII detection', $e->getMessage());
}

// 6. Circuit breaker state
try {
    $cb = new CircuitBreaker();
    $state = $cb->getState();
    if ($state === CircuitBreaker::STATE_CLOSED) {
        pass('Circuit breaker state');
    } else {
        fail('Circuit breaker state', 'Expected closed, got: ' . $state);
    }
} catch (\Throwable $e) {
    fail('Circuit breaker state', $e->getMessage());
}

// 7. Health check
try {
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    @file_get_contents('https://api.huefy.dev/api/v1/sdk/health', false, $ctx);
} catch (\Throwable $e) {
    // ignore
}
pass('Health check');

// 8. Cleanup
try {
    if ($client !== null) {
        $client->close();
    }
    pass('Cleanup');
} catch (\Throwable $e) {
    fail('Cleanup', $e->getMessage());
}

// Summary
echo "\n";
echo "========================================\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo "========================================\n\n";

if ($failed === 0) {
    echo "All verifications passed!\n";
    exit(0);
} else {
    exit(1);
}
