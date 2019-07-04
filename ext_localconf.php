<?php
defined('TYPO3_MODE') or die('Access denied.');

/** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $driverRegistry */
$driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
$driverRegistry->registerDriverClass(
    \Brix\CelumFal\Driver\CelumDriver::class,
    \Brix\CelumFal\Driver\CelumDriver::DRIVER_TYPE,
    'celum:connect (FAL)',
    'FILE:EXT:' . \Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY . '/Configuration/FlexForm/CelumDriverFlexForm.xml'
);

\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::getInstance()->registerExtractionService(\Brix\CelumFal\Index\Extractor::class);

// Caching framework
if( !is_array($GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY] ) ) {
    $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY] = array();
}
if( !isset($GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY]['frontend'] ) ) {
    $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY]['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
}
if( !isset($GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY]['options'] ) ) {
    $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][\Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY]['options'] = array('defaultLifetime' => \Brix\CelumFal\Client\CelumClient::LIFE_TIME);
}

/*
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Brix']['CelumFal']['writerConfiguration'] = array(
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => array(
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => array()
    )
);
*/