<?php
declare(strict_types=1);

use Brix\CelumFal\Utility\Cache;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

ExtensionUtility::configurePlugin(
    'CelumFal',
    'ClearCache',
    [Cache::class => 'clearCache'],
);
