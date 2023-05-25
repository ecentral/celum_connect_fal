<?php

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
    'version' => '2.0.5',
    'constraints' => [
        'depends' => [
            'typo3' => '11.0.0-11.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];