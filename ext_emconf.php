<?php

$EM_CONF['celum_connect_fal'] = [
    'title' => 'celum:connect (FAL)',
    'description' => 'Provides a FAL driver for the CELUM DAM.',
    'category' => 'be',
    'version' => '2.0.0',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => false,
    'author' => 'Chris Maechler',
    'author_email' => 'support@brix.ch',
    'author_company' => 'brix IT solutions',
    'constraints' => [
        'depends' => [
            'typo3' => '11.0.0-11.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
