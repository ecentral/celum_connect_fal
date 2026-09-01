<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Service;

/**
 * Verifies that the configured credentials actually reach CELUM.
 */
interface ConnectionProbeInterface
{
    /**
     * @param array<string, string> $configuration Driver configuration of the storage
     * @return ValidationResult|null NULL when the connection succeeded
     */
    public function probe(array $configuration, int $storageUid): ?ValidationResult;
}
