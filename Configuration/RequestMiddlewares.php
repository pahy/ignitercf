<?php

return [
    'frontend' => [
        'pahy/ignitercf/cache-control' => [
            'target' => \Pahy\Ignitercf\Middleware\CacheControlMiddleware::class,
            'after' => [
                'typo3/cms-frontend/tsfe',
            ],
            'before' => [
                'typo3/cms-frontend/output-compression',
            ],
        ],
    ],
];
