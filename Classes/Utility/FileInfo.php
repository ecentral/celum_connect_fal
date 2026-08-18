<?php

declare(strict_types=1);

namespace Brix\CelumFal\Utility;

use Brix\CelumFal\Utility\FileInfo\Format;
use Celum\Client\Model\Asset;
use Celum\Client\Model\Download;
use Celum\Client\Model\FileCategory;
use TYPO3\CMS\Core\Utility\PathUtility;

class FileInfo
{
    private string $identifier;
    private string $identifierHash;
    private string $folderHash;
    private string $name;
    private int $storage;
    private int $fileSize;
    private int $width;
    private int $height;
    private ?string $description = '';
    private ?string $alternative = '';
    private string $mimetype;
    private int $ctime;
    private int $mtime;

    private string $previewUrl;
    private string $thumbUrl;
    private bool|string $publicUrl;
    private string $extension;

    public function __construct(Asset $asset, ?string $originalDownloadUrl, int $storage, Format $imageFormat, Format $videoFormat, Format $documentFormat, Format $othersFormat)
    {

        $this->identifier = (string)$asset->getId();
        $this->identifierHash = sha1($this->identifier);
        $this->folderHash = sha1(PathUtility::dirname($this->identifier));
        /** @var string $fileCategory */
        $fileCategory = $asset->getCurrentVersion()->getFileCategory();
        $this->mimetype = strtolower($fileCategory) . '/' . $asset->getCurrentVersion()->getFileExtension();
        $this->storage = $storage;
        $this->fileSize = $asset->getCurrentVersion()->getFilesize();
        $this->mtime = $asset->getModification()->getDate()->getTimestamp();
        $this->ctime = $asset->getCreation()->getDate()->getTimestamp();

        $format = match ($fileCategory) {
            FileCategory::IMAGE => $imageFormat,
            FileCategory::VIDEO => $videoFormat,
            FileCategory::DOCUMENT => $documentFormat,
            default => $othersFormat,
        };

        $this->initImagesSize($format, $asset);
        $this->initPublicUrl($format, $asset, $originalDownloadUrl);
        $this->initNameAndExtension($format, $asset);
    }

    public function toArray(): array
    {
        return [
            'info' => [
                'identifier' => $this->identifier,
                'identifier_hash' => $this->identifierHash,
                'folder_hash' => $this->folderHash,
                'sha1' => sha1($this->identifier),
                'name' => $this->name,
                'title' => $this->name,
                'storage' => $this->storage,
                'size' => $this->fileSize,
                'width' => $this->width,
                'height' => $this->height,
                'description' => $this->description,
                'alternative' => $this->alternative,
                'extension' => $this->extension,
                'mime_type' => $this->mimetype,
                'creation_date' => $this->ctime,
                'modification_date' => $this->mtime,
            ],
            'preview' => $this->previewUrl,
            'thumbnail' => $this->thumbUrl,
            'publicUrl' => $this->publicUrl,
        ];
    }

    public function initImagesSize(Format $format, Asset $asset): void
    {
        $width = 0;
        $height = 0;
        foreach ($asset->getFileProperties() ?? [] as $property) {
            if ($property->getName() === 'width') {
                $width = (int)$property->getValue();
            }
            if ($property->getName() === 'height') {
                $height = (int)$property->getValue();
            }
        }
        if ($width && $height) {
            $max = $format->getMaxSize();
            if (($max > 0) and (($width > $max) or ($height > $max))) {
                if ($width > $height) {
                    $this->height = intval($height * $max / $width);
                    $this->width = $max;
                } else {
                    $this->width = intval($width * $max / $height);
                    $this->height = $max;
                }
            } else {
                $this->width = $width;
                $this->height = $height;
            }
        } else {
            $this->height = 0;
            $this->width = 0;
        }
    }

    private function initPublicUrl(Format $format, Asset $asset, ?string $originalDownloadUrl = null): void
    {
        $currentVersion = $asset->getCurrentVersion();
        $this->publicUrl = false;
        if ($currentVersion->getFileCategory() == FileCategory::IMAGE) {
            foreach ($currentVersion->getPreviewUrls() as $formatKey => $previewUrl) {
                //TODO Check video, archives and documents
                if($format == Format::PREVIEW) {
                    $this->publicUrl = $previewUrl;
                }
                if(Format::PREVIEW == Format::getFormatByString($formatKey)) {
                    $this->previewUrl = $previewUrl;
                }
                if(Format::THUMBNAIL == Format::getFormatByString($formatKey)) {
                    $this->thumbUrl = $previewUrl;
                }
                /*if (($purl['provider'] == $this->provider[$type]) and ($purl['description'] == $this->description[$type])) {
                    $this->publicUrl = $previewUrl;
                }*/
            }
        }
        elseif ($currentVersion->getFileCategory() == FileCategory::DOCUMENT) {
            foreach ($currentVersion->getPreviewUrls() as $formatKey => $previewUrl) {
                /*if($format == Format::PDF) {
                    $this->publicUrl = $previewUrl;
                }*/
                if(Format::PREVIEW == Format::getFormatByString($formatKey)) {
                    $this->previewUrl = $previewUrl;
                }
                if(Format::THUMBNAIL == Format::getFormatByString($formatKey)) {
                    $this->thumbUrl = $previewUrl;
                }
            }
            $this->publicUrl = $originalDownloadUrl;
        }
        elseif ($currentVersion->getFileCategory() == FileCategory::VIDEO) {
            foreach ($currentVersion->getPreviewUrls() as $formatKey => $previewUrl) {
                /*if($format == Format::VIDEO) {
                    $this->publicUrl = $previewUrl;
                }*/
                if(Format::PREVIEW == Format::getFormatByString($formatKey)) {
                    $this->previewUrl = $previewUrl;
                }
                if(Format::THUMBNAIL == Format::getFormatByString($formatKey)) {
                    $this->thumbUrl = $previewUrl;
                }
            }
            $this->publicUrl = $originalDownloadUrl;
        }
        else {
            $this->previewUrl = '';
            $this->thumbUrl = '';
            $this->publicUrl = $originalDownloadUrl;
        }
    }

    public function initNameAndExtension(Format $format, Asset $asset): void
    {

        $this->name = $asset->getName();
        if ($format === Format::THUMBNAIL ||
            $format === Format::LARGE_PREVIEW ||
            $format === Format::PREVIEW) {
            $this->extension = 'jpg';
        } else {
            $this->extension = (string)$asset->getCurrentVersion()->getFileExtension();
        }

        if ($this->extension === '') {
            return;
        }

        // CELUM asset names may carry no extension at all ("_44A8196_02") or the
        // extension of the original ("photo.tif") while a JPEG preview is delivered.
        // TYPO3 derives the file extension from the name, so it has to end in
        // ".<extension>" - otherwise no thumbnail can be generated.
        $suffix = '.' . $this->extension;
        if (str_ends_with(strtolower($this->name), strtolower($suffix))) {
            return;
        }

        $this->name = rtrim($this->name, '.') . $suffix;
    }
    // -------- Getter-Methoden --------
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getIdentifierHash(): string
    {
        return $this->identifierHash;
    }

    public function getFolderHash(): string
    {
        return $this->folderHash;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStorage(): int
    {
        return $this->storage;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAlternative(): ?string
    {
        return $this->alternative;
    }

    public function getMimetype(): string
    {
        return $this->mimetype;
    }

    public function getCtime(): int
    {
        return $this->ctime;
    }

    public function getMtime(): int
    {
        return $this->mtime;
    }

    public function getPreviewUrl(): string
    {
        return $this->previewUrl;
    }

    public function getThumbUrl(): string
    {
        return $this->thumbUrl;
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }
}
