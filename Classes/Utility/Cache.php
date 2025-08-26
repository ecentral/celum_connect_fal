<?php
namespace Brix\CelumFal\Utility;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Cache implements SingletonInterface
{
    private ?FrontendInterface $cache = null;
    private array $cacheData = [];

    public function __construct()
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);

        $driverExtensuionKey = (new Typo3Version())->getMajorVersion() < 13
            ? \Brix\CelumFal\Driver\CelumDriverV12::EXTENSION_KEY
            : \Brix\CelumFal\Driver\CelumDriver::EXTENSION_KEY;

        if ($cacheManager->hasCache($driverExtensuionKey)) {
            $this->cache = $cacheManager->getCache($driverExtensuionKey);
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
        $result = ['success' => true, 'title' => 'Success', 'message' => 'Celum cache successfully cleared.'];
        return new JsonResponse($result);
    }
}
