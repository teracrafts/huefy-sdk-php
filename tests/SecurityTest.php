<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Tests;

use PHPUnit\Framework\TestCase;
use Teracrafts\Huefy\Security\Security;

class SecurityTest extends TestCase
{
    public function testDetectPiiFindsEmailAddresses(): void
    {
        $result = Security::detectPii('Contact us at user@example.com for details');
        $this->assertContains('email', $result);
    }

    public function testDetectPiiFindsPhoneNumbers(): void
    {
        $result = Security::detectPii('Call us at +14155551234');
        $this->assertContains('phone', $result);
    }

    public function testDetectPiiFindsSsn(): void
    {
        $result = Security::detectPii('SSN is 123-45-6789');
        $this->assertContains('ssn', $result);
    }

    public function testDetectPiiFindsCreditCardNumbers(): void
    {
        $result = Security::detectPii('Card: 4111 1111 1111 1111');
        $this->assertContains('credit_card', $result);
    }

    public function testDetectPiiReturnsEmptyListForCleanText(): void
    {
        $result = Security::detectPii('This is a normal message with no PII');
        $this->assertEmpty($result);
    }

    public function testDetectPiiFindsMultipleTypes(): void
    {
        $result = Security::detectPii('Email: a@b.com, SSN: 111-22-3333');
        $this->assertContains('email', $result);
        $this->assertContains('ssn', $result);
    }

    public function testHmacSignProducesConsistentSignatures(): void
    {
        $sig1 = Security::hmacSign('payload', 'secret');
        $sig2 = Security::hmacSign('payload', 'secret');
        $this->assertSame($sig1, $sig2);
    }

    public function testHmacSignProducesDifferentSignaturesForDifferentPayloads(): void
    {
        $sig1 = Security::hmacSign('payload1', 'secret');
        $sig2 = Security::hmacSign('payload2', 'secret');
        $this->assertNotSame($sig1, $sig2);
    }

    public function testHmacSignProducesDifferentSignaturesForDifferentSecrets(): void
    {
        $sig1 = Security::hmacSign('payload', 'secret1');
        $sig2 = Security::hmacSign('payload', 'secret2');
        $this->assertNotSame($sig1, $sig2);
    }

    public function testHmacVerifyReturnsTrueForValidSignature(): void
    {
        $signature = Security::hmacSign('payload', 'secret');
        $this->assertTrue(Security::hmacVerify('payload', $signature, 'secret'));
    }

    public function testHmacVerifyReturnsFalseForInvalidSignature(): void
    {
        $this->assertFalse(Security::hmacVerify('payload', 'invalidsig', 'secret'));
    }

    public function testHmacVerifyReturnsFalseForTamperedPayload(): void
    {
        $signature = Security::hmacSign('original', 'secret');
        $this->assertFalse(Security::hmacVerify('tampered', $signature, 'secret'));
    }

    public function testMaskApiKeyMasksMiddleCharacters(): void
    {
        $masked = Security::maskApiKey('abcd1234efgh5678ijkl');
        $this->assertSame('abcd************ijkl', $masked);
    }

    public function testMaskApiKeyReturnsStarsForShortKeys(): void
    {
        $this->assertSame('****', Security::maskApiKey('short'));
    }

    public function testMaskApiKeyHandlesExactly8CharacterKey(): void
    {
        $this->assertSame('****', Security::maskApiKey('12345678'));
    }

    public function testValidateApiKeyFormatAcceptsValidKeys(): void
    {
        $this->assertTrue(Security::validateApiKeyFormat('abcdefghijklmnop'));
        $this->assertTrue(Security::validateApiKeyFormat('abc-def_ghi.jklmnop'));
    }

    public function testValidateApiKeyFormatRejectsShortKeys(): void
    {
        $this->assertFalse(Security::validateApiKeyFormat('short'));
    }

    public function testValidateApiKeyFormatRejectsBlankKeys(): void
    {
        $this->assertFalse(Security::validateApiKeyFormat(''));
        $this->assertFalse(Security::validateApiKeyFormat('   '));
    }

    public function testGenerateNonceProducesUniqueValues(): void
    {
        $nonce1 = Security::generateNonce();
        $nonce2 = Security::generateNonce();
        $this->assertNotSame($nonce1, $nonce2);
    }

    public function testGenerateNonceProducesNonEmptyString(): void
    {
        $nonce = Security::generateNonce();
        $this->assertNotEmpty($nonce);
    }
}
