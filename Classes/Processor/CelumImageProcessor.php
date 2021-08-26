<?php


namespace Brix\CelumFal\Processor;

use Brix\CelumFal\Driver\CelumDriver;
use InvalidArgumentException;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Processing\ProcessorInterface;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CelumImageProcessor implements ProcessorInterface {

    /** @var Logger */
    protected $log;

    public function __construct() {
        $this->log = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    public function canProcessTask(TaskInterface $task) {
        $this->log->debug("canProcessTask(" . json_encode($task) . ")");
        return ($task->getName() === 'Preview') and
            $task->getSourceFile()->getStorage()->getDriverType() === CelumDriver::DRIVER_TYPE;
    }

    public function processTask(TaskInterface $task) {
        $this->log->debug("processTask(" . json_encode($task) . ")");
        if (!$this->canProcessTask($task))
            throw new InvalidArgumentException('Cannot process task of type "' . $task->getType() . '.' . $task->getName() . '"');
        $id = $task->getSourceFile()->getIdentifier();
        $info = CelumDriver::$client->getFileInfo($id);
        $width = $info['info']['width'];
        $height = $info['info']['height'];
        $original = false;
        if ($width and $height) {
            $max = 250;
            if (($width > $max) or ($height > $max)) {
                if ($width > $height) {
                    $height = intval($height * $max / $width);
                    $width = $max;
                } else {
                    $width = intval($width * $max / $height);
                    $height = $max;
                }
            } else {
                $original = true;
            }
        } else {
            $original = true;
            $width = 0;
            $height = 0;
        }
        $task->setExecuted(true);
        $id = $task->getSourceFile()->getIdentifier();
        $this->log->debug("ID: $id");
        if ($original) {
            $task->getTargetFile()->setUsesOriginalFile();
        } else {
            $id = 'thumb' . $id;
            $storage = $task->getSourceFile()->getStorage();
            $task->getTargetFile()->setName($task->getTargetFileName());
            $task->getTargetFile()->setIdentifier($id);
            $task->getTargetFile()->setStorage($storage);
            $task->getTargetFile()->updateProcessingUrl(CelumDriver::$client->getUrl($id));
        }
        $task->getTargetFile()->updateProperties([
            'width' => $width,
            'height' => $height,
            'size' => $task->getSourceFile()->getSize(),
            'checksum' => $task->getConfigurationChecksum()
        ]);
    }

}