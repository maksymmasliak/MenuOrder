<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TableSigner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TableSignerTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';
    private const string OTHER_SECRET = 'fedcba9876543210fedcba9876543210';

    public function testSignatureMatchesKnownValue(): void
    {
        // Printed QR codes depend on this exact value: changing the message format
        // or the algorithm silently invalidates every code on every table.
        self::assertSame('3244e538bb9f7838', new TableSigner(self::SECRET)->sign(7));
    }

    public function testSignatureIsSixteenLowercaseHexChars(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', new TableSigner(self::SECRET)->sign(7));
    }

    public function testValidSignaturePasses(): void
    {
        $signer = new TableSigner(self::SECRET);

        self::assertTrue($signer->verify(7, $signer->sign(7)));
    }

    public function testSignatureOfAnotherTableFails(): void
    {
        $signer = new TableSigner(self::SECRET);

        self::assertFalse($signer->verify(8, $signer->sign(7)));
    }

    public function testSignatureWithOneChangedCharacterFails(): void
    {
        $signer = new TableSigner(self::SECRET);
        $signature = $signer->sign(7);

        $tampered = $signature;
        $tampered[15] = $signature[15] === 'a' ? 'b' : 'a';

        self::assertFalse($signer->verify(7, $tampered));
    }

    public function testUppercaseSignatureFails(): void
    {
        $signer = new TableSigner(self::SECRET);

        self::assertFalse($signer->verify(7, strtoupper($signer->sign(7))));
    }

    #[DataProvider('malformedSignatures')]
    public function testMalformedSignatureFails(string $signature): void
    {
        self::assertFalse(new TableSigner(self::SECRET)->verify(7, $signature));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedSignatures(): iterable
    {
        yield 'empty string' => [''];
        yield 'full 64-char HMAC' => [hash_hmac('sha256', 'table:7', self::SECRET)];
        yield '15-char prefix' => ['3244e538bb9f783'];
    }

    public function testDifferentSecretGivesDifferentSignature(): void
    {
        $signer = new TableSigner(self::SECRET);
        $otherSigner = new TableSigner(self::OTHER_SECRET);

        self::assertNotSame($signer->sign(7), $otherSigner->sign(7));
        self::assertFalse($signer->verify(7, $otherSigner->sign(7)));
    }

    public function testRejectsTooShortSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TableSigner(str_repeat('a', 31));
    }

    public function testAcceptsSecretOfMinimumLength(): void
    {
        $this->expectNotToPerformAssertions();

        new TableSigner(str_repeat('a', 32));
    }
}
