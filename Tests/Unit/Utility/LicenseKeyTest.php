<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Unit\Utility;

use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use Brix\CelumFal\Tests\Unit\Fixtures\LicenseKeyFixture;
use Brix\CelumFal\Utility\LicenseKey;
use DateTimeImmutable;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class LicenseKeyTest extends UnitTestCase
{
    /**
     * @test
     */
    public function readsHostAndExpiryDateFromEncodedKey(): void
    {
        $expiresAt = new DateTimeImmutable('2027-01-31 12:00:00');

        $licenseKey = LicenseKey::fromEncoded(
            LicenseKeyFixture::encode('https://demo.celum.cloud', $expiresAt->getTimestamp())
        );

        self::assertSame('https://demo.celum.cloud', $licenseKey->getHost());
        self::assertSame($expiresAt->getTimestamp(), $licenseKey->getExpiresAt()->getTimestamp());
    }

    /**
     * @test
     */
    public function rejectsEmptyKeyWithDedicatedReason(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(LicenseKey::REASON_EMPTY);

        LicenseKey::fromEncoded('');
    }

    /**
     * @test
     */
    public function rejectsKeyThatIsNotValidBase64(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(LicenseKey::REASON_UNDECODABLE);

        LicenseKey::fromEncoded('!!! not base64 !!!');
    }

    /**
     * A key that decodes cleanly but does not carry the "<host>_<timestamp>"
     * payload - typically a key issued for a different product.
     *
     * @test
     */
    public function rejectsKeyWithoutHostAndTimestampPayload(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(LicenseKey::REASON_MALFORMED);

        LicenseKey::fromEncoded(LicenseKeyFixture::encrypt('no-separator-here'));
    }

    /**
     * @test
     */
    public function rejectsKeyWhoseExpiryIsNotATimestamp(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionCode(LicenseKey::REASON_MALFORMED);

        LicenseKey::fromEncoded(LicenseKeyFixture::encrypt('https://demo.celum.cloud_whenever'));
    }

    /**
     * @test
     */
    public function reportsExpiryRelativeToGivenPointInTime(): void
    {
        $licenseKey = LicenseKey::fromEncoded(
            LicenseKeyFixture::encode('https://demo.celum.cloud', (new DateTimeImmutable('2027-01-31'))->getTimestamp())
        );

        self::assertTrue($licenseKey->isExpired(new DateTimeImmutable('2027-02-01')));
        self::assertFalse($licenseKey->isExpired(new DateTimeImmutable('2027-01-30')));
    }

    /**
     * @test
     */
    public function reportsUpcomingExpiryWithinGivenNumberOfDays(): void
    {
        $licenseKey = LicenseKey::fromEncoded(
            LicenseKeyFixture::encode('https://demo.celum.cloud', (new DateTimeImmutable('2027-01-31'))->getTimestamp())
        );

        self::assertTrue($licenseKey->expiresWithinDays(30, new DateTimeImmutable('2027-01-15')));
        self::assertFalse($licenseKey->expiresWithinDays(30, new DateTimeImmutable('2026-12-01')));
    }

    /**
     * An already expired key must not additionally be reported as "expiring
     * soon" - the caller shows one message, not two.
     *
     * @test
     */
    public function doesNotReportExpiredKeyAsExpiringSoon(): void
    {
        $licenseKey = LicenseKey::fromEncoded(
            LicenseKeyFixture::encode('https://demo.celum.cloud', (new DateTimeImmutable('2027-01-31'))->getTimestamp())
        );

        self::assertFalse($licenseKey->expiresWithinDays(30, new DateTimeImmutable('2027-02-05')));
    }
}
