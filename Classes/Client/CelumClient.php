<?php
/**
 * Created by PhpStorm.
 * User: CMA
 * Date: 05/11/2018
 * Time: 13:37
 */

namespace Brix\CelumFal\Client;

use Brix\CelumFal\Driver\CelumDriver;
use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use Brix\CelumFal\Utility\Cache;
use GuzzleHttp\Client;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class CelumClient {

	protected $celumUrl;
    protected $cora;
    protected $locale;
    protected $defaultLocale;
    /** @var FrontendInterface */
    protected $cache;
    /** @var Logger */
    protected $log;
    protected $storage;
    protected $directDownload;
    private $provider;
    private $description;
    private $secret;
    private $client;
    private $options;
    private $postOptions;
    private $imageFormat;
    private $videoFormat;
    private $othersFormat;
    private $lifetime;
    private $token;
    private $writePublicUrls;
    private $infoFieldId;
    private $nodeId;
    private $descriptionFieldName;
    private $alternativeFieldName;
    private $fieldSelect = '';
    private $roots;

    public function __construct(array $config, $storage) {
        if (empty($config['licenseKey'])) {
            throw new InvalidConfigurationException('No licenseKey given');
        }

        if (empty($config['celumApiKey'])) {
            throw new InvalidConfigurationException('No celumApiKey given');
        }

        $this->log = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->log->debug("__construct(" . json_encode($config) . ")");
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
        $this->lifetime = intval($config['cacheLifetimeInMinutes']);
        if (($this->lifetime <= 0) || ($this->lifetime >= 30))
            $this->lifetime = 29;
        $this->lifetime *= 60;
        $this->token = $config['infoFieldSetterToken'];
        $this->writePublicUrls = $config['writePublicUrls'];
        $this->infoFieldId = $config['informationFieldId'];
        $this->nodeId = $config['nodeId'];
        $this->descriptionFieldName = $config['descriptionFieldName'];
        if ($this->descriptionFieldName)
            $this->fieldSelect .= ',informationFieldValues/' . $this->descriptionFieldName;
        $this->alternativeFieldName = $config['alternativeTextFieldName'];
        if ($this->alternativeFieldName)
            $this->fieldSelect .= ',informationFieldValues/' . $this->alternativeFieldName;
        $this->roots = preg_split('/\\s*,\\s*/', trim($config['roots']));
        foreach ($this->roots as $key => $val)
            $this->roots[$key] = "/$val/";
    }

    public function extractId($identifier) {
        return basename(rtrim($identifier, '/'));
    }

    public function extractName(&$names) {
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

    // returns an array of the value selected for extraction or the folder info itself if nothing is specified
    // extract: 'filename', 'foldername', 'file', 'folder'
    // storage id only required for root folder
    public function getFolderInfo($identifier, $extract = '') {
        //$semaphore = sem_get(($identifier == '/' ? 0 : intval($this->extractId($identifier))) + 10); // 1 seems to be used by TYPO3
        //sem_acquire($semaphore);
        //try {
            $key = str_replace('/', '_', $identifier);
            if (!$this->cache->has($key)) {
                if ($identifier == '/') {
                    $this->cache->set($key, ['info' => ['identifier' => '/', 'name' => 'CELUM', 'storage' => $this->storage], 'assets' => [], 'children' => $this->roots], [], $this->lifetime);
                    $this->cache->set($key . 'file', [], [], $this->lifetime);
                    $this->cache->set($key . 'filename', [], [], $this->lifetime);
                    $folders = [];
                    $foldernames = [];
                    foreach ($this->roots as $root) {
                        $f = $this->getFolderInfo($root);
                        $folders[] = ['identifier' => $root, 'name' => $f['name']];
                        $foldernames[$f['name']] = $root;
                    }
                    $this->cache->set($key . 'folder', $folders, [], $this->lifetime);
                    $this->cache->set($key . 'foldername', $foldernames, [], $this->lifetime);
                } else {
                    $filenames = [];
                    $foldernames = [];
                    $files = [];
                    $folders = [];
                    $id = $this->extractId($identifier);
                    $continue = true;
                    $top = 200;
                    for ($skip = 0; $continue; $skip += $top) {
                        $continue = false;
                        $response = $this->client->request('GET', 'Nodes(' . $id . ')?$expand=children($select=id,name%3B$top=' . $top . '%3B$skip=' . $skip . '),assets($select=id,name,fileInformation,fileProperties,modificationInformation,previewInformation,fileCategory' . $this->fieldSelect . '%3B$expand=publicUrls%3B$top=' . $top . '%3B$skip=' . $skip . ')&$select=id,name,children,assets', $this->options)->getBody();
                        if ($response) {
                            $response = json_decode($response, true);
                            if ($skip == 0)
                                $data = ['info' => ['identifier' => $identifier, 'name' => $this->extractName($response['name']), 'storage' => $this->storage], 'children' => [], 'assets' => []];
                            if (isset($response['children'])) {
                                $c = count($response['children']);
                                if ($c > 0) {
                                    foreach ($response['children'] as $child) {
                                        $fi = $identifier . $child['id'] . '/';
                                        $n = $this->extractName($child['name']);
                                        $data['children'][] = $fi;
                                        $foldernames[$n] = $fi;
                                        $folders[] = ['name' => $n, 'identifier' => $fi];
                                    }
                                    if ($c == $top)
                                        $continue = true;
                                }
                            }
                            if (isset($response['assets'])) {
                                $c = count($response['assets']);
                                if ($c > 0) {
                                    foreach ($response['assets'] as $asset) {
                                        $fi = $identifier . $asset['id'];
                                        $data['assets'][] = $fi;
                                        $a = $this->toAsset($asset, $fi);
                                        $this->cache->set($key . $asset['id'], $a, [], $this->lifetime);
                                        $files[] = $a;
                                        $filenames[$a['info']['name']] = $fi;
                                    }
                                    if ($c == $top)
                                        $continue = true;
                                }
                            }
                        } elseif ($skip == 0) {
                            $this->cache->set($key, ['info' => null, 'children' => [], 'assets' => []], [], 60);
                            return $this->cache->get($key);
                        }
                    }
                    $this->cache->set($key, $data, [], $this->lifetime);
                    $this->cache->set($key . 'file', $files, [], $this->lifetime);
                    $this->cache->set($key . 'filename', $filenames, [], $this->lifetime);
                    $this->cache->set($key . 'folder', $folders, [], $this->lifetime);
                    $this->cache->set($key . 'foldername', $foldernames, [], $this->lifetime);
                }
            }
            $this->log->debug("getFolderInfo($identifier, $extract): " . json_encode($this->cache->get($key . $extract)));
            return $this->cache->get($key . $extract);
        //} finally {
        //    sem_release($semaphore);
        //}
    }

    public function getFileInfo($identifier) {
        $key = str_replace('/', '_', $identifier);
        if (!$this->cache->has($key)) {
            $response = $this->client->request('GET', 'Assets(' . $this->extractId($identifier) . ')?$select=id,name,fileInformation,fileProperties,modificationInformation,previewInformation,fileCategory' . $this->fieldSelect . '&$expand=publicUrls', $this->options)->getBody();
            if ($response) {
                $response = json_decode($response, true);
                $this->cache->set($key, $this->toAsset($response, $identifier), [], $this->lifetime);
            } else {
                $this->cache->set($key, ['info' => null], [], 60); // short cache on error
            }
        }
        $this->log->debug("getFileInfo($identifier)" . json_encode($this->cache->get($key)));
        return $this->cache->get($key);
    }

    private function toAsset(&$arr, $identifier) {
        $type = $arr['fileCategory'];
        $format = ($type == 'image' ? $this->imageFormat : ($type == 'video' ? $this->videoFormat : $this->othersFormat));
        foreach ($arr['fileProperties'] as $prop) {
            if ($prop['name'] === 'width')
                $width = $prop['value'];
            elseif ($prop['name'] === 'height')
                $height = $prop['value'];
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
                if (($purl['provider'] == $this->provider[$type]) and ($purl['description'] == $this->description[$type]))
                    $publicUrl = $purl['url'];
            }
        }
        if (!$publicUrl) {
            $id = $this->extractId($identifier);
            $publicUrl = $this->directDownload . 'format=' . $format . '&id=' . $id;
            if ($this->secret)
                $publicUrl .= '&token=' . hash('sha256', $id . $this->secret);
        }
        $name = $arr['name'];
        if ($format === 'prvw' or $format === 'largeprvw' or $format === 'thumb')
            $ext = '.jpg';
        else
            $ext = '.' . $arr['fileInformation']['fileExtension'];
        if (substr($name, -strlen($ext)) !== $ext) {
            if (substr($name, -1) === '.')
                $name .= substr($ext, 1);
            else
                $name .= $ext;
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

    private function getInfoFieldValue($name, $arr) {
        if (!$arr['informationFieldValues'])
            return '';
        $val = $arr['informationFieldValues'][$name];
        if (!$val)
            return '';
        if (is_array($val)) {
            $v = $this->extractName($val);
            return $v == null ? '' : $v;
        } else
            return $val;
    }

    public function addPublicUrl($identifier, $url, $description) {
        if (!$this->token)
            return;
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

    public function deletePublicUrl($identifier, $description, $stillUsed) {
        if (!$this->token)
            return;
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

    public function getUrl($identifier, $type='publicUrl') {
        if (substr($identifier, 0, 5) === 'thumb') {
            $type = 'thumbnail';
            $identifier = substr($identifier, 5);
        }
        $ret = $this->getFileInfo($identifier)[$type];
        $this->log->debug("getUrl($identifier, $type): $ret");
        return $ret;
    }

	private function decode_base64($sData){
		$sBase64 = strtr($sData, '-_', '+/');
		return base64_decode($sBase64.'==');
	}

	private function decrypt($sData){
		$secretKey = "ZbMchtd9DivzjPDi5QIio1iVERFnNZiSE33QKY3Gw9rYfCNLFiKloJQt3zi4";
		$sResult = '';
		$sData   = $this->decode_base64($sData);
		for($i=0;$i<strlen($sData);$i++){
			$sChar    = substr($sData, $i, 1);
			$sKeyChar = substr($secretKey, ($i % strlen($secretKey)) - 1, 1);
			$sChar    = chr(ord($sChar) - ord($sKeyChar));
			$sResult .= $sChar;
		}
		return $sResult;
	}
}