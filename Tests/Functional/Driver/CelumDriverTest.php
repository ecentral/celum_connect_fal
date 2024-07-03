<?php
namespace Brix\CelumFal\Tests\Functional\Client;

use Brix\CelumFal\Driver\CelumDriver;
use GuzzleHttp\Exception\ClientException;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CelumDriverTest extends FunctionalTestCase
{
    private CelumDriver $driver;

    /**
     * @test
     */
    public function checkGetRootLevelFolder()
    {
        $this->initializeDriver();
        $this->assertEquals('/', $this->driver->getRootLevelFolder());
        $this->assertEquals('/', $this->driver->getDefaultFolder());
    }

    /**
     * @test
     * @dataProvider checkGetUrlMethodDataProvider()
     * @param array $config
     * @param string $identifier
     * @param string|null $expectedResult
     */
    public function checkGetUrlMethod(array $config, string $identifier, ?string $expectedResult, ?string $errorClass = null): void
    {
        $this->initializeDriver($config);
        if ($errorClass) {
            $this->expectException($errorClass);
        }

        $url = $this->driver->getPublicUrl($identifier);

        if ($expectedResult) {
            $this->assertStringStartsWith($expectedResult, $url);
        } else {
            $this->assertNull($url);
        }
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkCreateFolder()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->createFolder('folderName');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkRenameFolder()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->renameFolder('/', 'folderName');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkDeleteFolder()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->deleteFolder('/');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkAddFile()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->addFile('/foo.jpg', '/');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkCreateFile()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->createFile('foo.jpg', '/');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkCopyFileWithinStorage()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->copyFileWithinStorage('foo.jpg', '/', 'bar.jpg');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkRenameFile()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->renameFile('/foo.jpg', 'bar.jpg');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkReplaceFile()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->replaceFile('/foo.jpg', '/');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkSetFileContents()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->setFileContents('/foo.jpg', 'foobar');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkDeleteFile()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->deleteFile('/foo.jpg');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkMoveFileWithinStorage()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->moveFileWithinStorage('/foo.jpg', '/', 'bar.jpg');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkMoveFolderWithinStorage()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->moveFolderWithinStorage('/', '/bar/', 'foo');
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkCopyFolderWithinStorage()
    {
        $this->initializeDriver();
        $this->expectException(Exception::class);
        $this->driver->copyFolderWithinStorage('/', '/bar/', 'foo');
    }

    /**
     * @test
     */
    public function checkGetPermissions()
    {
        $this->initializeDriver();
        $this->assertEquals(['r' => true, 'w' => false], $this->driver->getPermissions('/foo.jpg'));
    }

    /**
     * @test
     * @group not-php74
     * @dataProvider checkGetFilesInFolderDataProvider()
     */
    public function checkGetFilesInFolder(string $folderIdentifier, array $expectedFiles, bool $recursive = false, int $start = 0, int $numberOfItems = 0, string $sort = ''): void
    {
        $this->initializeDriver();
        $files = $this->driver->getFilesInFolder($folderIdentifier, $start, $numberOfItems, $recursive, [], $sort);
        $this->assertEquals($expectedFiles, $files);
    }

    /**
     * @test
     * @dataProvider checkGetFoldersInFolderDataProvider()
     */
    public function checkGetFoldersInFolder(string $folderIdentifier, array $expectedFiles, bool $recursive = false, int $start = 0, int $numberOfItems = 0, string $sort = ''): void
    {
        $this->initializeDriver();
        $files = $this->driver->getFoldersInFolder($folderIdentifier, $start, $numberOfItems, $recursive, [], $sort);
        $this->assertEquals($expectedFiles, $files);
    }

    /**
     * @test
     */
    public function checkSanitizeFileName(): void
    {
        $this->initializeDriver();
        $this->assertEquals('/11084/11085/1282', $this->driver->sanitizeFileName('/11084/11085/1282'));
    }

    /**
     * @test
     */
    public function checkCountFilesInFolder(): void
    {
        $this->initializeDriver();
        $this->assertEquals(118, $this->driver->countFilesInFolder('/11084/11085'));
    }

    /**
     * @test
     */
    public function checkCountFoldersInFolder(): void
    {
        $this->initializeDriver();
        $this->assertEquals(6, $this->driver->countFoldersInFolder('/11084/'));
    }

    /**
     * @test
     */
    public function checkGetParentFolderIdentifierOfIdentifier(): void
    {
        $this->initializeDriver();
        $this->assertEquals('/11084/11085/', $this->driver->getParentFolderIdentifierOfIdentifier('/11084/11085/1282'));
    }

    /**
     * @test
     */
    public function checkCaseSensitiveFileSystem(): void
    {
        $this->initializeDriver();
        $this->assertTrue($this->driver->isCaseSensitiveFileSystem());
    }

    /**
     * @test
     */
    public function checkMergeConfigurationCapabilities()
    {
        $this->initializeDriver();
        $this->assertEquals(ResourceStorage::CAPABILITY_BROWSABLE | ResourceStorage::CAPABILITY_PUBLIC | ResourceStorage::CAPABILITY_HIERARCHICAL_IDENTIFIERS, $this->driver->getCapabilities());

        $this->assertTrue($this->driver->hasCapability(ResourceStorage::CAPABILITY_PUBLIC));

        $capabilities = $this->driver->mergeConfigurationCapabilities(ResourceStorage::CAPABILITY_BROWSABLE);
        $this->assertEquals(1, $capabilities);
    }

    protected function initializeDriver(?array $config = null, $storage = null): void
    {
        $driverConfig = [
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
            'writePublicUrls' => getenv('celum_writePublicUrls') ?: true,
            'informationFieldId' => getenv('celum_informationFieldId') ?: '',
            'nodeId' => getenv('celum_nodeId') ?: '',
            'descriptionFieldName' => getenv('celum_descriptionFieldName') ?: '',
            'alternativeTextFieldName' => getenv('celum_alternativeTextFieldName') ?: '',
            'roots' => getenv('celum_roots') ?: '',
        ];

        if (is_array($config)) {
            ArrayUtility::mergeRecursiveWithOverrule($driverConfig, $config);
        }
        $this->driver = new CelumDriver($driverConfig);
        $this->driver->setStorageUid(1);
        $this->driver->processConfiguration();
        $this->driver->initialize();
    }

    public function checkGetUrlMethodDataProvider(): array
    {
        return [
            'default type' => [
                [],
                '/11084/11086/1494',
                'https://contenthub-demo.brix.ch/direct/download?format=largeprvw&id=1494'
            ],
            'publicUrl' => [
                [],
                '/11084/11086/1494',
                'https://contenthub-demo.brix.ch/direct/download?format=largeprvw&id=1494'
            ],
            'thumb-wrong' => [
                [],
                'thumb/foo/bar',
                '',
                ClientException::class
            ],
        ];
    }

    public function checkGetFilesInFolderDataProvider(): array
    {
        return [
            [
                '/',
                []
            ],
            [
                '/11084/11086/',
                [
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
            ],
            [
                '/11084/11086/',
                [
                    '/11084/11086/1492',
                    '/11084/11086/1494',
                    '/11084/11086/1495',
                    '/11084/11086/1496',
                ],
                false,
                0,
                4
            ],
            [
                '/11084/',
                [
                    '/11084/11085/1258',
                    '/11084/11085/1259',
                    '/11084/11085/1260',
                    '/11084/11085/1261',
                ],
                true,
                0,
                4
            ],
            [
                '/11084/11085',
                [
                    '/11084/11085/1265',
                    '/11084/11085/1317',
                    '/11084/11085/1355',
                    '/11084/11085/1282',
                ],
                false,
                0,
                4,
                'name'
            ],
            [
                '/11084/11085',
                [
                    '/11084/11085/1258',
                    '/11084/11085/1259',
                    '/11084/11085/1260',
                    '/11084/11085/1261',
                ],
                false,
                0,
                4,
                'fileext'
            ],
            [
                '/11084/11085',
                [
                    '/11084/11085/1258',
                    '/11084/11085/1259',
                    '/11084/11085/1260',
                    '/11084/11085/1261',
                ],
                false,
                0,
                4,
                'tstamp'
            ],
            [
                '/11084/11085',
                [
                    '/11084/11085/1353',
                    '/11084/11085/1314',
                    '/11084/11085/1459',
                    '/11084/11085/1282',
                ],
                false,
                0,
                4,
                'size'
            ],
        ];
    }

    public function checkGetFoldersInFolderDataProvider(): array
    {
        return [
            [
                '/',
                [
                    '/11084/'
                ]
            ],
            [
                '/11084/',
                [
                    '/11084/11085/',
                    '/11084/11086/',
                    '/11084/11087/',
                    '/11084/11088/',
                    '/11084/11089/',
                    '/11084/11090/',
                ],
            ],
            [
                '/',
                [
                    '/11084/',
                    '/11084/11085/',
                    '/11084/11086/',
                    '/11084/11087/',
                    '/11084/11088/',
                    '/11084/11089/',
                    '/11084/11090/',
                ],
                true
            ],
            [
                '/',
                [
                    '/11084/',
                    '/11084/11085/',
                    '/11084/11086/',
                    '/11084/11087/',
                ],
                true,
                0,
                4
            ],
            [
                '/11084/',
                [
                    '/11084/11090/',
                    '/11084/11089/',
                    '/11084/11086/',
                    '/11084/11085/',
                ],
                false,
                0,
                4,
                'name'
            ],
        ];
    }
}
