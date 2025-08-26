<?php
/**
 * Created by PhpStorm.
 * User: CMA
 * Date: 05/11/2018
 * Time: 12:12
 */

namespace Brix\CelumFal\Index;

use Brix\CelumFal\Driver\CelumDriver;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Extractor implements ExtractorInterface
{

    /** @var Logger */
    protected $log;

    public function __construct()
    {
        $this->log = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Returns an array of supported file types;
     * An empty array indicates all filetypes
     *
     * @return array
     */
    public function getFileTypeRestrictions()
    {
        return [];
    }

    /**
     * Get all supported DriverClasses
     *
     * Since some extractors may only work for local files, and other extractors
     * are especially made for grabbing data from remote.
     *
     * Returns array of string with driver names of Drivers which are supported,
     * If the driver did not register a name, it's the classname.
     * empty array indicates no restrictions
     *
     * @return array
     */
    public function getDriverRestrictions()
    {
        return [CelumDriver::DRIVER_TYPE];
    }

    /**
     * Returns the data priority of the extraction Service.
     * Defines the precedence of Data if several extractors
     * extracted the same property.
     *
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return int
     */
    public function getPriority()
    {
        return 50;
    }

    /**
     * Returns the execution priority of the extraction Service
     * Should be between 1 and 100, 100 means runs as first service, 1 runs at last service
     *
     * @return int
     */
    public function getExecutionPriority()
    {
        return 50;
    }

    /**
     * Checks if the given file can be processed by this Extractor
     *
     * @param File $file
     * @return bool
     */
    public function canProcess(File $file)
    {
        return $file->getStorage()->getDriverType() === CelumDriver::DRIVER_TYPE;
    }

    /**
     * The actual processing TASK
     *
     * Should return an array with database properties for sys_file_metadata to write
     *
     * @param File $file
     * @param array $previousExtractedData optional, contains the array of already extracted data
     * @return array
     */
    public function extractMetaData(File $file, array $previousExtractedData = [])
    {
        $this->log->debug('extractMetaData(' . $file->getIdentifier() . ', ' . json_encode($previousExtractedData) . ')');
        return CelumDriver::$client->getFileInfo($file->getIdentifier())['info'];
    }
}
