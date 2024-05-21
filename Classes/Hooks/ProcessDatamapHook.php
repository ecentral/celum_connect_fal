<?php
namespace Brix\CelumFal\Hooks;

use Brix\CelumFal\Client\CelumClient;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Service\TranslationService;

class ProcessDatamapHook
{
    private TranslationService $translationService;

    public function processDatamap_afterDatabaseOperations(string $status, string $table, $id, array &$fieldArray, DataHandler $pObj): void
    {
        if ($status === 'new' && !is_int($id)) {
            $id = $pObj->substNEWwithIDs[$id];
        }
        $this->updateReference($table, $id, $status);
    }

    public function processCmdmap_postProcess(string $command, string $table, $id, $commandValue, DataHandler $dataHandler)
    {
        $this->updateReference($table, $id, $command);
    }

    protected function updateReference(string $table, int $id, string $status)
    {
        $references = [];
        if ($table === 'sys_file_reference') {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($table);

            $queryBuilder->getRestrictions()->removeAll();
            $query = $queryBuilder
                ->select('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)
                    ),
                )
            ->execute();
            $references = $query->fetchAllAssociative();
        } else {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_file_reference');
            $queryBuilder->getRestrictions()->removeAll();
            $query = $queryBuilder
                ->select('*')
                ->from('sys_file_reference')
                ->where(
                    $queryBuilder->expr()->eq(
                        'tablenames',
                        $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)
                    ),
                    $queryBuilder->expr()->eq(
                        'uid_foreign',
                        $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)
                    ),
                )
                ->execute();

            $references = $query->fetchAllAssociative();
        }

        foreach ($references as $sysFileReference) {
                $resourceFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\ResourceFactory::class);
                $file = $resourceFactory->getFileObject($sysFileReference['uid_local']);

                if ($file->getStorage()->getDriverType() === 'BrixCelumDriver') {
                    $tableName = $sysFileReference['tablenames'] ?: $table;
                    $recordId = $sysFileReference['uid_foreign'] ?: $id;

                    if ($tableName !== 'sys_file_reference') {
                        if ($sysFileReference['deleted'] === 1 || $sysFileReference['hidden'] === 1) {
                            $usedOnOtherPlaces = false;

                            // is file used anywhere else?
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                                ->getQueryBuilderForTable('sys_file_reference');
                            $query = $queryBuilder
                                ->select('*')
                                ->from('sys_file_reference')
                                ->where(
                                    $queryBuilder->expr()->eq(
                                        'uid_local',
                                        $queryBuilder->createNamedParameter($sysFileReference['uid_local'], Connection::PARAM_INT)
                                    ),
                                )
                                ->execute();
                            if ($query->rowCount() > 0) {
                                $usedOnOtherPlaces = true;
                            }

                            $client = new CelumClient($file->getStorage()->getConfiguration(), $file->getStorage()->getStorageRecord()['uid']);
                            $client->deletePublicUrl($file->getIdentifier(), $this->getTableName($tableName) . ' ' . $recordId, $usedOnOtherPlaces);
                        } else {
                            $backendUriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                            $uriParameters = ['edit' => [$tableName => [$recordId => 'edit']]];
                            $url = $backendUriBuilder->buildUriFromRoute('record_edit', $uriParameters, $backendUriBuilder::SHAREABLE_URL);

                            $client = new CelumClient($file->getStorage()->getConfiguration(), $file->getStorage()->getStorageRecord()['uid']);
                            $client->addPublicUrl($file->getIdentifier(), $url, $this->getTableName($tableName) . ' ' . $recordId);
                        }
                    }
                }
        }
    }

    protected function getTableName(string $table)
    {
        $title = $GLOBALS['TCA'][$table]['ctrl']['title'];
        return GeneralUtility::makeInstance(TranslationService::class)->translate($title, null, null, null, $title);
    }
}