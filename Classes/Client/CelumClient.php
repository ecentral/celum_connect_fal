<?php

declare(strict_types = 1);

namespace Brix\CelumFal\Client;

use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use Brix\CelumFal\Utility\Cache;
use Brix\CelumFal\Utility\FileInfo;
use Brix\CelumFal\Utility\FileInfo\Format;
use Brix\CelumFal\Utility\RestClientFolderUtility;
use Celum\Client\Api\AssetsApi;
use Celum\Client\Api\CollectionsApi;
use Celum\Client\Api\DownloadApi;
use Celum\Client\Configuration;
use Celum\Client\Model\Asset;
use Celum\Client\Model\FileCategory;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CelumClient
{
    private const API_PATH = '/content-api/v1';
    private const X_API_KEY_IDENTIFIER = 'X-API-KEY';
    private const X_API_KEY_PREFIX = 'Bearer';
    private const LICENSE_SECRET_KEY = 'ZbMchtd9DivzjPDi5QIio1iVERFnNZiSE33QKY3Gw9rYfCNLFiKloJQt3zi4';

    protected string $host;
    protected string $locale;
    protected string $defaultLocale;
    protected string $username;
    protected string $password;
    protected string $apiKey;

    protected int $storage;
    protected Cache $cache;

    private Format $imageFormat;
    private Format $videoFormat;
    private Format $othersFormat;
    private Format $documentFormat;

    private ?ClientInterface $client = null;

    protected Configuration $clientConfiguration;
    protected Logger $log;

    private int $cacheLifetime;
    private array $roots = [];

    public function __construct(array $config, int $storage)
    {
        try {
            $this->log = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $this->log->debug('__construct(' . json_encode($config) . ')');

            $this->initConfiguration($config);

            $this->storage = $storage;

            $this->cache = GeneralUtility::makeInstance(Cache::class);

            // Create celum client config
            $this->clientConfiguration = Configuration::getDefaultConfiguration()
                ->setHost($this->host)
                ->setApiKeyPrefix(self::X_API_KEY_IDENTIFIER, self::X_API_KEY_PREFIX)
                ->setApiKey(self::X_API_KEY_IDENTIFIER, $this->apiKey)
                ->setUsername($this->username)
                ->setPassword($this->password);
        } catch (Exception $exception) {
            $this->log->error($exception->getMessage());
        }
    }

    private function initConfiguration(array $configuration): void
    {
        //TODO check work with licenseKey in new version

        $res = $this->decrypt((string)($configuration['licenseKey'] ?? ''));
        if (preg_match('/^(.*)_([^_]+)$/', $res, $matches) && ((int)$matches[2] > time())) {
            $this->host = $this->appendApiPathIfMissing(rtrim($matches[1]));
        } else {
            throw new InvalidConfigurationException('No valid license');
        }
        //$this->host = $this->appendApiPathIfMissing($configuration['celumHost'] ?? '');
        $this->apiKey = $configuration['celumApiKey'] ?? '';
        $this->username = $configuration['celumUser'] ?? '';
        $this->password = $configuration['celumPassword'] ?? '';
        $this->locale = $configuration['locale'] ?? 'en';
        $this->defaultLocale = $configuration['defaultLocale'] ?? 'en';
        $this->imageFormat = Format::PREVIEW;
        $this->videoFormat = Format::VIDEO;
        $this->othersFormat = Format::OTHER;
        $this->documentFormat = Format::PDF;
        $this->cacheLifetime = (int)($configuration['cacheLifetimeInMinutes'] ?? 0);
        if ($this->cacheLifetime <= 0 || $this->cacheLifetime >= 30) {
            $this->cacheLifetime = 29;
        }
        $this->cacheLifetime *= 60;

        $roots = trim((string)($configuration['roots'] ?? ''));
        if ($roots === '') {
            $this->roots = [];
            return;
        }

        $this->roots = array_map(
            static fn(string $value): string => '/' . trim($value, '/') . '/',
            array_filter(array_map('trim', explode(',', $roots)), static fn(string $value): bool => $value !== '')
        );
    }

    private function appendApiPathIfMissing(string $host): string
    {
        if ($host === '' || !$this->hasNoPath($host)) {
            return $host;
        }

        return rtrim($host, '/') . self::API_PATH;
    }

    private function hasNoPath(string $host): bool
    {
        $hostForParsing = $host;
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $host) !== 1 && strpos($host, '//') !== 0 && strpos($host, '/') !== 0) {
            $hostForParsing = '//' . $host;
        }

        $path = parse_url($hostForParsing, PHP_URL_PATH);

        return $path === null || $path === '' || $path === '/';
    }

    public function getClient(): ClientInterface
    {
        if ($this->client === null) {
            $this->client = new Client();
        }
        return $this->client;
    }

    public function extractId(string $identifier): string
    {
        return basename(rtrim($identifier, '/'));
    }

    /**
     * @param array<int, array{locale: string, value: string}> $names
     */
    public function extractName(array $names): ?string
    {
        $default = null;
        foreach ($names as $name) {
            if ($name['locale'] === $this->defaultLocale) {
                $default = $name['value'];
            } elseif ($name['locale'] === $this->locale && $name['value']) {
                return $name['value'];
            }
        }
        return $default;
    }

    protected function initCacheRoot(): void
    {
        $key = '_';
        $rootFolderInfo = [
            'info' => [
                'identifier' => '/',
                'name' => 'CELUM',
                'storage' => $this->storage
            ],
            'assets' => [],
            'children' => $this->roots
        ];
        $this->cache->set($key, $rootFolderInfo, [], $this->cacheLifetime);
        $this->cache->set($key . 'file', [], [], $this->cacheLifetime);          // no files in storage root
        $this->cache->set($key . 'filename', [], [], $this->cacheLifetime);      // no files in storage root

        // add DAM nodes as root folders
        $folders = [];
        $foldernames = [];
        foreach ($this->roots as $root) {
            $f = $this->getFolderInfo($root, '');
            $folders[] = ['identifier' => $root, 'name' => $f['info']['name']];
            $foldernames[$f['info']['name']] = $root;
        }
        $this->cache->set($key . 'folder', $folders, [], $this->cacheLifetime);
        $this->cache->set($key . 'foldername', $foldernames, [], $this->cacheLifetime);
        $this->cache->set($key . 'children', $rootFolderInfo['children'], [], $this->cacheLifetime);
        $this->cache->set($key . 'assets', $rootFolderInfo['assets'], [], $this->cacheLifetime);
    }


    private function queryBasicFolderInformation(string $identifier): array
    {
        $folderInfoReturnValue = ['info' => null, 'children' => [], 'assets' => []];

        // node id missing, return empty set
        if ($identifier === '//' || $identifier === './') {
            return $folderInfoReturnValue;
        }

        $collectionApi = new CollectionsApi($this->getClient(), $this->clientConfiguration);
        $collectionId = $this->extractId($identifier);

        try {
            $collection = $collectionApi->getCollection($collectionId, $this->locale);
            $folderInfo = RestClientFolderUtility::getFolderInfoByCollection($collection, $this->storage, $this->locale);
        } catch (Exception $exception) {
            $this->log->error($exception->getMessage());
            return $folderInfoReturnValue;
        }

        return $folderInfo;
    }

    private function querySubfolder(string $identifier): array
    {
        $typeId = 101; // CELUM collection type ID for sub-collections
        $collectionApi = new CollectionsApi($this->getClient(), $this->clientConfiguration);

        $collection = $collectionApi->getCollection($this->extractId($identifier), $this->locale);
        $folderInfo = RestClientFolderUtility::getFolderInfoByCollection($collection, $this->storage, $this->locale);

        $foldernames = [];
        $folders = [];
        if ($collection->getHasChildren()) {
            $childCollections = $collectionApi->getCollections($this->locale, $collection->getId(), $typeId);
            foreach ($childCollections->getContent() as $childCollection) {

                $folderInfo['children'][] = $childCollection->getId();
                $foldernames[RestClientFolderUtility::getFolderName($childCollection->getName(), $this->locale)] = $childCollection->getId();
                $folders[] = ['name' => RestClientFolderUtility::getFolderName($childCollection->getName(), $this->locale), 'identifier' => $childCollection->getId()];
            }
        }

        return [$folderInfo, $foldernames, $folders];
    }

    private function querySubfolderAndAssets(string $identifier): array
    {
        $typeId = 101; // CELUM collection type ID for sub-collections
        $collectionApi = new CollectionsApi($this->getClient(), $this->clientConfiguration);
        $collection = $collectionApi->getCollection($this->extractId($identifier), $this->locale);
        $folderInfo = RestClientFolderUtility::getFolderInfoByCollection($collection, $this->storage, $this->locale);

        $foldernames = [];
        $folders = [];
        $filenames = [];
        $files = [];
        if ($collection->getHasChildren()) {
            $childCollections = $collectionApi->getCollections($this->locale, $collection->getId(), $typeId);
            foreach ($childCollections->getContent() as $childCollection) {
                $folderInfo['children'][] = $childCollection->getId();
                $foldernames[RestClientFolderUtility::getFolderName($childCollection->getName(), $this->locale)] = $childCollection->getId();
                $folders[] = ['name' => RestClientFolderUtility::getFolderName($childCollection->getName(), $this->locale), 'identifier' => $childCollection->getId()];
            }

        }
        $assetsApi = new AssetsApi($this->getClient(), $this->clientConfiguration);
        $coll_id = $collection->getId();
        $assetsByCollection = $assetsApi->getAssets($this->locale, $coll_id, null, null, null, false, 1, null, null, null, ['informationFields', 'fileProperties']);
        foreach ($assetsByCollection->getContent() as $asset) {
            $folderInfo['assets'][] = $asset->getId();
            $files[] = $this->toAsset($asset);
            $filenames[$asset->getName()] = $asset->getId();
        }
        return [$folderInfo, $foldernames, $folders, $filenames, $files];
    }

    /**
     *  returns an array of the value selected for extraction or the folder info itself if nothing is specified
     * @param $identifier
     * @param $extract     string 'filename', 'foldername', 'file', 'folder' or ''
     * @return array|mixed
     */
    public function getFolderInfo(string $identifier, string $extract = ''): array
    {
        $key = str_replace(['/', '.'], ['_', ''], $identifier);
        if ($identifier === '/' || $identifier === './') {
            $this->initCacheRoot();
        } else {
            if ($extract === '') {
                // we are looking for basic folder information no recursion
                $folderInfo = $this->queryBasicFolderInformation($identifier);
                if (!isset($folderInfo['info'])) {
                    $this->cache->set($key, $folderInfo, [], 60);  // set cache with short ttl to revisit shortly
                    return $folderInfo;
                }
            } elseif ($extract === 'folder' || $extract === 'children') {
                [$folderInfo, $foldernames, $folders] = $this->querySubfolder($identifier);

                if (!isset($folderInfo['info'])) {
                    $this->cache->set($key, $folderInfo, [], 60);  // set cache with short ttl to revisit shortly
                    return $folderInfo;
                }

                $this->cache->set($key . 'folder', $folders, [], $this->cacheLifetime);
                $this->cache->set($key . 'foldername', $foldernames, [], $this->cacheLifetime);
                $this->cache->set($key . 'children', $folderInfo['children'], [], $this->cacheLifetime);

            } else {
                [$folderInfo, $foldernames, $folders, $filenames, $files] = $this->querySubfolderAndAssets($identifier);
                if (!isset($folderInfo['info'])) {
                    $this->cache->set($key, $folderInfo, [], 60);  // set cache with short ttl to revisit shortly
                    return $folderInfo;
                }

                // add additional cache values for further processing
                $this->cache->set($key . 'file', $files, [], $this->cacheLifetime);
                $this->cache->set($key . 'filename', $filenames, [], $this->cacheLifetime);
                $this->cache->set($key . 'folder', $folders, [], $this->cacheLifetime);
                $this->cache->set($key . 'foldername', $foldernames, [], $this->cacheLifetime);

                // fill up cache with asset information
                foreach ($files as $asset) {
                    $assetKey = str_replace('/', '_', $asset['info']['identifier']);
                    $this->cache->set($assetKey, $asset, [], $this->cacheLifetime);
                }

                $this->cache->set($key . 'assets', $folderInfo['assets'], [], $this->cacheLifetime);
                $this->cache->set($key . 'children', $folderInfo['children'], [], $this->cacheLifetime);

            }
            $this->cache->set($key, $folderInfo, [], $this->cacheLifetime);
        }
        $this->log->debug("getFolderInfo($identifier, $extract): " . json_encode($this->cache->get($key . $extract)));

        return $this->cache->get($key . $extract);

    }

    public function getFileInfo(string $identifier): bool|array
    {
        $fileId = $this->getFileIdByFileIdentifier($identifier);
        $key = str_replace('/', '_', $identifier);
        if (!$this->cache->has($key)) {
            $assetsApi = new AssetsApi($this->getClient(), $this->clientConfiguration);
            try {
                $asset = $assetsApi->getAsset($fileId, $this->locale, null, ['informationFields', 'fileProperties']);
                $this->cache->set($key, $this->toAsset($asset), [], $this->cacheLifetime);
            } catch (Exception $exception){
                $this->log->error("getFileInfo($fileId)" . json_encode($this->cache->get($key)) . ':' . $exception->getMessage());
                $this->cache->set($key, ['info' => null], [], 60); // short cache on error
            }
        }
        $this->log->debug("getFileInfo($fileId)" . json_encode($this->cache->get($key)));
        return $this->cache->get($key);
    }

    private function toAsset(Asset $asset): array
    {
        $fileCategory = $asset->getCurrentVersion()->getFileCategory();
        //get download url from original for docuemnts, unknown,
        $originalDownloadUrl = null;
        if ($fileCategory == FileCategory::DOCUMENT ||
            $fileCategory == FileCategory::UNKNOWN ||
            $fileCategory == FileCategory::MODEL3_D ||
            $fileCategory == FileCategory::TEXT ||
            $fileCategory == FileCategory::VIDEO ||
            $fileCategory == FileCategory::PLACEHOLDER) {
            $downloadApi = new DownloadApi($this->getClient(), $this->clientConfiguration);
            $download = $downloadApi->requestDownload((string)$asset->getId(), 1);
            $originalDownloadUrl = $download->getUrl();
        }

        $fileInfo = new FileInfo($asset, $originalDownloadUrl, $this->storage, $this->imageFormat, $this->videoFormat, $this->documentFormat, $this->othersFormat);

        return $fileInfo->toArray();
    }

    private function getFileIdByFileIdentifier(string $fileIdentifier): int
    {
        $fileArray = explode('/', $fileIdentifier);
        return (int)$fileArray[count($fileArray) - 1];
    }

    public function getUrl(string $identifier, string $type = 'publicUrl'): mixed
    {
        if (substr($identifier, 0, 5) === 'thumb') {
            $type = 'thumbnail';
            $identifier = substr($identifier, 5);
        }
        $fileInfo = $this->getFileInfo($identifier);
        $url = $fileInfo[$type];
        $this->log->debug("getUrl($identifier, $type): $url");
        return $url;
    }

    private function decode_base64(string $sData): string
    {
        $sBase64 = strtr($sData, '-_', '+/');
        $remainder = strlen($sBase64) % 4;
        if ($remainder > 0) {
            $sBase64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($sBase64, true);
        if ($decoded === false) {
            throw new InvalidConfigurationException('Invalid license encoding');
        }

        return $decoded;
    }

    private function decrypt(string $sData): string
    {
        $sResult = '';
        $sData   = $this->decode_base64($sData);
        $keyLength = strlen(self::LICENSE_SECRET_KEY);

        for ($i = 0, $length = strlen($sData); $i < $length; $i++) {
            $sChar    = substr($sData, $i, 1);
            $sKeyChar = substr(self::LICENSE_SECRET_KEY, ($i % $keyLength) - 1, 1);
            $sChar    = chr((ord($sChar) - ord($sKeyChar) + 256) % 256);
            $sResult .= $sChar;
        }
        return $sResult;
    }

}
