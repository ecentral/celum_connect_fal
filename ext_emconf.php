<?php

declare(strict_types=1);

/*
 * This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$_EXTKEY = $_EXTKEY ?? 'celum_connect_fal';

$EM_CONF[$_EXTKEY] = [
    'title' => 'celum:connect (FAL)',
    'description' => 'Provides a FAL driver for the CELUM DAM.',
    'category' => 'plugin',
    'author' => 'Chris Maechler',
    'author_email' => 'support@brix.ch',
    'author_company' => 'brix IT solutions',
    'state' => 'stable',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '3.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-13.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
