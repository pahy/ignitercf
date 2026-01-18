<?php

return [
    'ignitercf_clear_page' => [
        'path' => '/ignitercf/clear-page',
        'target' => \Pahy\Ignitercf\Controller\CacheController::class . '::clearPageAction',
    ],
    'ignitercf_clear_all' => [
        'path' => '/ignitercf/clear-all',
        'target' => \Pahy\Ignitercf\Controller\CacheController::class . '::clearAllAction',
    ],
    'ignitercf_test_connection' => [
        'path' => '/ignitercf/test-connection',
        'target' => \Pahy\Ignitercf\Controller\BackendController::class . '::testConnectionAction',
    ],
];
