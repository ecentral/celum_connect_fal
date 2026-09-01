<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

defined('TYPO3') || die('Access denied.');
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Driver
$driverClass = (new Typo3Version())->getMajorVersion() < 13
    ? 'Brix\\CelumFal\\Driver\\CelumDriverV12'
    : \Brix\CelumFal\Driver\CelumDriver::class;

$driverRegistry = GeneralUtility::makeInstance(DriverRegistry::class);
$driverRegistry->registerDriverClass(
    $driverClass,
    \Brix\CelumFal\Driver\CelumDriver::DRIVER_TYPE,
    'celum:connect (FAL)',
    'FILE:EXT:' . \Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY . '/Configuration/FlexForm/CelumDriverFlexForm.xml'
);

// Storage configuration check: warn when license or API key do not work
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
    = \Brix\CelumFal\Hooks\StorageConfigurationCheck::class;

// Caching
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY] = [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'groups' => ['system', 'all'],
    'options' => [
        'defaultLifetime' => 29 * 60
    ]
];

// Extractor
$extractorRegistry = new \TYPO3\CMS\Core\Resource\Index\ExtractorRegistry();
$extractorRegistry->registerExtractionService(\Brix\CelumFal\Index\Extractor::class);

// Processor
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['CelumImageProcessor'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['CelumImageProcessor'] = [
        'className' => \Brix\CelumFal\Processor\CelumImageProcessor::class,
        'before' => ['LocalImageProcessor'],
    ];
}

// Logging
//$GLOBALS['TYPO3_CONF_VARS']['LOG']['Brix']['CelumFal']['writerConfiguration'] = [\TYPO3\CMS\Core\Log\LogLevel::DEBUG => [\TYPO3\CMS\Core\Log\Writer\FileWriter::class => []]];
