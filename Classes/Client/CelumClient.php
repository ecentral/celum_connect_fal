<?php

declare(strict_types = 1);

namespace Brix\CelumFal\Client;

use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use Brix\CelumFal\Utility\Cache;
use Brix\CelumFal\Utility\FileInfo;
use Brix\CelumFal\Utility\FileInfo\Format;
use Brix\CelumFal\Utility\RestClientFolderUtility;
use Celum\Client\Api\AboutApi;
use Celum\Client\Api\AssetsApi;
use Celum\Client\Api\CollectionsApi;
use Celum\Client\Api\DownloadApi;
use Celum\Client\Api\DownloadFormatsApi;
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
    /*NEW*/
    const X_API_KEY_IDENTIFIER = 'X-API-KEY';
    const X_API_KEY_PREFIX = 'Bearer';

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

    protected $client;

    protected Configuration $clientConfiguration;

    /*TODO OLD REMOVE*/
    const API_MAX_ASSET_CHUNK = 200; // API defined maximum of child assets per request (max api page size)

    protected string $celumUrl;
    protected string $cora;
    //protected string $locale;
    //protected string $defaultLocale;
    //protected Cache $cache;
    protected Logger $log;
    //protected int $storage;
    protected string $directDownload;
    private array $provider;
    private array $description;
    private string $secret;
    //private Client $client;
    private array $options;
    private array $postOptions;

    private int $cacheLifetime;
    private string $token;
    private string $writePublicUrls;
    private string $infoFieldId;
    private string $nodeId;
    private string $descriptionFieldName;
    private string $alternativeFieldName;
    private string $fieldSelect = '';
    private array $roots;

    /**
     * @throws InvalidConfigurationException
     */
    public function __construct(array $config, int $storage)
    {
        try {
            $this->log = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $this->log->debug('__construct(' . json_encode($config) . ')');

            $this->initConfiguration($config);

            $this->storage = $storage;

            $this->cache = GeneralUtility::makeInstance(Cache::class);

            //Create celum client config
            $this->clientConfiguration = Configuration::getDefaultConfiguration()
                ->setHost($this->host)
                ->setApiKeyPrefix(self::X_API_KEY_IDENTIFIER, self::X_API_KEY_PREFIX)
                ->setApiKey(self::X_API_KEY_IDENTIFIER, $this->apiKey)
                ->setUsername($this->username)
                ->setPassword($this->password);

            return;

            $this->getDownloadFormats();
            //$this->test($configuration);

            /*TODO OLD REMOVE*/
            $this->log = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $this->log->debug('__construct(' . json_encode($config) . ')');
            $res = $this->decrypt($config['licenseKey']);

            if (preg_match('/^(.*)_([^_]+)$/', $res, $matches) and ($matches[2] > time())) {
                $this->celumUrl = rtrim($matches[1]);
            } else {
                throw new InvalidConfigurationException('No valid license');
            }
            //$this->imageFormat = $config['imageDownloadFormat'];
            //$this->videoFormat = $config['videoDownloadFormat'];
            //$this->othersFormat = $config['othersDownloadFormat'];
            $this->directDownload = $this->celumUrl . '/direct/download?';
            $this->provider = ['video' => $config['publicURLsProviderVideo'], 'image' => $config['publicURLsProviderImage']];
            $this->description = ['video' => $config['publicURLsDescriptionVideo'], 'image' => $config['publicURLsDescriptionImage']];
            $this->locale = $config['locale'];
            $this->defaultLocale = $config['defaultLocale'];
            $this->secret = $config['directDownloadSecret'];

            $this->token = $config['infoFieldSetterToken'];
            $this->writePublicUrls = $config['writePublicUrls'];
            $this->infoFieldId = $config['informationFieldId'];
            $this->nodeId = $config['nodeId'];
            $this->descriptionFieldName = $config['descriptionFieldName'];
            if ($this->descriptionFieldName) {
                $this->fieldSelect .= ',informationFieldValues/' . $this->descriptionFieldName;
            }
            $this->alternativeFieldName = $config['alternativeTextFieldName'];
            if ($this->alternativeFieldName) {
                $this->fieldSelect .= ',informationFieldValues/' . $this->alternativeFieldName;
            }
        } catch (Exception $exception) {
            $this->log->error($exception->getMessage());
        }
    }

    public function initConfiguration(array $configuration)
    {
        //TODO check work with licenseKey in new version
        /*
            $res = $this->decrypt($configuration['licenseKey']);
            if (preg_match('/^(.*)_([^_]+)$/', $res, $matches) and ($matches[2] > time())) {
                $this->host = rtrim($matches[1]);
            } else {
                throw new InvalidConfigurationException('No valid license');
            }
         */
        $this->host = $configuration['celumHost'] ?? '';

        $this->apiKey = $configuration['celumApiKey'] ?? '';

        $this->username = $configuration['celumUser'] ?? '';
        $this->password = $configuration['celumPassword'] ?? '';

        $this->locale = $configuration['locale'] ?? 'en';
        $this->defaultLocale = $configuration['defaultLocale'] ?? 'en';


        $this->imageFormat = Format::PREVIEW;
        //TODO append format for video and other
        $this->videoFormat = Format::VIDEO;
        $this->othersFormat = Format::OTHER;
        $this->documentFormat = Format::PDF;


        $this->cacheLifetime = intval($configuration['cacheLifetimeInMinutes']);
        if (($this->cacheLifetime <= 0) || ($this->cacheLifetime >= 30)) {
            $this->cacheLifetime = 29;
        }
        $this->cacheLifetime *= 60;

        $this->roots = preg_split('/\\s*,\\s*/', trim($configuration['roots']??''));
        foreach ($this->roots as $key => $val) {
            $this->roots[$key] = "/$val/";
        }
    }

    public function getClient(): ClientInterface
    {
        if($this->client == null) {
            try {
                $this->client = new Client();
            } catch (Exception $exception) {
                $this->log->error($exception->getMessage());
            }
        }
        return $this->client;
    }

    public function test(){

        $apiInstance = new AboutApi(
            $this->getClient(),
            $this->clientConfiguration
        );
        $x_celum_username = 'x_celum_username_example'; // string | Provide the username of the user that you want to impersonate

        try {
            $result = $apiInstance->getVersion();
            print_r($result);
        } catch (Exception $e) {
            echo 'Exception when calling AboutApi->getVersion: '. $e->getMessage();
        }
    }

    public function extractId($identifier): string
    {
        return basename(rtrim($identifier, '/'));
    }

    /**
     * @param array<int, array{locale: string, value: string}> $names
     */
    public function extractName(array &$names): ?string
    {
        $default = null;
        foreach ($names as $name) {
            if ($name['locale'] == $this->defaultLocale) {
                $default = $name['value'];
            } elseif (($name['locale'] == $this->locale) and $name['value']) {
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

        $collection = $collectionApi->getCollection($collectionId, $this->locale);

        $folderInfo = RestClientFolderUtility::getFolderInfoByCollection($collection, $this->storage, $this->locale);

        return $folderInfo;
    }

    private function querySubfolder(string $identifier): array
    {
        $typeId = 101;
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

    private function querySubfolderAndAssets(string $identifier): array{

        $typeId = 101;
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
            //if($asset->getCurrentVersion()->getFileCategory() != FileCategory::UNKNOWN) {
                $folderInfo['assets'][] = $asset->getId();
                $files[] = $this->toAsset($asset, (string)$asset->getId());
                $filenames[$asset->getName()] = $asset->getId();
            //}
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
        $key = str_replace(['/','.'], ['_',''], $identifier);
        if (true) {
            if ($identifier == '/' || $identifier == './') {
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
                    list($folderInfo, $foldernames, $folders) = $this->querySubfolder($identifier);

                    if (!isset($folderInfo['info'])) {
                        $this->cache->set($key, $folderInfo, [], 60);  // set cache with short ttl to revisit shortly
                        return $folderInfo;
                    }

                    $this->cache->set($key . 'folder', $folders, [], $this->cacheLifetime);
                    $this->cache->set($key . 'foldername', $foldernames, [], $this->cacheLifetime);
                    $this->cache->set($key . 'children', $folderInfo['children'], [], $this->cacheLifetime);

                } else {
                    list($folderInfo, $foldernames, $folders, $filenames, $files) = $this->querySubfolderAndAssets($identifier);
                    if (!isset($folderInfo['info'])) {
                        $this->cache->set($key, $folderInfo, [], 60);  // set cache with short ttl to revisit shortly
                        return $folderInfo;
                    }

                    // add additional Cache values for further processing
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
        }
        $this->log->debug("getFolderInfo($identifier, $extract): " . json_encode($this->cache->get($key . $extract)));

        return $this->cache->get($key . $extract);

    }

    public function getFileInfo($identifier): array
    {
        $fileId = $this->getFileIdByFileIdentifier($identifier);
        $key = str_replace('/', '_', $identifier);
        if (!$this->cache->has($key)) {
            $assetsApi = new AssetsApi($this->getClient(), $this->clientConfiguration);
            try {
                $asset = $assetsApi->getAsset($fileId, $this->locale, null, ['informationFields', 'fileProperties']);
                $this->cache->set($key, $this->toAsset($asset, $identifier), [], $this->cacheLifetime);
            } catch (Exception $exception){
                $this->log->error("getFileInfo($fileId)" . json_encode($this->cache->get($key)) . ':' . $exception->getMessage());
                $this->cache->set($key, ['info' => null], [], 60); // short cache on error
            }
        }
        $this->log->debug("getFileInfo($fileId)" . json_encode($this->cache->get($key)));
        return $this->cache->get($key);
    }

    private function toAsset(Asset $asset, string $identifier): array
    {
        $fileCategory = $asset->getCurrentVersion()->getFileCategory();
        //get download url from original for docuemnts, unknown,
        $originalDownloadUrl = null;
        if($fileCategory == FileCategory::DOCUMENT ||
            $fileCategory == FileCategory::UNKNOWN ||
            $fileCategory == FileCategory::MODEL3_D ||
            $fileCategory == FileCategory::TEXT ||
            $fileCategory == FileCategory::VIDEO ||
            $fileCategory == FileCategory::PLACEHOLDER) {
            $downloadApi = new DownloadApi($this->getClient(), $this->clientConfiguration);
            $download = $downloadApi->requestDownload($identifier, 1);
            $originalDownloadUrl = $download->getUrl();
        }

        $fileInfo = new FileInfo($asset, $originalDownloadUrl, $this->storage, $this->imageFormat, $this->videoFormat, $this->documentFormat, $this->othersFormat);

        return $fileInfo->toArray();
    }

    private function getFileIdByFileIdentifier(string $fileIdentifier): int
    {
        $fileArray = explode('/',$fileIdentifier);
        return (int)$fileArray[count($fileArray)-1];
    }

    private function getInfoFieldValue(string $name, array $arr): string
    {
        if (!isset($arr['informationFieldValues']) || !$arr['informationFieldValues']) {
            return '';
        }
        $val = $arr['informationFieldValues'][$name];
        if (!$val) {
            return '';
        }
        if (is_array($val)) {
            $v = $this->extractName($val);
            return $v == null ? '' : $v;
        }
        return $val;
    }

    public function getUrl(string $identifier, string $type = 'publicUrl')
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
        return base64_decode($sBase64 . '==');
    }

    private function decrypt(string $sData): string
    {
        $secretKey = 'ZbMchtd9DivzjPDi5QIio1iVERFnNZiSE33QKY3Gw9rYfCNLFiKloJQt3zi4';
        $sResult = '';
        $sData   = $this->decode_base64($sData);
        for ($i=0;$i<strlen($sData);$i++) {
            $sChar    = substr($sData, $i, 1);
            $sKeyChar = substr($secretKey, ($i % strlen($secretKey)) - 1, 1);
            $sChar    = chr(ord($sChar) - ord($sKeyChar));
            $sResult .= $sChar;
        }
        return $sResult;
    }

}
