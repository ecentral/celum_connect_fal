<?php
defined('TYPO3_MODE') or die('Access denied.');

// Driver
/** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $driverRegistry */
$driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
$driverRegistry->registerDriverClass(
    \Brix\CelumFal\Driver\CelumDriver::class,
    \Brix\CelumFal\Driver\CelumDriver::DRIVER_TYPE,
    'celum:connect (FAL)',
    'FILE:EXT:' . \Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY . '/Configuration/FlexForm/CelumDriverFlexForm.xml'
);

// Extractor
\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::getInstance()->registerExtractionService(\Brix\CelumFal\Index\Extractor::class);

// Caching
$GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY] = [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'options' => [
            'defaultLifetime' => \Brix\CelumFal\Client\CelumClient::LIFE_TIME
        ]
    ];

// Processor
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['CelumImageProcessor'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['CelumImageProcessor'] = [
        'className' => \Brix\CelumFal\Processor\CelumImageProcessor::class,
        'before' => ['LocalImageProcessor'],
    ];
}

// Logging
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Brix']['CelumFal']['writerConfiguration'] = [\TYPO3\CMS\Core\Log\LogLevel::DEBUG => [\TYPO3\CMS\Core\Log\Writer\FileWriter::class => []]];
