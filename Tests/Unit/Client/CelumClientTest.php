<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Unit\Client;

use Brix\CelumFal\Client\CelumClient;
use Brix\CelumFal\Tests\Unit\Fixtures\LicenseKeyFixture;
use ReflectionProperty;
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

    /**
     * @test
     *
     * @dataProvider configDataProvider()
     */
    public function initializeClientWithDifferentConfigs(array $config): void
    {
        $this->initializeClient($config);
        $this->assertInstanceOf(CelumClient::class, $this->client);
    }

    protected function initializeClient(?array $config = null, int $storage = 1): void
    {
        $clientConfig = [
            'celumHost' => getenv('celum_celumHost') ?: 'https://demo.celum.cloud/content-api/v1',
            'celumApiKey' => getenv('celum_apiKey') ?: '',
            'locale' => getenv('celum_locale') ?: 'de',
            'defaultLocale' => getenv('celum_defaultLocale') ?: 'en',
            'cacheLifetimeInMinutes' => getenv('celum_cacheLifetimeInMinutes') ?: '',
            'roots' => getenv('celum_roots') ?: '',
        ];

        if (is_array($config)) {
            ArrayUtility::mergeRecursiveWithOverrule($clientConfig, $config);
        }

        $this->client = new CelumClient($clientConfig, $storage);
    }

    /**
     * CELUM answers 401 "No authentication profile found for API key" when the
     * key is sent with a prefix, so it has to go out verbatim.
     *
     * @test
     */
    public function apiKeyIsSentWithoutPrefix(): void
    {
        $this->initializeClient([
            'licenseKey' => LicenseKeyFixture::encode('https://demo.celum.cloud', time() + 3600),
            'celumApiKey' => 'someApiKey',
        ]);

        self::assertTrue($this->client->isAvailable());

        $clientConfiguration = (new ReflectionProperty(CelumClient::class, 'clientConfiguration'))
            ->getValue($this->client);

        self::assertSame('someApiKey', $clientConfiguration->getApiKeyWithPrefix('X-API-KEY'));
    }


    /**
     * @return \string[][]
     */
    public static function extractIdDataProvider(): array
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

    public static function extractNameDataProvider(): array
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

    public static function configDataProvider(): array
    {
        return [
            'default config' => [
                [],
            ],
            'custom roots' => [
                ['roots' => '6101,6102'],
            ],
            'custom cache lifetime' => [
                ['cacheLifetimeInMinutes' => 15],
            ],
        ];
    }
}
