<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Brix\CelumFal\Utility\Cache;

return [
    'celum_cache' => [
        'path' => '/celum/cache',
        'access' => 'public',
        'target' => Cache::class . '::clearCache',
    ],
];
