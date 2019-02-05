<?php
/**
 * Created by PhpStorm.
 * User: CMA
 * Date: 05/11/2018
 * Time: 13:37
 */

namespace Brix\CelumFal;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CelumClient {

    const LIFE_TIME = 30 * 60 - 10;
	protected $celumUrl;
    protected $cora;
    protected $context;
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

    public function __construct(array $config, $storage)
    {
        $this->log = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->log->debug("__construct(" . json_encode($config) . ")");
        $this->celumUrl = rtrim($this->decrypt($config['licenseKey']));
        $this->cora = $this->celumUrl . '/cora/';
        $this->directDownload = $this->celumUrl . '/direct/download?format=prvw&id=';
        $this->provider = ['video' => $config['publicURLsProviderVideo'], 'image' => $config['publicURLsProviderImage']];
        $this->description = ['video' => $config['publicURLsDescriptionVideo'], 'image' => $config['publicURLsDescriptionImage']];
        $this->context = stream_context_create(['http' => ['method' => 'GET', 'header' => 'Authorization: celumApiKey ' . $config['celumApiKey']]]);
        $this->locale = $config['locale'];
        $this->defaultLocale = $config['defaultLocale'];
        $this->secret = $config['directDownloadSecret'];
        $this->storage = $storage;
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache(CelumDriver::EXTENSION_KEY);
    }

    protected function extractId($identifier) {
        return basename(rtrim($identifier, '/'));
    }

    protected function extractName(&$names) {
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

    public function getFolderInfo($identifier) {
        if (($identifier == '') or ($identifier == '//'))
            $identifier = '/';
        $key = str_replace('/', '_', $identifier);
        if (!$this->cache->has($key)) {
            $response = file_get_contents($this->cora . 'Nodes(' . $this->extractId($identifier) . ')?$expand=children,assets&$select=id,name,children,assets', false, $this->context);
            if ($response) {
                $response = json_decode($response, true);
                $data = ['info' => ['identifier' => $identifier, 'name' => $this->extractName($response['name']), 'storage' => $this->storage], 'children' => [],  'assets' => []];
                if (isset($response['children']) and count($response['children']) > 0) {
                    foreach ($response['children'] as $child) {
                        $data['children'][] = $identifier . $child['id'] . '/';
                    }
                }
                if (isset($response['assets']) and count($response['assets']) > 0) {
                    foreach ($response['assets'] as $asset) {
                        $data['assets'][] = $identifier . $asset['id'];
                    }
                }
                $this->cache->set($key, $data, [], self::LIFE_TIME);
            } else {
                $this->cache->set($key, ['info' => null, 'children' => [], 'assets' => []], [], self::LIFE_TIME);
            }
        }
        $this->log->debug("getFolderInfo($identifier): " . json_encode($this->cache->get($key)));
        return $this->cache->get($key);
    }

    public function getFileInfo($identifier) {
        $identifier = rtrim($identifier, '/') . '/';
        $key = str_replace('/', '_', $identifier);
        if (!$this->cache->has($key)) {
            $response = file_get_contents($this->cora . 'Assets(' . $this->extractId($identifier) . ')?$select=id,name,fileInformation,fileProperties,modificationInformation,previewInformation,fileCategory&$expand=publicUrls', false, $this->context);
            if ($response) {
                $response = json_decode($response, true);
                foreach ($response['fileProperties'] as $prop) {
                    if ($prop['name'] === 'width')
                        $width = $prop['value'];
                    elseif ($prop['name'] === 'height')
                        $height = $prop['value'];
                }
                if ($width and $height and (($width > 1024) or ($height > 1024))) {
                    if ($width > $height) {
                        $height = intval($height * 1024 / $width);
                        $width = 1024;
                    } else {
                        $width = intval($width * 1024 / $height);
                        $height = 1024;
                    }
                }
                $publicUrl = false;
                $type = $response['fileCategory'];
                if (($type == 'image') or ($type == 'video')) {
                    // echo $this->description . " " . $this->provider . " " . json_encode($response['publicUrls']) . "; ";
                    foreach ($response['publicUrls'] as $purl) {
                        if (($purl['provider'][$type] == $this->provider) and ($purl['description'][$type] == $this->description))
                            $publicUrl = $purl['url'];
                    }
                }
                if (!$publicUrl) {
                    $id = $this->extractId($identifier);
                    $publicUrl = $this->directDownload . $id;
                    if ($this->secret)
                        $publicUrl .= '&token=' . hash('sha256', $id . $this->secret);
                }
                $name = $response['name'];
                $ext = '.' . $response['fileInformation']['fileExtension'];
                if (!substr($name, -strlen($ext)) === $ext) {
                    if (substr($name, -1) === '.')
                        $name .= $response['fileInformation']['fileExtension'];
                    else
                        $name .= $ext;
                }
                $this->cache->set($key, ['info' => [
                    'identifier' => $identifier,
                    'name' => $name,
                    'storage' => $this->storage,
                    'size' => $response['fileInformation']['originalFileSize'],
                    'width' => $width,
                    'height' => $height,
                    'mimetype' =>  $type . '/' . $response['fileInformation']['fileExtension'],
                    'ctime' => strtotime($response['modificationInformation']['creationDateTime']),
                    'mtime' => strtotime($response['modificationInformation']['lastModificationDateTime']),
                ],
//                    'preview' => $response['previewInformation']['previewUrl'],
//                    'thumbnail' => $response['previewInformation']['thumbUrl'],
                    'publicUrl' => $publicUrl,
                    'extension' => $response['fileInformation']['fileExtension']
                ], [], self::LIFE_TIME);
            } else {
                $this->cache->set($key, ['info' => null], [], self::LIFE_TIME);
            }
        }
        $this->log->debug("getFileInfo($identifier)" . json_encode($this->cache->get($key)));
        return $this->cache->get($key);
    }

    public function getUrl($identifier, $type='publicUrl') {
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