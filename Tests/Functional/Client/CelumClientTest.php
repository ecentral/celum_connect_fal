<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Functional\Client;

use Brix\CelumFal\Client\CelumClient;
use GuzzleHttp\Exception\ClientException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CelumClientTest extends FunctionalTestCase
{
    private CelumClient $client;

    /**
     * @test
     *
     * @dataProvider checkGetFolderInfoMethodDataProvider()
     */
    public function checkGetFolderInfoMethod(array $config, string $identifier, array $expectedResult, string $exceptionClassName = '', string $exceptionMessage = ''): void
    {
        $this->skipWithoutLiveCelumCredentials();
        $this->initializeClient($config);
        if ($exceptionClassName) {
            $this->expectException($exceptionClassName);
            $this->expectExceptionMessage($exceptionMessage);
        }
        $folderInfo = $this->client->getFolderInfo($identifier);
        $this->assertEquals($expectedResult, $folderInfo);
    }

    /**
     * @test
     * @dataProvider checkGetFileInfoMethodDataProvider()
     * @param array $config
     * @param string $identifier
     * @param array $expectedResult
     */
    public function checkGetFileInfoMethod(array $config, string $identifier, array $expectedResult): void
    {
        $this->skipWithoutLiveCelumCredentials();
        $this->initializeClient($config);
        $fileInfo = $this->client->getFileInfo($identifier);

        foreach (['identifier', 'identifier_hash', 'folder_hash', 'name', 'title', 'storage', 'size', 'width', 'height', 'description', 'alternative', 'mimetype', 'ctime'] as $key) {
            $this->assertEquals($expectedResult['info'][$key], $fileInfo['info'][$key]);
        }

        $this->assertEquals($expectedResult['extension'], $fileInfo['extension']);
        $this->assertEquals($expectedResult['publicUrl'], $fileInfo['publicUrl']);
        $this->assertStringStartsWith($expectedResult['preview'], $fileInfo['preview']);
        $this->assertStringStartsWith($expectedResult['thumbnail'], $fileInfo['thumbnail']);
    }

    /**
     * @test
     * @dataProvider checkGetUrlMethodDataProvider()
     * @param array $config
     * @param string $identifier
     * @param string|null $expectedResult
     */
    public function checkGetUrlMethod(array $config, string $identifier, string $type, ?string $expectedResult, ?string $errorClass = null): void
    {
        $this->skipWithoutLiveCelumCredentials();
        $this->initializeClient($config);
        if ($errorClass) {
            $this->expectException($errorClass);
        }

        if (!empty($type)) {
            $url = $this->client->getUrl($identifier, $type);
        } else {
            $url = $this->client->getUrl($identifier);
        }

        if ($expectedResult) {
            $this->assertStringStartsWith($expectedResult, $url);
        } else {
            $this->assertNull($url);
        }
    }

    /**
     * @test
     * @dataProvider checkAddPublicUrlMethodDataProvider()
     * @param array $config
     * @param string $identifier
     * @param string $url
     * @param string $description
     */
    public function checkAddPublicUrlMethod(array $config, string $identifier, string $url, string $description)
    {
        $this->skipWithoutLiveCelumCredentials();
        $this->initializeClient($config);
        $this->client->addPublicUrl($identifier, $url, $description);
    }

    /**
     * These tests hit a real CELUM instance and assert against its actual content,
     * so they only run when live credentials are provided via environment variables.
     */
    protected function skipWithoutLiveCelumCredentials(): void
    {
        if (!getenv('celum_celumHost')) {
            self::markTestSkipped('Requires a live CELUM instance (set celum_celumHost, celum_apiKey, celum_user, celum_password env vars).');
        }
    }

    protected function initializeClient(?array $config = null, int $storage = 1): void
    {
        $clientConfig = [
            'celumHost' => getenv('celum_celumHost') ?: '',
            'celumApiKey' => getenv('celum_apiKey') ?: '',
            'celumUser' => getenv('celum_user') ?: '',
            'celumPassword' => getenv('celum_password') ?: '',
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

    public static function checkGetFolderInfoMethodDataProvider(): array
    {
        return [
            'default config' => [
                [],
                '/',
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
                '/',
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
                '/',
                [],
                ClientException::class,
                'NodeId: id must be > 0'
            ],
            'wrong roots' => [
                [
                    'roots' => '1'
                ],
                '/',
                [],
                ClientException::class,
                'NOT_FOUND_ENTITY_OF_COLLECTION_WITH_IDENTIFIER'
            ],
            'subfolders with default config' => [
                [],
                '/11084/',
                [
                    'info' => [
                        'identifier' => '/11084/',
                        'name' => 'Testbilder',
                        'storage' => null
                    ],
                    'assets' => [],
                    'children' => [
                        '/11084/11085/',
                        '/11084/11086/',
                        '/11084/11087/',
                        '/11084/11088/',
                        '/11084/11089/',
                        '/11084/11090/'
                    ]
                ]
            ],
            'subfolders with english locale' => [
                [
                    'locale' => 'en'
                ],
                '/11084/',
                [
                    'info' => [
                        'identifier' => '/11084/',
                        'name' => 'Test images',
                        'storage' => null
                    ],
                    'assets' => [],
                    'children' => [
                        '/11084/11085/',
                        '/11084/11086/',
                        '/11084/11087/',
                        '/11084/11088/',
                        '/11084/11089/',
                        '/11084/11090/'
                    ]
                ]
            ],
            'subfolders with assets and default config' => [
                [],
                '/11084/11086/',
                [
                    'info' => [
                        'identifier' => '/11084/11086/',
                        'name' => 'Menschen',
                        'storage' => null
                    ],
                    'assets' => [
                        '/11084/11086/1492',
                        '/11084/11086/1494',
                        '/11084/11086/1495',
                        '/11084/11086/1496',
                        '/11084/11086/1497',
                        '/11084/11086/1498',
                        '/11084/11086/1499',
                        '/11084/11086/1500',
                        '/11084/11086/1501',
                        '/11084/11086/1502',
                        '/11084/11086/1503',
                        '/11084/11086/1504',
                        '/11084/11086/1505',
                        '/11084/11086/1506',
                        '/11084/11086/1508',
                        '/11084/11086/1509',
                        '/11084/11086/1510',
                        '/11084/11086/1511',
                        '/11084/11086/1512',
                        '/11084/11086/1513',
                        '/11084/11086/1514',
                        '/11084/11086/1515',
                        '/11084/11086/1516',
                        '/11084/11086/1517',
                        '/11084/11086/1518',
                        '/11084/11086/1519',
                        '/11084/11086/1520',
                    ],
                    'children' => []
                ]
            ],
            'subfolders with assets in thmb' => [
                [
                    'downloadFormat' => 'thmb'
                ],
                '/11084/11086/',
                [
                    'info' => [
                        'identifier' => '/11084/11086/',
                        'name' => 'Menschen',
                        'storage' => null
                    ],
                    'assets' => [
                        '/11084/11086/1492',
                        '/11084/11086/1494',
                        '/11084/11086/1495',
                        '/11084/11086/1496',
                        '/11084/11086/1497',
                        '/11084/11086/1498',
                        '/11084/11086/1499',
                        '/11084/11086/1500',
                        '/11084/11086/1501',
                        '/11084/11086/1502',
                        '/11084/11086/1503',
                        '/11084/11086/1504',
                        '/11084/11086/1505',
                        '/11084/11086/1506',
                        '/11084/11086/1508',
                        '/11084/11086/1509',
                        '/11084/11086/1510',
                        '/11084/11086/1511',
                        '/11084/11086/1512',
                        '/11084/11086/1513',
                        '/11084/11086/1514',
                        '/11084/11086/1515',
                        '/11084/11086/1516',
                        '/11084/11086/1517',
                        '/11084/11086/1518',
                        '/11084/11086/1519',
                        '/11084/11086/1520',
                    ],
                    'children' => []
                ]
            ],
            'subfolders with assets in prvw' => [
                [
                    'downloadFormat' => 'prvw'
                ],
                '/11084/11086/',
                [
                    'info' => [
                        'identifier' => '/11084/11086/',
                        'name' => 'Menschen',
                        'storage' => null
                    ],
                    'assets' => [
                        '/11084/11086/1492',
                        '/11084/11086/1494',
                        '/11084/11086/1495',
                        '/11084/11086/1496',
                        '/11084/11086/1497',
                        '/11084/11086/1498',
                        '/11084/11086/1499',
                        '/11084/11086/1500',
                        '/11084/11086/1501',
                        '/11084/11086/1502',
                        '/11084/11086/1503',
                        '/11084/11086/1504',
                        '/11084/11086/1505',
                        '/11084/11086/1506',
                        '/11084/11086/1508',
                        '/11084/11086/1509',
                        '/11084/11086/1510',
                        '/11084/11086/1511',
                        '/11084/11086/1512',
                        '/11084/11086/1513',
                        '/11084/11086/1514',
                        '/11084/11086/1515',
                        '/11084/11086/1516',
                        '/11084/11086/1517',
                        '/11084/11086/1518',
                        '/11084/11086/1519',
                        '/11084/11086/1520',
                    ],
                    'children' => []
                ]
            ],

        ];
    }

    public static function checkGetFileInfoMethodDataProvider(): array
    {
        return [
            'default config' => [
                [],
                '/11084/11086/1494',
                [
                    'info' => [
                        'identifier' => '/11084/11086/1494',
                        'identifier_hash' => '9d29e53f01364d0af173ed0e1a08b96c421b6652',
                        'folder_hash' => '87a152fb1c7cd7fb0f73b5d3232ae238dad1d9d8',
                        'name' => 'people crossing a street.jpg',
                        'title' => 'people crossing a street.jpg',
                        'storage' => null,
                        'size' => 3843034,
                        'width' => 3000,
                        'height' => 1996,
                        'description' => '',
                        'alternative' => '',
                        'mimetype' => 'image/jpg',
                        'ctime' => 1630680583
                    ],
                    'preview' => 'https://contenthub-demo.brix.ch/cora/download?ticket=',
                    'publicUrl' => 'https://contenthub-demo.brix.ch/direct/download?format=largeprvw&id=1494',
                    'thumbnail' => 'https://contenthub-demo.brix.ch/cora/download?ticket=',
                    'extension' => 'jpg'
                ]
            ],
            'english locale' => [
                [
                    'locale' => 'en'
                ],
                '/11084/11086/1494',
                [
                    'info' => [
                        'identifier' => '/11084/11086/1494',
                        'identifier_hash' => '9d29e53f01364d0af173ed0e1a08b96c421b6652',
                        'folder_hash' => '87a152fb1c7cd7fb0f73b5d3232ae238dad1d9d8',
                        'name' => 'people crossing a street.jpg',
                        'title' => 'people crossing a street.jpg',
                        'storage' => null,
                        'size' => 3843034,
                        'width' => 3000,
                        'height' => 1996,
                        'description' => '',
                        'alternative' => '',
                        'mimetype' => 'image/jpg',
                        'ctime' => 1630680583,
                    ],
                    'preview' => 'https://contenthub-demo.brix.ch/cora/download?ticket=',
                    'publicUrl' => 'https://contenthub-demo.brix.ch/direct/download?format=largeprvw&id=1494',
                    'thumbnail' => 'https://contenthub-demo.brix.ch/cora/download?ticket=',
                    'extension' => 'jpg'
                ]
            ],
        ];
    }

    public static function checkGetUrlMethodDataProvider(): array
    {
        return [
            'default type' => [
                [],
                '/11084/11086/1494',
                '',
                'https://contenthub-demo.brix.ch/direct/download?format=largeprvw&id=1494'
            ],
            'publicUrl' => [
                [],
                '/11084/11086/1494',
                'publicUrl',
                'https://contenthub-demo.brix.ch/direct/download?format=largeprvw&id=1494'
            ],
            'thumbnail' => [
                [],
                '/11084/11086/1494',
                'thumbnail',
                'https://contenthub-demo.brix.ch/cora/download?ticket='
            ],
            'thumb' => [
                [],
                'thumb/11084/11086/1494',
                '',
                'https://contenthub-demo.brix.ch/cora/download?ticket='
            ],
            'thumb-wrong' => [
                [],
                'thumb/foo/bar',
                '',
                '',
                ClientException::class
            ],
            'preview' => [
                [],
                '/11084/11086/1494',
                'preview',
                'https://contenthub-demo.brix.ch/cora/download?ticket='
            ],
            'fake type' => [
                [],
                '/11084/11086/1494',
                'fake',
                null
            ],
        ];
    }

    public static function checkAddPublicUrlMethodDataProvider(): array
    {
        return [
            '' => [
                [],
                '/11084/11086/1494',
                'https://typo3.org',
                'Website of TYPO3'
            ]
        ];
    }
}
