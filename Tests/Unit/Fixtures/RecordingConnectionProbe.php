<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Unit\Fixtures;

use Brix\CelumFal\Service\ConnectionProbeInterface;
use Brix\CelumFal\Service\ValidationResult;

/**
 * Stands in for the real CELUM request and records how it was called.
 */
final class RecordingConnectionProbe implements ConnectionProbeInterface
{
    public int $callCount = 0;

    /** @var array<string, string>|null */
    public ?array $lastConfiguration = null;

    public ?int $lastStorageUid = null;

    public ?ValidationResult $result = null;

    public function probe(array $configuration, int $storageUid): ?ValidationResult
    {
        $this->callCount++;
        $this->lastConfiguration = $configuration;
        $this->lastStorageUid = $storageUid;

        return $this->result;
    }
}
