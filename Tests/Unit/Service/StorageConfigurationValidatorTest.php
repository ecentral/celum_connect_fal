<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Unit\Service;

use Brix\CelumFal\Service\StorageConfigurationValidator;
use Brix\CelumFal\Service\ValidationResult;
use Brix\CelumFal\Tests\Unit\Fixtures\LicenseKeyFixture;
use Brix\CelumFal\Tests\Unit\Fixtures\RecordingConnectionProbe;
use DateTimeImmutable;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class StorageConfigurationValidatorTest extends UnitTestCase
{
    private const NOW = '2027-01-01 00:00:00';

    private RecordingConnectionProbe $probe;
    private StorageConfigurationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->probe = new RecordingConnectionProbe();
        $this->validator = new StorageConfigurationValidator($this->probe);
    }

    /**
     * @test
     */
    public function reportsMissingLicenseKeyAsError(): void
    {
        $results = $this->validate(['licenseKey' => '', 'roots' => '42']);

        self::assertSame(ContextualFeedbackSeverity::ERROR, $results[0]->severity);
        self::assertSame('storageCheck.licenseEmpty', $results[0]->messageKey);
    }

    /**
     * @test
     */
    public function reportsUnreadableLicenseKeyAsError(): void
    {
        $results = $this->validate(['licenseKey' => '!!! not base64 !!!', 'roots' => '42']);

        self::assertSame(ContextualFeedbackSeverity::ERROR, $results[0]->severity);
        self::assertSame('storageCheck.licenseUndecodable', $results[0]->messageKey);
    }

    /**
     * @test
     */
    public function reportsExpiredLicenseWithItsExpiryDate(): void
    {
        $results = $this->validate([
            'licenseKey' => $this->licenseKeyExpiringAt('2026-12-24'),
            'roots' => '42',
        ]);

        self::assertSame(ContextualFeedbackSeverity::ERROR, $results[0]->severity);
        self::assertSame('storageCheck.licenseExpired', $results[0]->messageKey);
        self::assertSame(['2026-12-24'], $results[0]->arguments);
    }

    /**
     * @test
     */
    public function warnsAboutLicenseExpiringWithinThirtyDays(): void
    {
        $results = $this->validate([
            'licenseKey' => $this->licenseKeyExpiringAt('2027-01-20'),
            'roots' => '42',
        ]);

        self::assertSame(ContextualFeedbackSeverity::WARNING, $results[0]->severity);
        self::assertSame('storageCheck.licenseExpiresSoon', $results[0]->messageKey);
        self::assertSame(['2027-01-20'], $results[0]->arguments);
    }

    /**
     * @test
     */
    public function warnsWhenNoRootCollectionsAreConfigured(): void
    {
        $results = $this->validate([
            'licenseKey' => $this->licenseKeyExpiringAt('2028-01-01'),
            'roots' => '   ',
        ]);

        self::assertSame(
            ['storageCheck.noRoots'],
            $this->messageKeysOfSeverity($results, ContextualFeedbackSeverity::WARNING)
        );
    }

    /**
     * A broken license means no host and no API key worth trying - firing a
     * request would only add a misleading timeout message.
     *
     * @test
     */
    public function doesNotProbeTheApiWhenTheLicenseIsInvalid(): void
    {
        $this->validate(['licenseKey' => '', 'roots' => '42']);

        self::assertSame(0, $this->probe->callCount);
    }

    /**
     * @test
     */
    public function probesTheApiWithTheGivenConfigurationWhenTheLicenseIsValid(): void
    {
        $configuration = [
            'licenseKey' => $this->licenseKeyExpiringAt('2028-01-01'),
            'roots' => '42',
        ];

        $this->validator->validate($configuration, 7, new DateTimeImmutable(self::NOW));

        self::assertSame(1, $this->probe->callCount);
        self::assertSame($configuration, $this->probe->lastConfiguration);
        self::assertSame(7, $this->probe->lastStorageUid);
    }

    /**
     * @test
     */
    public function passesTheProbeResultOnToTheCaller(): void
    {
        $this->probe->result = new ValidationResult(
            ContextualFeedbackSeverity::ERROR,
            'storageCheck.apiKeyRejected',
            ['401 Unauthorized']
        );

        $results = $this->validate([
            'licenseKey' => $this->licenseKeyExpiringAt('2028-01-01'),
            'roots' => '42',
        ]);

        self::assertSame('storageCheck.apiKeyRejected', $results[0]->messageKey);
        self::assertSame(['401 Unauthorized'], $results[0]->arguments);
    }

    /**
     * @test
     */
    public function confirmsAWorkingConfigurationWithTheResolvedHost(): void
    {
        $results = $this->validate([
            'licenseKey' => $this->licenseKeyExpiringAt('2028-01-01'),
            'roots' => '42',
        ]);

        self::assertCount(1, $results);
        self::assertSame(ContextualFeedbackSeverity::OK, $results[0]->severity);
        self::assertSame('storageCheck.ok', $results[0]->messageKey);
        self::assertSame(['https://demo.celum.cloud'], $results[0]->arguments);
    }

    /**
     * @param array<string, string> $configuration
     * @return ValidationResult[]
     */
    private function validate(array $configuration): array
    {
        return $this->validator->validate($configuration, 1, new DateTimeImmutable(self::NOW));
    }

    private function licenseKeyExpiringAt(string $date): string
    {
        return LicenseKeyFixture::encode(
            'https://demo.celum.cloud',
            (new DateTimeImmutable($date))->getTimestamp()
        );
    }

    /**
     * @param ValidationResult[] $results
     * @return string[]
     */
    private function messageKeysOfSeverity(array $results, ContextualFeedbackSeverity $severity): array
    {
        $keys = [];
        foreach ($results as $result) {
            if ($result->severity === $severity) {
                $keys[] = $result->messageKey;
            }
        }
        return $keys;
    }
}
