<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Tests;

use Teracrafts\Huefy\Models\SendEmailRecipient;
use PHPUnit\Framework\TestCase;
use Teracrafts\Huefy\Validators\EmailValidators;

class EmailValidatorsTest extends TestCase
{
    // --- validateEmail ---

    public function testValidEmailReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateEmail('user@example.com'));
    }

    public function testEmptyEmailReturnsError(): void
    {
        $result = EmailValidators::validateEmail('');
        $this->assertNotNull($result);
        $this->assertStringContainsString('required', $result);
    }

    public function testInvalidEmailReturnsError(): void
    {
        $result = EmailValidators::validateEmail('not-an-email');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Invalid email', $result);
    }

    public function testEmailWithoutDomainReturnsError(): void
    {
        $result = EmailValidators::validateEmail('user@');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Invalid email', $result);
    }

    public function testOverlyLongEmailReturnsError(): void
    {
        $longEmail = str_repeat('a', 250) . '@b.co';
        $result = EmailValidators::validateEmail($longEmail);
        $this->assertNotNull($result);
        $this->assertStringContainsString('maximum length', $result);
    }

    public function testEmailWithWhitespaceIsTrimmed(): void
    {
        $this->assertNull(EmailValidators::validateEmail('  user@example.com  '));
    }

    // --- validateTemplateKey ---

    public function testValidTemplateKeyReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateTemplateKey('welcome-email'));
    }

    public function testEmptyTemplateKeyReturnsError(): void
    {
        $result = EmailValidators::validateTemplateKey('');
        $this->assertNotNull($result);
        $this->assertStringContainsString('required', $result);
    }

    public function testWhitespaceOnlyTemplateKeyReturnsError(): void
    {
        $result = EmailValidators::validateTemplateKey('   ');
        $this->assertNotNull($result);
        $this->assertStringContainsString('empty', $result);
    }

    public function testOverlyLongTemplateKeyReturnsError(): void
    {
        $longKey = str_repeat('a', 101);
        $result = EmailValidators::validateTemplateKey($longKey);
        $this->assertNotNull($result);
        $this->assertStringContainsString('maximum length', $result);
    }

    // --- validateEmailData ---

    public function testValidEmailDataReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateEmailData(['name' => 'John']));
    }

    public function testEmptyEmailDataReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateEmailData([]));
    }

    public function testNonStringValueReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateEmailData(['count' => 5]));
    }

    public function testNestedArrayValueReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateEmailData(['items' => ['a', 'b']]));
    }

    // --- validateBulkCount ---

    public function testValidBulkCountReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateBulkCount(10));
    }

    public function testZeroBulkCountReturnsError(): void
    {
        $result = EmailValidators::validateBulkCount(0);
        $this->assertNotNull($result);
        $this->assertStringContainsString('At least one', $result);
    }

    public function testNegativeBulkCountReturnsError(): void
    {
        $result = EmailValidators::validateBulkCount(-1);
        $this->assertNotNull($result);
        $this->assertStringContainsString('At least one', $result);
    }

    public function testOverMaxBulkCountReturnsError(): void
    {
        $result = EmailValidators::validateBulkCount(1001);
        $this->assertNotNull($result);
        $this->assertStringContainsString('Maximum of 1000', $result);
    }

    public function testExactlyMaxBulkCountReturnsNull(): void
    {
        $this->assertNull(EmailValidators::validateBulkCount(1000));
    }

    // --- validateSendEmailInput ---

    public function testValidInputReturnsEmptyArray(): void
    {
        $errors = EmailValidators::validateSendEmailInput('tpl', ['name' => 'John'], 'user@test.com');
        $this->assertEmpty($errors);
    }

    public function testRecipientObjectInputReturnsEmptyArray(): void
    {
        $errors = EmailValidators::validateSendEmailInput(
            'tpl',
            ['name' => 'John'],
            new SendEmailRecipient(email: 'user@test.com', type: 'bcc', data: ['locale' => 'en']),
        );
        $this->assertEmpty($errors);
    }

    public function testMultipleInvalidInputsReturnMultipleErrors(): void
    {
        $errors = EmailValidators::validateSendEmailInput('', ['count' => 5], 'bad');
        $this->assertGreaterThan(1, count($errors));
    }

    public function testSingleInvalidFieldReturnsSingleError(): void
    {
        $errors = EmailValidators::validateSendEmailInput('tpl', ['name' => 'John'], 'bad');
        $this->assertCount(1, $errors);
    }

    public function testInvalidRecipientObjectReturnsSingleError(): void
    {
        $errors = EmailValidators::validateSendEmailInput(
            'tpl',
            ['name' => 'John'],
            new SendEmailRecipient(email: 'bad'),
        );
        $this->assertCount(1, $errors);
    }

    public function testInvalidRecipientObjectTypeReturnsSingleError(): void
    {
        $errors = EmailValidators::validateSendEmailInput(
            'tpl',
            ['name' => 'John'],
            new SendEmailRecipient(email: 'user@test.com', type: 'reply-to'),
        );
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Recipient type', $errors[0]);
    }
}
