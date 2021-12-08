<?php
namespace Brix\CelumFal\Tests\Unit\Client;

use Brix\CelumFal\Client\CelumClient;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class CelumClientTest extends UnitTestCase
{
    private CelumClient $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->resetSingletonInstances = true;
    }

    /**
     * @test
     *
     * @dataProvider extractIdDataProvider()
     */
    public function checkOutputExtractId(string $id, string $expectedResult): void
    {
        $this->initializeClient();
        $this->assertEquals($expectedResult, $this->client->extractId($id));
    }

    /**
     * @test
     *
     * @dataProvider extractNameDataProvider()
     */
    public function checkOutputExtractName(string $locale, array $names, ?string $expectedResult): void
    {
        $this->initializeClient(['locale' => $locale]);
        $this->assertEquals($expectedResult, $this->client->extractName($names));
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

    /**
     * @return \string[][]
     */
    public function extractIdDataProvider(): array
    {
        return [
            'multiple nodes' => [
                '/11084/11088/',
                '11088'
            ],
            'single node' => [
                '/11084/',
                '11084'
            ],
            'no trailing slash' => [
                '/11084',
                '11084'
            ],
            'not starting with slash' => [
                '11084/',
                '11084'
            ]
        ];
    }

    public function extractNameDataProvider(): array
    {
        return [
            'empty array' => [
                'en',
                [],
                null
            ],
            'German' => [
                'de',
                [
                    [
                        'locale' => 'de',
                        'value' => 'Städte'
                    ],
                    [
                        'locale' => 'fr',
                        'value' => 'Villes'
                    ],
                    [
                        'locale' => 'en',
                        'value' => 'Cities'
                    ]
                ],
                'Städte'
            ],
            'French' => [
                'fr',
                [
                    [
                        'locale' => 'de',
                        'value' => 'Städte'
                    ],
                    [
                        'locale' => 'fr',
                        'value' => 'Villes'
                    ],
                    [
                        'locale' => 'en',
                        'value' => 'Cities'
                    ]
                ],
                'Villes'
            ],
            'Default locale' => [
                '',
                [
                    [
                        'locale' => 'de',
                        'value' => 'Städte'
                    ],
                    [
                        'locale' => 'fr',
                        'value' => 'Villes'
                    ],
                    [
                        'locale' => 'en',
                        'value' => 'Cities'
                    ]
                ],
                'Cities'
            ],
        ];
    }
}