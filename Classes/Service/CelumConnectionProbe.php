<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Service;

use Brix\CelumFal\Client\CelumClient;
use Celum\Client\ApiException;
use Throwable;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Asks CELUM whether the configured API key is accepted.
 *
 * Only an unambiguous rejection counts as an error - anything else may just be
 * a network hiccup and must not brand a correct configuration as broken.
 */
final class CelumConnectionProbe implements ConnectionProbeInterface
{
    /**
     * HTTP statuses that mean "your key is not accepted" rather than
     * "the service had a bad moment".
     */
    private const REJECTING_STATUSES = [401, 403];

    /**
     * CELUM error bodies are echoed into the exception message; keep the flash
     * message readable.
     */
    private const MAX_REASON_LENGTH = 200;

    public function probe(array $configuration, int $storageUid): ?ValidationResult
    {
        $client = new CelumClient($configuration, $storageUid);
        if (!$client->isAvailable()) {
            return new ValidationResult(
                ContextualFeedbackSeverity::ERROR,
                'storageCheck.clientUnavailable'
            );
        }

        try {
            $client->verifyConnection();
        } catch (ApiException $exception) {
            $rejected = in_array($exception->getCode(), self::REJECTING_STATUSES, true);

            return new ValidationResult(
                $rejected ? ContextualFeedbackSeverity::ERROR : ContextualFeedbackSeverity::WARNING,
                $rejected ? 'storageCheck.apiKeyRejected' : 'storageCheck.apiUnreachable',
                [$this->shorten($exception->getMessage())]
            );
        } catch (Throwable $throwable) {
            // The probe runs while a record is being saved: nothing it hits may
            // escape into the DataHandler and turn a save into an error page.
            return new ValidationResult(
                ContextualFeedbackSeverity::WARNING,
                'storageCheck.apiUnreachable',
                [$this->shorten($throwable->getMessage())]
            );
        }

        return null;
    }

    private function shorten(string $reason): string
    {
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? $reason);
        if (mb_strlen($reason) <= self::MAX_REASON_LENGTH) {
            return $reason;
        }

        return mb_substr($reason, 0, self::MAX_REASON_LENGTH) . '...';
    }
}
