<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Command;

use Brix\CelumFal\Index\Extractor;
use Brix\CelumFal\Utility\DriverUtility;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RefreshMetaCommand extends Command
{
    protected static $defaultName = 'celumfal:refreshmeta';

    public function __construct(private readonly Extractor $metadataExtractor)
    {
        parent::__construct(self::$defaultName);
    }

    public function configure(): void
    {
        $this->setDescription('Command to refresh meta stored in system_file_meta')
            ->addArgument('storageUid');

        $this->setHelp(
            <<<'EOF'
This command will pull down all metadata of celum files and override it analog to the definition in the backend.
It will also delete all processed files to these files
EOF
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting meta refresh process');
        $storageUid = $input->getArgument('storageUid');

        $output->writeln('init and staging celum driver for storage with id ' . $storageUid);

        $factory = GeneralUtility::makeInstance(ResourceFactory::class);

        /** @var ResourceStorage $storage */
        $storage = $factory->getStorageObject($storageUid);

        $driverClass = DriverUtility::getDriver();

        if ($storage->getDriverType() !== $driverClass::DRIVER_TYPE) {
            throw new RuntimeException('Chosen storage is not a CelumFal storage');
        }

        $celumDriver = GeneralUtility::makeInstance($driverClass, $storage->getConfiguration());
        $celumDriver->setStorageUid((int)$storageUid);
        $celumDriver->initialize();

        $output->writeln('Starting import  process');
        $rootFolderNode = $celumDriver->getFolderInfoByIdentifier('/');

        $folderIdentifiers = $celumDriver->getFoldersInFolder($rootFolderNode['identifier'], 0, 0, true);

        $files = [];
        $folders = [];
        $progressBar = new ProgressBar($output, count($folderIdentifiers));
        $progressBar->start();
        $output->writeln('');
        foreach ($folderIdentifiers as $folderIdentifier) {
            $progressBar->advance();
            // The folder identifier can also be an int-like string, resulting in int array keys.
            $output->write('collecting Files for ' . $folderIdentifier . '*...');
            $filesInFolder = $celumDriver->getFilesInFolder($folderIdentifier);
            $files = array_merge($files, $filesInFolder);
            $folders[$folderIdentifier] = $files;
            $output->writeln('Files found: ' . count($filesInFolder));
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('Total files found in DAM: ' . count($files));
        $output->writeln('Start processing File Meta ...');

        $progressBar = new ProgressBar($output, count($files));
        $progressBar->start();
        $output->writeln('');

        foreach ($files as $fileIdentifier) {
            $progressBar->advance();

            try {
                $file = $factory->getFileObjectFromCombinedIdentifier($storageUid . ':' . $fileIdentifier);
                assert($file instanceof File);
                $metaData = $this->metadataExtractor->extractMetaData($file);
                if ($metaData) {
                    $file->getMetaData()->add($metaData)->save();
                    $file->getForLocalProcessing(true);
                    $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
                    foreach ($processedFileRepository->findAllByOriginalFile($file) as $processedFile) {
                        $processedFile->delete(true);
                    }
                }
            } catch (Exception $e) {
                $output->writeln('File ' . $fileIdentifier . ' failed: ' . $e->getMessage());
                continue;
            }
        }
        $progressBar->finish();
        $output->writeln('Done');
        return self::SUCCESS;
    }
}
