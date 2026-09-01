<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Service;

use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use Brix\CelumFal\Utility\LicenseKey;
use DateTimeImmutable;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Checks a CELUM storage configuration and reports what is wrong with it.
 *
 * Deliberately free of TYPO3 UI concerns: it returns findings, it does not
 * render them.
 */
final class StorageConfigurationValidator
{
    /**
     * How far ahead an upcoming license expiry is worth a warning.
     */
    private const EXPIRY_WARNING_DAYS = 30;

    private const LICENSE_MESSAGE_KEYS = [
        LicenseKey::REASON_EMPTY => 'storageCheck.licenseEmpty',
        LicenseKey::REASON_UNDECODABLE => 'storageCheck.licenseUndecodable',
        LicenseKey::REASON_MALFORMED => 'storageCheck.licenseMalformed',
    ];

    public function __construct(
        private readonly ConnectionProbeInterface $probe
    ) {
    }

    /**
     * @param array<string, string> $configuration Driver configuration of the storage
     * @return ValidationResult[]
     */
    public function validate(array $configuration, int $storageUid, DateTimeImmutable $now): array
    {
        try {
            $licenseKey = LicenseKey::fromEncoded((string)($configuration['licenseKey'] ?? ''));
        } catch (InvalidConfigurationException $exception) {
            return [new ValidationResult(
                ContextualFeedbackSeverity::ERROR,
                self::LICENSE_MESSAGE_KEYS[$exception->getCode()] ?? 'storageCheck.licenseMalformed'
            )];
        }

        // Without a usable license there is no host and no point in asking CELUM;
        // a request would only add a misleading timeout on top.
        if ($licenseKey->isExpired($now)) {
            return [new ValidationResult(
                ContextualFeedbackSeverity::ERROR,
                'storageCheck.licenseExpired',
                [$this->formatDate($licenseKey->getExpiresAt())]
            )];
        }

        $results = [];

        if ($licenseKey->expiresWithinDays(self::EXPIRY_WARNING_DAYS, $now)) {
            $results[] = new ValidationResult(
                ContextualFeedbackSeverity::WARNING,
                'storageCheck.licenseExpiresSoon',
                [$this->formatDate($licenseKey->getExpiresAt())]
            );
        }

        if (trim((string)($configuration['roots'] ?? '')) === '') {
            $results[] = new ValidationResult(
                ContextualFeedbackSeverity::WARNING,
                'storageCheck.noRoots'
            );
        }

        $probeResult = $this->probe->probe($configuration, $storageUid);
        if ($probeResult !== null) {
            $results[] = $probeResult;

            return $results;
        }

        if ($results === []) {
            $results[] = new ValidationResult(
                ContextualFeedbackSeverity::OK,
                'storageCheck.ok',
                [$licenseKey->getHost()]
            );
        }

        return $results;
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d');
    }
}
