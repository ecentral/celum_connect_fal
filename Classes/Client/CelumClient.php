<?php
/**
 * Created by PhpStorm.
 * User: CMA
 * Date: 05/11/2018
 * Time: 13:37
 */

declare(strict_types = 1);

namespace Brix\CelumFal\Client;

use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use Brix\CelumFal\Utility\Cache;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class CelumClient
{

    const API_MAX_ASSET_CHUNK = 200; // API defined maximum of child assets per request (max api page size)

    protected string $celumUrl;
    protected string $cora;
    protected string $locale;
    protected string $defaultLocale;
    protected Cache $cache;
    protected Logger $log;
    protected int $storage;
    protected string $directDownload;
    private array $provider;
    private array $description;
    private string $secret;
    private Client $client;
    private array $options;
    private array $postOptions;
    private string $imageFormat;
    private string $videoFormat;
    private string $othersFormat;
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
        // The following leads to being unable to configure a driver, because T3 makes an instance before it is configured
        /*
        if (empty($config['licenseKey'])) {
            throw new InvalidConfigurationException('No licenseKey given');
        }

        if (empty($config['celumApiKey'])) {
            throw new InvalidConfigurationException('No celumApiKey given');
        }
        */
        try {
            $this->log = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $this->log->debug('__construct(' . json_encode($config) . ')');
            $res = $this->decrypt($config['licenseKey']);

            if (preg_match('/^(.*)_([^_]+)$/', $res, $matches) and ($matches[2] > time())) {
                $this->celumUrl = rtrim($matches[1]);
            } else {
                throw new InvalidConfigurationException('No valid license');
            }

            $this->cora = $this->celumUrl . '/cora/';
            $this->imageFormat = $config['imageDownloadFormat'];
            $this->videoFormat = $config['videoDownloadFormat'];
            $this->othersFormat = $config['othersDownloadFormat'];
            $this->directDownload = $this->celumUrl . '/direct/download?';
            $this->provider = ['video' => $config['publicURLsProviderVideo'], 'image' => $config['publicURLsProviderImage']];
            $this->description = ['video' => $config['publicURLsDescriptionVideo'], 'image' => $config['publicURLsDescriptionImage']];
            $this->locale = $config['locale'];
            $this->defaultLocale = $config['defaultLocale'];
            $this->secret = $config['directDownloadSecret'];
            $this->storage = $storage;
            $this->cache = GeneralUtility::makeInstance(Cache::class);
            $this->client = new Client(['base_uri' => $this->cora]);
            $this->options = ['headers' => ['Authorization' => 'celumApiKey ' . $config['celumApiKey']], 'verify' => false];
            $this->postOptions = ['verify' => false];
            $this->cacheLifetime = intval($config['cacheLifetimeInMinutes']);
            if (($this->cacheLifetime <= 0) || ($this->cacheLifetime >= 30)) {
                $this->cacheLifetime = 29;
            }
            $this->cacheLifetime *= 60;
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
            $this->roots = preg_split('/\\s*,\\s*/', trim($config['roots']));
            foreach ($this->roots as $key => $val) {
                $this->roots[$key] = "/$val/";
            }
        } catch (Exception $exception) {
            $this->log->error($exception->getMessage());
        }
    }

    /**
     * @param $identifier
     * @return string
     */
    public function extractId($identifier): string
    {
        return basename(rtrim($identifier, '/'));
    }

    /**
     * @param array<int, array{locale: string, value: string}> $names
     * @return string|null
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

    /**
     * @param $identifier
     * @return array
     */
    private function queryBasicFolderInformation(string $identifier): array
    {
        $folderInfoReturnValue = ['info' => null, 'children' => [], 'assets' => []];

        // node id missing, return empty set
        if ($identifier === '//') {
            return $folderInfoReturnValue;
        }

        $id = $this->extractId($identifier);
        $request = 'Nodes(' . $id . ')?$select=id,name,children,assets,modificationInformation';
        $this->log->debug('request: GET:' . $request);
        try {
            $response = $this->client->request('GET', $request, $this->options)->getBody();
            if ($response) {
                $response = json_decode((string)$response, true);

                $folderInfoReturnValue = [
                    'info' => [
                        'identifier' => $identifier,
                        'name' => $this->extractName($response['name']),
                        'storage' => $this->storage,
                        'mtime' => strtotime($response['modificationInformation']['lastModificationDateTime']),
                    ],
                    'children' => [],
                    'assets' => []
                ];
            }
        } catch (GuzzleException $exception) {
            $this->log->error($exception->getMessage());
        }
        return $folderInfoReturnValue;
    }

    /**
     * @param $identifier
     * @return array
     */
    private function querySubfolder(string $identifier): array
    {
        $foldernames = [];
        $folders = [];

        $id = $this->extractId($identifier);
        $continue = true;
        $top = CelumClient::API_MAX_ASSET_CHUNK;

        $folderInfoReturnValue = ['info' => null, 'children' => [], 'assets' => []];
        try {
            for ($skip = 0; $continue; $skip += $top) {
                $continue = false;
                $request = 'Nodes(' . $id . ')?$expand=children($select=id,name%3B$top=' . $top . '%3B$skip=' . $skip . ')&$select=id,name,children,assets,modificationInformation';
                $this->log->debug('request: GET:' . $request);

                $response = $this->client->request('GET', $request, $this->options)->getBody();
                if ($response) {
                    $response = json_decode((string)$response, true);
                    if ($skip == 0) {
                        $folderInfoReturnValue = ['info' => ['identifier' => $identifier, 'name' => $this->extractName($response['name']), 'storage' => $this->storage, 'mtime' => strtotime($response['modificationInformation']['lastModificationDateTime'])], 'children' => [], 'assets' => []];
                    }
                    if (isset($response['children'])) {
                        $c = count($response['children']);
                        if ($c > 0) {
                            foreach ($response['children'] as $child) {
                                $fi = $identifier . $child['id'] . '/';
                                $folderInfoReturnValue['children'][] = $fi;

                                $n = $this->extractName($child['name']);
                                $foldernames[$n] = $fi;
                                $folders[] = ['name' => $n, 'identifier' => $fi];
                            }
                            if ($c == $top) {
                                $continue = true;
                            }
                        }
                    }
                } elseif ($skip == 0) {
                    // no response on first request, exit without data
                    return ['info' => null, 'children' => [], 'assets' => []];
                }
            }

            // pass additional information in reurnvalue // ToDo: Refactor
            $folderInfoReturnValue['xfoldernames'] = $foldernames;
            $folderInfoReturnValue['xfolders'] = $folders;
        } catch (GuzzleException $exception) {
            $this->log->error($exception->getMessage());
        }
        return [$folderInfoReturnValue, $foldernames, $folders];
    }

    /**
     * @param $identifier
     * @return array
     */
    private function querySubfolderAndAssets(string $identifier): array
    {
        $filenames = [];
        $foldernames = [];
        $files = [];
        $folders = [];

        $id = $this->extractId($identifier);
        $continue = true;
        $top = CelumClient::API_MAX_ASSET_CHUNK;

        $folderInfoReturnValue = ['info' => null, 'children' => [], 'assets' => []];
        try {
            for ($skip = 0; $continue; $skip += $top) {
                $continue = false;
                $request = 'Nodes(' . $id . ')?$expand=children($select=id,name%3B$top=' . $top . '%3B$skip=' . $skip . '),assets($select=id,name,fileInformation,fileProperties,modificationInformation,previewInformation,fileCategory' . $this->fieldSelect . '%3B$expand=publicUrls%3B$top=' . $top . '%3B$skip=' . $skip . ')&$select=id,name,children,assets,modificationInformation';
                $this->log->debug('request: GET:' . $request);
                $response = $this->client->request('GET', $request, $this->options)->getBody();
                if ($response) {
                    $response = json_decode((string)$response, true);
                    if ($skip == 0) {
                        $folderInfoReturnValue = [
                            'info' => [
                                'identifier' => $identifier,
                                'name' => $this->extractName($response['name']),
                                'storage' => $this->storage,
                                'mtime' => strtotime($response['modificationInformation']['lastModificationDateTime'])
                            ],
                            'children' => [],
                            'assets' => []
                        ];
                    }
                    if (isset($response['children'])) {
                        $c = count($response['children']);
                        if ($c > 0) {
                            foreach ($response['children'] as $child) {
                                $fi = $identifier . $child['id'] . '/';
                                $folderInfoReturnValue['children'][] = $fi;

                                $n = $this->extractName($child['name']);
                                $foldernames[$n] = $fi;
                                $folders[] = ['name' => $n, 'identifier' => $fi];
                            }
                            if ($c == $top) {
                                $continue = true;
                            }
                        }
                    }
                    if (isset($response['assets'])) {
                        $c = count($response['assets']);
                        if ($c > 0) {
                            foreach ($response['assets'] as $asset) {
                                $fi = $identifier . $asset['id'];
                                $folderInfoReturnValue['assets'][] = $fi;
                                $a = $this->toAsset($asset, $fi);
                                $files[] = $a;
                                $filenames[$a['info']['name']] = $fi;
                            }
                            if ($c == $top) {
                                $continue = true;
                            }
                        }
                    }
                } elseif ($skip == 0) {
                    // no response on first request, exit without data
                    return [['info' => null, 'children' => [], 'assets' => []], null, null, null, null];
                }
            }
        } catch (GuzzleException $exception) {
            $this->log->error($exception->getMessage());
        }
        return [$folderInfoReturnValue, $foldernames, $folders, $filenames, $files];
    }

    /**
     *  returns an array of the value selected for extraction or the folder info itself if nothing is specified
     * @param $identifier
     * @param $extract     string 'filename', 'foldername', 'file', 'folder' or ''
     * @return array|mixed
     */
    public function getFolderInfo(string $identifier, string $extract = ''): array
    {
        $key = str_replace('/', '_', $identifier);
        if (!$this->cache->has($key . $extract)) {
            if ($identifier == '/') {
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

    public function getFileInfo($identifier)
    {
        $key = str_replace('/', '_', $identifier);
        if (!$this->cache->has($key)) {
            $request = 'Assets(' . $this->extractId($identifier) . ')?$select=id,name,fileInformation,fileProperties,modificationInformation,previewInformation,fileCategory' . $this->fieldSelect . '&$expand=publicUrls';
            $this->log->debug('request: GET:' . $request);
            $response = $this->client->request('GET', $request, $this->options)->getBody();
            if ($response) {
                $response = json_decode((string)$response, true);
                $this->cache->set($key, $this->toAsset($response, $identifier), [], $this->cacheLifetime);
            } else {
                $this->cache->set($key, ['info' => null], [], 60); // short cache on error
            }
        }
        $this->log->debug("getFileInfo($identifier)" . json_encode($this->cache->get($key)));
        return $this->cache->get($key);
    }

    private function toAsset(array &$arr, string $identifier)
    {
        $type = $arr['fileCategory'];
        $format = (($type == 'image') ? $this->imageFormat : (($type == 'video') ? $this->videoFormat : $this->othersFormat));
        foreach ($arr['fileProperties'] as $prop) {
            if ($prop['name'] === 'width') {
                $width = $prop['value'];
            } elseif ($prop['name'] === 'height') {
                $height = $prop['value'];
            }
        }
        if ($width and $height) {
            $max = 0;
            if ($format === 'thmb') {
                $max = 250;
            } elseif ($format === 'prvw') {
                $max = 1024;
            } elseif ($format === 'largeprvw') {
                $max = 3000;
            }
            if (($max > 0) and (($width > $max) or ($height > $max))) {
                if ($width > $height) {
                    $height = intval($height * $max / $width);
                    $width = $max;
                } else {
                    $width = intval($width * $max / $height);
                    $height = $max;
                }
            }
        } else {
            $width = 0;
            $height = 0;
        }
        $publicUrl = false;
        if (($type == 'image') or ($type == 'video')) {
            // echo $this->description . " " . $this->provider . " " . json_encode($response['publicUrls']) . "; ";
            foreach ($arr['publicUrls'] as $purl) {
                if (($purl['provider'] == $this->provider[$type]) and ($purl['description'] == $this->description[$type])) {
                    $publicUrl = $purl['url'];
                }
            }
        }
        if (!$publicUrl) {
            $id = $this->extractId($identifier);
            $publicUrl = $this->directDownload . 'format=' . $format . '&id=' . $id;
            if ($this->secret) {
                $publicUrl .= '&token=' . hash('sha256', $id . $this->secret);
            }
        }
        $name = $arr['name'];
        if ($format === 'prvw' or $format === 'largeprvw' or $format === 'thumb') {
            $ext = '.jpg';
        } else {
            $ext = '.' . $arr['fileInformation']['fileExtension'];
        }
        if (substr($name, -strlen($ext)) !== $ext) {
            if (substr($name, -1) === '.') {
                $name .= substr($ext, 1);
            } else {
                $name .= $ext;
            }
        }
        return [
            'info' => [
                'identifier' => $identifier,
                'identifier_hash' => sha1($identifier),
                'folder_hash' => sha1(PathUtility::dirname($identifier)),
                'name' => $name,
                'title' => $name,
                'storage' => $this->storage,
                'size' => $arr['fileInformation']['originalFileSize'],
                'width' => $width,
                'height' => $height,
                'description' => $this->getInfoFieldValue($this->descriptionFieldName, $arr),
                'alternative' => $this->getInfoFieldValue($this->alternativeFieldName, $arr),
                'mimetype' => $type . '/' . $arr['fileInformation']['fileExtension'],
                'ctime' => strtotime($arr['modificationInformation']['creationDateTime']),
                'mtime' => strtotime($arr['modificationInformation']['lastModificationDateTime']),
            ],
            'preview' => $arr['previewInformation']['previewUrl'],
            'thumbnail' => $arr['previewInformation']['thumbUrl'],
            'publicUrl' => $publicUrl,
            'extension' => $arr['fileInformation']['fileExtension']
        ];
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

    /**
     * @param $identifier
     * @param $url
     * @param $description
     * @throws GuzzleException
     */
    public function addPublicUrl(string $identifier, string $url, $description): void
    {
        if (!$this->token) {
            return;
        }
        $clientUrl = $this->celumUrl . '/infofield/setter?token=' . urlencode($this->token) . '&asset=' . $this->extractId($identifier) . '&instance=' . str_replace(' ', '_', $description);
        if ($this->writePublicUrls) {
            $clientUrl .= '&provider=TYPO3&description=' . urldecode($description) . '&publicurl=' . urlencode($url);
        }
        if ($this->infoFieldId) {
            if ($this->nodeId) {
                $clientUrl .= '&noderef-' . $this->infoFieldId . '=' . $this->nodeId;
            } else {
                $clientUrl .= '&info-' . $this->infoFieldId . '=true';
            }
        }
        $this->client->request('POST', $clientUrl, $this->postOptions);
    }

    /**
     * @throws GuzzleException
     */
    public function deletePublicUrl(string $identifier, string $description, bool $stillUsed): void
    {
        if (!$this->token) {
            return;
        }
        $url = $this->celumUrl . '/infofield/setter?token=' . urlencode($this->token) . '&asset=' . $this->extractId($identifier) . '&instance=' . str_replace(' ', '_', $description);
        if ($this->writePublicUrls) {
            $url .= '&provider=TYPO3&description=' . urldecode($description) . '&publicurl=delete';
        }
        if ($this->infoFieldId and !$stillUsed) {
            if ($this->nodeId) {
                $url .= '&noderef-' . $this->infoFieldId . '-remove=' . $this->nodeId;
            } else {
                $url .= '&info-' . $this->infoFieldId . '=false';
            }
        }
        $this->client->request('POST', $url, $this->postOptions);
    }

    /**
     * @param $identifier
     * @param $type
     * @return mixed
     */
    public function getUrl(string $identifier, string $type = 'publicUrl'): mixed
    {
        if (substr($identifier, 0, 5) === 'thumb') {
            $type = 'thumbnail';
            $identifier = substr($identifier, 5);
        }
        $ret = $this->getFileInfo($identifier)[$type];
        $this->log->debug("getUrl($identifier, $type): $ret");
        return $ret;
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
