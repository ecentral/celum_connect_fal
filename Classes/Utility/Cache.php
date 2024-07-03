<?php
namespace Brix\CelumFal\Utility;

use Brix\CelumFal\Driver\CelumDriver;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Cache implements SingletonInterface
{
    private ?FrontendInterface $cache = null;
    private array $cacheData = [];

    public function __construct()
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        if ($cacheManager->hasCache(CelumDriver::EXTENSION_KEY)) {
            $this->cache = $cacheManager->getCache(CelumDriver::EXTENSION_KEY);
        }
    }

    public function has(string $entryIdentifier): bool
    {
        if ($this->cache) {
            return $this->cache->has($entryIdentifier);
        }

        return array_key_exists($entryIdentifier, $this->cacheData);
    }

    public function set(string $entryIdentifier, $data, array $tags = [], $lifetime = null): void
    {
        if ($this->cache) {
            $this->cache->set($entryIdentifier, $data, $tags, $lifetime);
            return;
        }

        $this->cacheData[$entryIdentifier] = $data;
    }

    /**
     * @param string $entryIdentifier
     * @return mixed
     */
    public function get(string $entryIdentifier)
    {
        if ($this->cache) {
            return $this->cache->get($entryIdentifier);
        }

        return $this->cacheData[$entryIdentifier];
    }



    /**
     * clear the celum cache
     *
     * @param ResponseInterface $response the current response
     * @return ResponseInterface
     */
    public function clearCache(): ResponseInterface
    {
        if ($this->cache) {
            $this->cache->flush();
        }
        $this->cacheData = [];
        return new HtmlResponse('');
    }
}