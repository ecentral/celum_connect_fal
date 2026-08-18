<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Utility;

use Brix\CelumFal\Client\CelumClient;
use Brix\CelumFal\Driver\CelumDriver;
use TYPO3\CMS\Core\Information\Typo3Version;

class DriverUtility
{
    public static function getDriver(): string
    {
        return (new Typo3Version())->getMajorVersion() < 13
            ? 'Brix\\CelumFal\\Driver\\CelumDriverV12'
            : CelumDriver::class;
    }

    public static function getClient(): CelumClient
    {
        $driverClass = self::getDriver();

        return $driverClass::$client;
    }
}
