<?php

$EM_CONF['celum_connect_fal'] = [
    'title' => 'CELUM FAL driver',
    'description' => 'Provides a FAL driver for the CELUM DAM.',
    'category' => 'be',
    'version' => '1.0.4',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => false,
    'author' => 'Chris Maechler',
    'author_email' => 'support@brix.ch',
    'author_company' => 'brix cross media',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
