<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CLI toolbox',
    'description' => '',
    'category' => 'system',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author' => 'Philipp Kitzberger',
    'author_email' => 'typo3@kitze.net',
    'author_company' => '',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-12.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
