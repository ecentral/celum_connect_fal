<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Brix\CelumFal\Driver\CelumDriver;
use Brix\CelumFal\Index\Extractor;
use Brix\CelumFal\Processor\CelumImageProcessor;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Resource\Index\ExtractorRegistry;

defined('TYPO3') || die('Access denied.');
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Driver
$driverClass = (new Typo3Version())->getMajorVersion() < 13
    ? 'Brix\\CelumFal\\Driver\\CelumDriverV12'
    : CelumDriver::class;

$driverRegistry = GeneralUtility::makeInstance(DriverRegistry::class);
$driverRegistry->registerDriverClass(
    $driverClass,
    CelumDriver::DRIVER_TYPE,
    'celum:connect (FAL)',
    'FILE:EXT:' . CelumDriver::EXTENSION_KEY . '/Configuration/FlexForm/CelumDriverFlexForm.xml'
);

// Caching
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][CelumDriver::EXTENSION_KEY] = [
    'frontend' => VariableFrontend::class,
    'groups' => ['system', 'all'],
    'options' => [
        'defaultLifetime' => 29 * 60
    ]
];

// Extractor
$extractorRegistry = new ExtractorRegistry();
$extractorRegistry->registerExtractionService(Extractor::class);

// Processor
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['CelumImageProcessor'] ??= [
    'className' => CelumImageProcessor::class,
    'before' => ['LocalImageProcessor'],
];

// Logging
//$GLOBALS['TYPO3_CONF_VARS']['LOG']['Brix']['CelumFal']['writerConfiguration'] = [\TYPO3\CMS\Core\Log\LogLevel::DEBUG => [\TYPO3\CMS\Core\Log\Writer\FileWriter::class => []]];
