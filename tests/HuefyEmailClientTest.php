<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Tests;

use Teracrafts\Huefy\Errors\HuefyException;
use Teracrafts\Huefy\HuefyEmailClient;
use Teracrafts\Huefy\Models\BulkRecipient;
use Teracrafts\Huefy\Models\SendBulkEmailsRequest;
use Teracrafts\Huefy\Models\SendEmailRequest;
use Teracrafts\Huefy\Models\SendEmailRecipient;
use PHPUnit\Framework\TestCase;

class HuefyEmailClientTest extends TestCase
{
    public function testLegacyNamespaceAliasesRemainAvailable(): void
    {
        self::assertTrue(class_exists(\Huefy\HuefyEmailClient::class));
        self::assertTrue(class_exists(\Huefy\HuefyConfig::class));
        self::assertTrue(class_exists(\Huefy\RateLimitInfo::class));
        self::assertTrue(class_exists(\Huefy\Models\EmailProvider::class));
    }

    // --- sendEmail validation ---

    public function testSendEmailThrowsOnEmptyTemplateKey(): void
    {
        $client = $this->makeClient();

        $this->expectException(HuefyException::class);

        $client->sendEmail(new SendEmailRequest(
            templateKey: '',
            data: ['name' => 'John'],
            recipient: 'john@example.com',
        ));
    }

    public function testSendEmailThrowsOnInvalidRecipient(): void
    {
        $client = $this->makeClient();

        $this->expectException(HuefyException::class);
        $this->expectExceptionMessageMatches('/Invalid email/');

        $client->sendEmail(new SendEmailRequest(
            templateKey: 'welcome',
            data: ['name' => 'John'],
            recipient: 'not-an-email',
        ));
    }

    public function testSendEmailThrowsOnInvalidProvider(): void
    {
        $client = $this->makeClient();

        $this->expectException(HuefyException::class);
        $this->expectExceptionMessageMatches('/Invalid provider/');

        $client->sendEmail(new SendEmailRequest(
            templateKey: 'welcome',
            data: ['name' => 'John'],
            recipient: 'john@example.com',
            provider: 'invalid-provider',
        ));
    }

    public function testSendEmailSucceeds(): void
    {
        $responseData = [
            'success' => true,
            'correlationId' => 'corr-123',
            'data' => [
                'emailId' => 'email-abc',
                'status' => 'sent',
                'recipients' => [
                    ['email' => 'john@example.com', 'status' => 'sent'],
                ],
            ],
        ];

        $client = $this->makeClientWithResponse($responseData);

        $response = $client->sendEmail(new SendEmailRequest(
            templateKey: 'welcome',
            data: ['name' => 'John'],
            recipient: 'john@example.com',
        ));

        $this->assertTrue($response->success);
        $this->assertSame('email-abc', $response->data->emailId);
        $this->assertSame('sent', $response->data->status);
        $this->assertCount(1, $response->data->recipients);
        $this->assertSame('john@example.com', $response->data->recipients[0]->email);
    }

    public function testSendEmailAcceptsRecipientObject(): void
    {
        $responseData = [
            'success' => true,
            'correlationId' => 'corr-123',
            'data' => [
                'emailId' => 'email-abc',
                'status' => 'sent',
                'recipients' => [
                    ['email' => 'john@example.com', 'status' => 'sent'],
                ],
            ],
        ];

        $client = $this->makeClientWithResponse($responseData);

        $response = $client->sendEmail(new SendEmailRequest(
            templateKey: 'welcome',
            data: ['name' => 'John'],
            recipient: new SendEmailRecipient(
                email: 'john@example.com',
                type: 'cc',
                data: ['locale' => 'en'],
            ),
        ));

        $this->assertTrue($response->success);
        $this->assertSame('john@example.com', $response->data->recipients[0]->email);
    }

    // --- sendBulkEmails validation ---

    public function testSendBulkEmailsThrowsOnEmptyRecipients(): void
    {
        $client = $this->makeClient();

        $this->expectException(HuefyException::class);

        $client->sendBulkEmails(new SendBulkEmailsRequest(
            templateKey: 'welcome',
            recipients: [],
        ));
    }

    public function testSendBulkEmailsThrowsOnInvalidRecipientEmail(): void
    {
        $client = $this->makeClient();

        $this->expectException(HuefyException::class);
        $this->expectExceptionMessageMatches('/recipients\[0\]/');

        $client->sendBulkEmails(new SendBulkEmailsRequest(
            templateKey: 'welcome',
            recipients: [new BulkRecipient(email: 'not-an-email')],
        ));
    }

    public function testSendBulkEmailsThrowsOnBlankTemplateKey(): void
    {
        $client = $this->makeClient();

        $this->expectException(HuefyException::class);
        $this->expectExceptionMessageMatches('/Template key/');

        $client->sendBulkEmails(new SendBulkEmailsRequest(
            templateKey: '   ',
            recipients: [new BulkRecipient(email: 'john@example.com')],
        ));
    }

    public function testSendBulkEmailsThrowsOnInvalidRecipientType(): void
    {
        $client = $this->makeClient();

        $this->expectException(HuefyException::class);
        $this->expectExceptionMessageMatches('/recipients\[0\].*Recipient type/');

        $client->sendBulkEmails(new SendBulkEmailsRequest(
            templateKey: 'welcome',
            recipients: [new BulkRecipient(email: 'john@example.com', type: 'reply-to')],
        ));
    }

    public function testSendBulkEmailsSucceeds(): void
    {
        $responseData = [
            'success' => true,
            'correlationId' => 'corr-456',
            'data' => [
                'batchId' => 'batch-xyz',
                'status' => 'completed',
                'templateKey' => 'welcome',
                'totalRecipients' => 2,
                'successCount' => 2,
                'failureCount' => 0,
                'suppressedCount' => 0,
                'startedAt' => '2026-04-24T20:00:00Z',
                'recipients' => [
                    ['email' => 'alice@example.com', 'status' => 'sent'],
                    ['email' => 'bob@example.com', 'status' => 'sent'],
                ],
            ],
        ];

        $client = $this->makeClientWithResponse($responseData);

        $response = $client->sendBulkEmails(new SendBulkEmailsRequest(
            templateKey: 'welcome',
            recipients: [
                new BulkRecipient(email: 'alice@example.com', data: ['name' => 'Alice']),
                new BulkRecipient(email: 'bob@example.com', data: ['name' => 'Bob']),
            ],
        ));

        $this->assertTrue($response->success);
        $this->assertSame('batch-xyz', $response->data->batchId);
        $this->assertSame(2, $response->data->totalRecipients);
        $this->assertSame(2, $response->data->successCount);
        $this->assertCount(2, $response->data->recipients);
    }

    // --- helpers ---

    private function makeClient(): HuefyEmailClient
    {
        return $this->getMockBuilder(HuefyEmailClient::class)
            ->setConstructorArgs([['apiKey' => 'sdk_test_key']])
            ->onlyMethods(['request'])
            ->getMock();
    }

    private function makeClientWithResponse(array $responseData): HuefyEmailClient
    {
        $client = $this->getMockBuilder(HuefyEmailClient::class)
            ->setConstructorArgs([['apiKey' => 'sdk_test_key']])
            ->onlyMethods(['request'])
            ->getMock();

        $client->method('request')->willReturn($responseData);

        return $client;
    }
}
