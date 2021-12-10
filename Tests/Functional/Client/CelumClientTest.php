<?php
namespace Brix\CelumFal\Tests\Functional\Client;

use Brix\CelumFal\Client\CelumClient;
use Brix\CelumFal\Exceptions\InvalidConfigurationException;
use GuzzleHttp\Exception\ClientException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CelumClientTest extends FunctionalTestCase
{
    private CelumClient $client;

    /**
     * @test
     *
     * @dataProvider checkRootFolderAndChildrenDataProvider()
     */
    public function checkRootFolderAndChildren(array $config, bool $shouldSucceed, array $expectedResult, string $exceptionClassName = '', string $exceptionMessage = ''): void
    {
        $this->initializeClient($config);
        if (!$shouldSucceed) {
            $this->expectException($exceptionClassName);
            $this->expectExceptionMessage($exceptionMessage);
        }
        $folderInfo = $this->client->getFolderInfo('/');
        $this->assertEquals($expectedResult, $folderInfo);
    }

    protected function initializeClient(?array $config = null, $storage = null): void
    {
        $clientConfig = [
            'licenseKey' => getenv('celum_licenseKey') ?: '',
            'locale' => getenv('celum_locale') ?: 'de',
            'defaultLocale' => getenv('celum_defaultLocale') ?: 'en',
            'downloadFormat' => getenv('celum_downloadFormat') ?: 'largeprvw',
            'publicURLsProviderVideo' => getenv('celum_publicURLsProviderVideo') ?: '',
            'publicURLsProviderImage' => getenv('celum_publicURLsProviderImage') ?: '',
            'publicURLsDescriptionVideo' => getenv('celum_publicURLsDescriptionVideo') ?: '',
            'publicURLsDescriptionImage' => getenv('celum_publicURLsDescriptionImage') ?: '',
            'directDownloadSecret' => getenv('celum_directDownloadSecret') ?: '',
            'celumApiKey' => getenv('celum_apiKey') ?: '',
            'cacheLifetimeInMinutes' => getenv('celum_cacheLifetimeInMinutes') ?: '',
            'infoFieldSetterToken' => getenv('celum_infoFieldSetterToken') ?: '',
            'writePublicUrls' => getenv('celum_writePublicUrls') ?: '',
            'informationFieldId' => getenv('celum_informationFieldId') ?: '',
            'nodeId' => getenv('celum_nodeId') ?: '',
            'descriptionFieldName' => getenv('celum_descriptionFieldName') ?: '',
            'alternativeTextFieldName' => getenv('celum_alternativeTextFieldName') ?: '',
            'roots' => getenv('celum_roots') ?: '',
        ];

        if (is_array($config)) {
            ArrayUtility::mergeRecursiveWithOverrule($clientConfig, $config);
        }
        $this->client = new CelumClient($clientConfig, $storage);
    }

    public function checkRootFolderAndChildrenDataProvider(): array
    {
        return [
            'default config' => [
                [],
                true,
                [
                    'info' => [
                        'identifier' => '/',
                        'name' => 'CELUM',
                        'storage' => null
                    ],
                    'assets' => [],
                    'children' => [
                        '/11084/'
                    ]
                ]
            ],
            'locale en' => [
                [
                    'locale' => 'en'
                ],
                true,
                [
                    'info' => [
                        'identifier' => '/',
                        'name' => 'CELUM',
                        'storage' => null
                    ],
                    'assets' => [],
                    'children' => [
                        '/11084/'
                    ]
                ]
            ],
            'invalid roots' => [
                [
                    'roots' => '-1'
                ],
                false,
                [],
                ClientException::class,
                'NodeId: id must be > 0'
            ],
            'wrong roots' => [
                [
                    'roots' => '1'
                ],
                false,
                [],
                ClientException::class,
                'NOT_FOUND_ENTITY_OF_COLLECTION_WITH_IDENTIFIER'
            ],
        ];
    }
}
