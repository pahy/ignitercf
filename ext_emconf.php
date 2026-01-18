<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'IgniterCF - Cloudflare Cache Purge',
    'description' => 'Automatically purge Cloudflare cache when content changes. Prevents caching of backend previews and hidden pages. Supports multi-site and multi-zone setups. Not affiliated with Cloudflare, Inc.',
    'category' => 'be',
    'version' => '1.1.0',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author' => 'Patrick Hayder',
    'author_email' => 'patrick@hayder.org',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
            'php' => '8.1.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'scheduler' => '',
            'dashboard' => '',
        ],
    ],
];
