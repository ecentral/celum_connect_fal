<?php

use Brix\CelumFal\Utility\Cache;

return [
    'celum_cache' => [
        'path' => '/celum/cache',
        'access' => 'public',
        'target' => Cache::class . '::clearCache',
    ],
];
