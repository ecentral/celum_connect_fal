<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Brix\CelumFal\ToolbarItem;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Backend\Event\ModifyClearCacheActionsEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;use TYPO3\CMS\Core\Utility\DebugUtility;
/**
 * Render cache clearing toolbar item.
 * Adds a dropdown if there are more than one item to clear (usually for admins to render the flush all caches).
 * The dropdown items can be manipulated using ModifyClearCacheActionsEvent.
 */
class CumulusCacheCleanerItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
    protected array $cacheActions = [];
    protected array $optionValues = [];
    private ServerRequestInterface $request;

    public function __construct(
        UriBuilder $uriBuilder,
        EventDispatcherInterface $eventDispatcher,
        private readonly BackendViewFactory $backendViewFactory
    ) {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $clearCacheUri = (string)$uriBuilder->buildUriFromRoute('ajax_celum_cache');
        $cacheActions[] = [
            'id' => 'celum_cache_action',
            'title' => 'LLL:EXT:celum_connect_fal/Resources/Private/Language/locallang.xlf:be_clear_cache_title',
            'description' => 'LLL:EXT:celum_connect_fal/Resources/Private/Language/locallang.xlf:be_clear_cache_description',
            'href' => $clearCacheUri,
            'iconIdentifier' => 'actions-synchronize',
        ];
        $this->optionValues[] = 'celum';



        $event = new ModifyClearCacheActionsEvent($cacheActions, $this->optionValues);
        $event = $eventDispatcher->dispatch($event);
        $this->cacheActions = $event->getCacheActions();
        $this->optionValues = $event->getCacheActionIdentifiers();
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Checks whether the user has access to this toolbar item.
     */
    public function checkAccess(): bool
    {
        $backendUser = $this->getBackendUser();
        if ($backendUser->isAdmin()) {
            return true;
        }
        foreach ($this->optionValues as $value) {
            if ($backendUser->getTSConfig()['options.']['clearCache.'][$value] ?? false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Render clear cache icon, based on the option if there is more than one icon or just one.
     */
    public function getItem(): string
    {
        // Fluid Template laden
        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);

        // Setze den Template Root Pfad und den Partial Pfad
        $view->setTemplateRootPaths([GeneralUtility::getFileAbsFileName('EXT:celum_connect_fal/Resources/Private/Templates/')]);
        $view->setPartialRootPaths([GeneralUtility::getFileAbsFileName('EXT:celum_connect_fal/Resources/Private/Partials/')]);
        $view->setLayoutRootPaths([GeneralUtility::getFileAbsFileName('EXT:celum_connect_fal/Resources/Private/Layouts/')]);



        // Setze die Template-Datei
        $view->setTemplate('ToolbarItems/ClearCumulusCacheToolbarItemSingle.html');

        $cacheAction = end($this->cacheActions);
        $view->assignMultiple([
            'link'  => $cacheAction['href'],
            'title' => $cacheAction['title'],
            'iconIdentifier'  => $cacheAction['iconIdentifier'],
        ]);
        return $view->render();
    }

    /**
     * Render drop-down.
     */
    public function getDropDown(): string
    {
        $view = $this->backendViewFactory->create($this->request);
        $view->assign('cacheActions', $this->cacheActions);
        return $view->render('ToolbarItems/ClearCacheToolbarItemDropDown');
    }

    /**
     * No additional attributes needed.
     */
    public function getAdditionalAttributes(): array
    {
        return [];
    }

    /**
     * This item has a drop-down, if there is more than one cache action available for the current Backend user.
     */
    public function hasDropDown(): bool
    {
        return count($this->cacheActions) > 1;
    }

    /**
     * Position relative to others
     */
    public function getIndex(): int
    {
        return 25;
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
