<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Service;

use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * One finding about a storage configuration.
 *
 * Carries a label key rather than a finished sentence so the caller decides
 * how to render it - a flash message today, a status report tomorrow.
 */
final class ValidationResult
{
    /**
     * @param string[] $arguments Values for the placeholders in the label
     */
    public function __construct(
        public readonly ContextualFeedbackSeverity $severity,
        public readonly string $messageKey,
        public readonly array $arguments = []
    ) {
    }
}
