<?php

namespace Brix\CelumFal\Utility;

use Brix\CelumFal\Client\CelumClient;
use TYPO3\CMS\Core\Information\Typo3Version;

class DriverUtility
{
    public static function getDriver(): string
    {
        return (new Typo3Version())->getMajorVersion() < 13
            ? \Brix\CelumFal\Driver\CelumDriverV12::class
            : \Brix\CelumFal\Driver\CelumDriver::class;
    }

    public static function getClient(): CelumClient
    {
        return (new Typo3Version())->getMajorVersion() < 13
            ? \Brix\CelumFal\Driver\CelumDriverV12::$client
            : \Brix\CelumFal\Driver\CelumDriver::$client;
    }
}
