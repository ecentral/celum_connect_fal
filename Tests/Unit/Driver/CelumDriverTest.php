<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Unit\Driver;

use Brix\CelumFal\Client\CelumClient;
use Brix\CelumFal\Driver\CelumDriver;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class CelumDriverTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @test
     */
    public function getFolderInfoByIdentifierThrowsExceptionWhenFolderIsMissing(): void
    {
        $client = $this->createMock(CelumClient::class);
        $client->method('getFolderInfo')->willReturn(['info' => null, 'children' => [], 'assets' => []]);
        CelumDriver::$client = $client;

        $driver = new CelumDriver([]);

        $this->expectException(FolderDoesNotExistException::class);
        $driver->getFolderInfoByIdentifier('/6101/');
    }

    /**
     * @test
     */
    public function getFileInfoByIdentifierThrowsExceptionWhenFileIsMissing(): void
    {
        $client = $this->createMock(CelumClient::class);
        $client->method('getFileInfo')->willReturn(['info' => null]);
        CelumDriver::$client = $client;

        $driver = new CelumDriver([]);

        $this->expectException(FileDoesNotExistException::class);
        $driver->getFileInfoByIdentifier('/6101/9999');
    }
}
