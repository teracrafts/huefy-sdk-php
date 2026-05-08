<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Teracrafts\\Huefy\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        if ($relative === false) {
            return;
        }

        $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    });

    require_once __DIR__ . '/../src/legacy_aliases.php';
}

use Teracrafts\Huefy\Errors\HuefyException;
use Teracrafts\Huefy\HuefyEmailClient;
use Teracrafts\Huefy\Models\BulkRecipient;
use Teracrafts\Huefy\Models\HealthResponse;
use Teracrafts\Huefy\Models\SendBulkEmailsRequest;
use Teracrafts\Huefy\Models\SendBulkEmailsResponse;
use Teracrafts\Huefy\Models\SendEmailRecipient;
use Teracrafts\Huefy\Models\SendEmailRequest;
use Teracrafts\Huefy\Models\SendEmailResponse;

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

final class LabEmailClient extends HuefyEmailClient
{
    /** @var list<array<string, mixed>> */
    private array $responses = [];
    /** @var list<array{method:string,path:string,body:?array}> */
    public array $calls = [];

    public function __construct(array $config = [])
    {
        // The lab exercises request shaping and validation, so it does not need
        // the production HTTP transport or Composer-installed dependencies.
        $this->calls = [];
    }

    /**
     * @param list<array<string, mixed>> $responses
     */
    public function queueResponses(array $responses): void
    {
        $this->responses = $responses;
        $this->calls = [];
    }

    public function request(string $method, string $path, ?array $body = null): array
    {
        $this->calls[] = ['method' => $method, 'path' => $path, 'body' => $body];
        if ($this->responses === []) {
            throw new \RuntimeException('No queued response');
        }
        return array_shift($this->responses);
    }

    public function close(): void
    {
        // No transport resources are allocated in the lab harness.
    }
}

echo "=== Huefy PHP SDK Lab ===\n\n";

$client = null;

try {
    $client = new LabEmailClient(['apiKey' => 'sdk_lab_test_key_xxxxxxxxxxxx']);
    pass('Initialization');
} catch (\Throwable $e) {
    fail('Initialization', $e->getMessage());
}

try {
    assert($client instanceof LabEmailClient);
    $client->queueResponses([
        [
            'success' => true,
            'data' => [
                'emailId' => 'email_123',
                'status' => 'queued',
                'recipients' => [['email' => 'alice@example.com', 'status' => 'queued']],
            ],
            'correlationId' => 'corr_send_123',
        ],
    ]);
    $response = $client->sendEmail(new SendEmailRequest(
        templateKey: ' welcome-email ',
        data: ['firstName' => 'Alice'],
        recipient: new SendEmailRecipient(email: ' alice@example.com ', type: 'CC', data: ['locale' => 'en']),
        provider: 'ses',
    ));
    $call = $client->calls[0] ?? null;
    $recipient = $call['body']['recipient'] ?? null;
    $ok =
        $call !== null &&
        $call['method'] === 'POST' &&
        $call['path'] === '/emails/send' &&
        ($call['body']['templateKey'] ?? null) === 'welcome-email' &&
        is_array($recipient) &&
        ($recipient['email'] ?? null) === 'alice@example.com' &&
        ($recipient['type'] ?? null) === 'cc' &&
        ($call['body']['providerType'] ?? null) === 'ses' &&
        $response instanceof SendEmailResponse &&
        $response->data->emailId === 'email_123';
    $ok ? pass('Single email contract') : fail('Single email contract', json_encode($call) ?: 'call mismatch');
} catch (\Throwable $e) {
    fail('Single email contract', $e->getMessage());
}

try {
    assert($client instanceof LabEmailClient);
    $client->queueResponses([
        [
            'success' => true,
            'data' => [
                'batchId' => 'batch_123',
                'status' => 'processing',
                'templateKey' => 'digest',
                'templateVersion' => 3,
                'senderUsed' => 'alerts@huefy.dev',
                'senderVerified' => true,
                'totalRecipients' => 2,
                'processedCount' => 0,
                'successCount' => 0,
                'failureCount' => 0,
                'suppressedCount' => 0,
                'startedAt' => '2026-05-07T10:00:00Z',
                'recipients' => [
                    ['email' => 'alice@example.com', 'status' => 'queued'],
                    ['email' => 'bob@example.com', 'status' => 'queued'],
                ],
            ],
            'correlationId' => 'corr_bulk_123',
        ],
    ]);
    $response = $client->sendBulkEmails(new SendBulkEmailsRequest(
        templateKey: ' digest ',
        recipients: [
            new BulkRecipient(email: ' alice@example.com ', type: 'TO', data: ['locale' => 'en']),
            new BulkRecipient(email: ' bob@example.com ', type: 'BCC'),
        ],
        provider: 'mailgun',
    ));
    $call = $client->calls[0] ?? null;
    $recipients = $call['body']['recipients'] ?? null;
    $ok =
        $call !== null &&
        $call['method'] === 'POST' &&
        $call['path'] === '/emails/send-bulk' &&
        ($call['body']['templateKey'] ?? null) === 'digest' &&
        ($call['body']['providerType'] ?? null) === 'mailgun' &&
        is_array($recipients) &&
        ($recipients[0]['email'] ?? null) === 'alice@example.com' &&
        ($recipients[0]['type'] ?? null) === 'to' &&
        ($recipients[1]['type'] ?? null) === 'bcc' &&
        $response instanceof SendBulkEmailsResponse &&
        $response->data->batchId === 'batch_123';
    $ok ? pass('Bulk email contract') : fail('Bulk email contract', json_encode($call) ?: 'call mismatch');
} catch (\Throwable $e) {
    fail('Bulk email contract', $e->getMessage());
}

try {
    assert($client instanceof LabEmailClient);
    $client->queueResponses([[]]);
    $client->sendEmail(new SendEmailRequest(
        templateKey: 'welcome',
        data: [],
        recipient: new SendEmailRecipient(email: 'not-an-email', type: 'reply-to'),
    ));
    fail('Validation rejects invalid single recipient', 'Expected validation error');
} catch (HuefyException $e) {
    $message = strtolower($e->getMessage());
    (str_contains($message, 'invalid email') || str_contains($message, 'recipient type'))
        ? pass('Validation rejects invalid single recipient')
        : fail('Validation rejects invalid single recipient', $e->getMessage());
} catch (\Throwable $e) {
    fail('Validation rejects invalid single recipient', $e->getMessage());
}

try {
    assert($client instanceof LabEmailClient);
    $client->queueResponses([[]]);
    $client->sendBulkEmails(new SendBulkEmailsRequest(
        templateKey: 'digest',
        recipients: [],
    ));
    fail('Validation rejects invalid bulk request', 'Expected validation error');
} catch (HuefyException $e) {
    str_contains(strtolower($e->getMessage()), 'at least one email')
        ? pass('Validation rejects invalid bulk request')
        : fail('Validation rejects invalid bulk request', $e->getMessage());
} catch (\Throwable $e) {
    fail('Validation rejects invalid bulk request', $e->getMessage());
}

try {
    assert($client instanceof LabEmailClient);
    $client->queueResponses([
        [
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'timestamp' => '2026-05-07T10:00:00Z',
                'version' => '1.0.0',
            ],
            'correlationId' => 'corr_health_123',
        ],
    ]);
    $response = $client->emailHealthCheck();
    $call = $client->calls[0] ?? null;
    $ok =
        $call !== null &&
        $call['method'] === 'GET' &&
        $call['path'] === '/health' &&
        $response instanceof HealthResponse &&
        $response->status === 'healthy';
    $ok ? pass('Health check path') : fail('Health check path', json_encode($call) ?: 'call mismatch');
} catch (\Throwable $e) {
    fail('Health check path', $e->getMessage());
}

try {
    $client?->close();
    pass('Cleanup');
} catch (\Throwable $e) {
    fail('Cleanup', $e->getMessage());
}

echo "\n";
echo "========================================\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo "========================================\n\n";

if ($failed === 0) {
    echo "All verifications passed!\n";
    exit(0);
}
exit(1);
