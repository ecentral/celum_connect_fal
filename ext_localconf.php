<?php
defined('TYPO3') || die('Access denied.');
use TYPO3\CMS\Core\Information\Typo3Version;

if ((new Typo3Version())->getMajorVersion() > 12) {
    // Driver
    /** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $driverRegistry */
    $driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
    $driverRegistry->registerDriverClass(
        \Brix\CelumFal\Driver\CelumDriver::class,
        \Brix\CelumFal\Driver\CelumDriver::DRIVER_TYPE,
        'celum:connect (FAL)',
        'FILE:EXT:' . \Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY . '/Configuration/FlexForm/CelumDriverFlexForm.xml'
    );
} else {
    /** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $driverRegistry */
    $driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
    $driverRegistry->registerDriverClass(
        \Brix\CelumFal\Driver\CelumDriverV12::class,
        \Brix\CelumFal\Driver\CelumDriverV12::DRIVER_TYPE,
        'celum:connect (FAL)',
        'FILE:EXT:' . \Brix\CelumFal\Driver\CelumDriverV12::EXTENSION_KEY . '/Configuration/FlexForm/CelumDriverFlexForm.xml'
    );
}

// Extractor
$extractorRegistry = new \TYPO3\CMS\Core\Resource\Index\ExtractorRegistry();
$extractorRegistry->registerExtractionService(\Brix\CelumFal\Index\Extractor::class);

// Caching
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY] = [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'groups' => ['system', 'all'],
        'options' => [
            'defaultLifetime' => 29 * 60
        ]
    ];

// Processor
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['CelumImageProcessor'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['CelumImageProcessor'] = [
        'className' => \Brix\CelumFal\Processor\CelumImageProcessor::class,
        'before' => ['LocalImageProcessor'],
    ];
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \Brix\CelumFal\Hooks\ProcessDatamapHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Brix\CelumFal\Hooks\ProcessDatamapHook::class;
// Logging
//$GLOBALS['TYPO3_CONF_VARS']['LOG']['Brix']['CelumFal']['writerConfiguration'] = [\TYPO3\CMS\Core\Log\LogLevel::DEBUG => [\TYPO3\CMS\Core\Log\Writer\FileWriter::class => []]];
