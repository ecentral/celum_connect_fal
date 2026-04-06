<?php

declare(strict_types=1);

namespace Brix\CelumFal\Utility;

use Celum\Client\Model\Collection;

class RestClientFolderUtility
{
    public static function getFolderInfoByCollection(Collection $collection, int $storageId, string $locale): array
    {
        return [
            'info' => [
                'identifier' => $collection->getId(),
                'name' => self::getFolderName($collection->getName(), $locale),
                'storage' => $storageId,
                'mtime' => $collection->getModification()->getDate()->getTimestamp(),
            ],
            'children' => [],
            'assets' => []
        ];
    }

    public static function getFolderName(array $names, string $locale): string
    {
        $value = $names[array_key_first($names)];
        if (array_key_exists($locale, $names)) {
            $value = $names[$locale];
        }
        return $value;
    }
}
