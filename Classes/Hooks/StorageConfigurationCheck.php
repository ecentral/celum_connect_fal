<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Hooks;

use Brix\CelumFal\Driver\CelumDriver;
use Brix\CelumFal\Service\StorageConfigurationValidator;
use Brix\CelumFal\Service\ValidationResult;
use DateTimeImmutable;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Service\FlexFormService;

/**
 * Tells the editor right away when a CELUM storage was saved with a license
 * key or API key that does not work.
 *
 * Registered as a DataHandler hook because neither TYPO3 v12.4 nor v13.4 offer
 * a PSR-14 event for the data map. The record is always saved - a key may be
 * entered before it is activated on the CELUM side.
 */
final class StorageConfigurationCheck
{
    private const LANGUAGE_FILE = 'LLL:EXT:celum_connect_fal/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly StorageConfigurationValidator $validator,
        private readonly FlashMessageService $flashMessageService,
        private readonly FlexFormService $flexFormService,
        private readonly LanguageServiceFactory $languageServiceFactory
    ) {
    }

    /**
     * @param string $status "new" or "update"
     * @param string|int $id Uid of the record, or its NEW placeholder
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string|int $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($table !== 'sys_file_storage') {
            return;
        }

        // A flash message needs someone to read it and a session to survive in.
        // The DataHandler also runs from the command line - scheduler tasks,
        // imports - where there is neither, and storing a message would fail.
        if (!$this->isBackendRequest()) {
            return;
        }

        $uid = (int)($dataHandler->substNEWwithIDs[$id] ?? $id);
        if ($uid <= 0) {
            return;
        }

        $record = BackendUtility::getRecord('sys_file_storage', $uid);
        if ($record === null || ($record['driver'] ?? '') !== CelumDriver::DRIVER_TYPE) {
            return;
        }

        $configuration = $this->flexFormService->convertFlexFormContentToArray(
            (string)($record['configuration'] ?? '')
        );

        $results = $this->validator->validate($configuration, $uid, new DateTimeImmutable());
        foreach ($results as $result) {
            $this->enqueue($result);
        }
    }

    private function isBackendRequest(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return false;
        }

        // ApplicationType::fromRequest() throws when the attribute is missing,
        // which is exactly the case outside a TYPO3 application.
        if (!is_int($request->getAttribute('applicationType'))) {
            return false;
        }

        return ApplicationType::fromRequest($request)->isBackend();
    }

    private function enqueue(ValidationResult $result): void
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER'] ?? null);
        $message = $languageService->sL(self::LANGUAGE_FILE . $result->messageKey);
        if ($result->arguments !== []) {
            $message = vsprintf($message, $result->arguments);
        }

        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(
            new FlashMessage(
                $message,
                $languageService->sL(self::LANGUAGE_FILE . 'storageCheck.title'),
                $result->severity,
                // The backend redirects after saving, so the message has to survive the request.
                true
            )
        );
    }
}
