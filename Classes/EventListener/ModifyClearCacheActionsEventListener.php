<?php
namespace Brix\CelumFal\EventListener;

use TYPO3\CMS\Backend\Backend\Event\ModifyClearCacheActionsEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
class ModifyClearCacheActionsEventListener
{
    public function __invoke(ModifyClearCacheActionsEvent $modifyClearCacheActionsEvent): void
    {
        $cacheActions = $modifyClearCacheActionsEvent->getCacheActions();

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $clearCacheUri = (string)$uriBuilder->buildUriFromRoute('ajax_celum_cache');

        $cacheActions[] = [
            'id' => 'celum_cache_action',
            'title' => 'LLL:EXT:celum_connect_fal/Resources/Private/Language/locallang.xlf:be_clear_cache_title',
            'description' => 'LLL:EXT:celum_connect_fal/Resources/Private/Language/locallang.xlf:be_clear_cache_description',
            'href' => $clearCacheUri,
            'iconIdentifier' => 'actions-system-cache-clear-impact-medium',
        ];

        $modifyClearCacheActionsEvent->setCacheActions($cacheActions);
    }
}
