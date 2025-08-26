<?php

declare(strict_types = 1);

namespace Brix\CelumFal\Command;

use Brix\CelumFal\Driver\CelumDriver;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CompleteImportCommand extends Command
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Command controller task to import full DAM tree')
            ->addArgument('storageUid');
    }

    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storageUid = $input->getArgument('storageUid');

        $output->writeln('init and staging celum driver for storage with id ' . $storageUid);

        $factory = GeneralUtility::makeInstance(ResourceFactory::class);

        /** @var ResourceStorage $storage */
        $storage = $factory->getStorageObject($storageUid);
        if ($storage->getDriverType() !== CelumDriver::DRIVER_TYPE) {
            throw new RuntimeException('Chosen storage is not a CelumFal storage');
        }

        /** @var CelumDriver $celumDriver */
        $celumDriver = GeneralUtility::makeInstance(CelumDriver::class, $storage->getConfiguration());
        $celumDriver->setStorageUid((int)$storageUid);
        $celumDriver->initialize();

        $output->writeln('Starting import  process');
        $rootFolderNode = $celumDriver->getFolderInfoByIdentifier('/');

        $folderIdentifiers = $celumDriver->getFoldersInFolder($rootFolderNode['identifier'], 0, 0, true);

        $folders = [];
        foreach ($folderIdentifiers as $folderIdentifier) {
            // The folder identifier can also be an int-like string, resulting in int array keys.
            $output->writeln('collect Files for ' . $folderIdentifier);
            $files = $celumDriver->getFilesInFolder($folderIdentifier);
            $folders[$folderIdentifier] = $files;
            $output->writeln('count(' . $folderIdentifier . '):' . count($files));
        }

        $output->writeln('Done');

        return self::SUCCESS;
    }
}
