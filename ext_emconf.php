<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CLI toolbox',
    'description' => 'TYPO3 CLI toolbox: run copy/move/delete commands of DataHandler via CLI',
    'category' => 'system',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author' => 'Philipp Kitzberger',
    'author_email' => 'typo3@kitze.net',
    'author_company' => '',
    'version' => '3.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
