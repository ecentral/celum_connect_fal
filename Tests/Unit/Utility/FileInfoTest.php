<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Brix\CelumFal\Tests\Unit\Utility;

use Brix\CelumFal\Utility\FileInfo;
use Brix\CelumFal\Utility\FileInfo\Format;
use Celum\Client\Model\Asset;
use Celum\Client\Model\FilePropertyObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class FileInfoTest extends UnitTestCase
{
    /**
     * @test
     */
    public function initImagesSizeSetsWidthAndHeightWhenAssetIsSmallerThanFormatMaxSize(): void
    {
        $asset = $this->createMock(Asset::class);
        $asset->method('getFileProperties')->willReturn([
            $this->createFileProperty('width', '800'),
            $this->createFileProperty('height', '600'),
        ]);

        $fileInfo = $this->getMockBuilder(FileInfo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $fileInfo->initImagesSize(Format::PREVIEW, $asset);

        self::assertSame(800, $fileInfo->getWidth());
        self::assertSame(600, $fileInfo->getHeight());
    }

    private function createFileProperty(string $name, string $value): FilePropertyObject
    {
        $property = $this->createMock(FilePropertyObject::class);
        $property->method('getName')->willReturn($name);
        $property->method('getValue')->willReturn($value);

        return $property;
    }
}
