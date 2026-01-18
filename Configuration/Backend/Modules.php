<?php

use Pahy\Ignitercf\Controller\BackendController;

/**
 * Backend module registration for IgniterCF
 */
return [
    'ignitercf' => [
        'parent' => 'system',
        'position' => ['after' => 'config'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/system/ignitercf',
        'labels' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'module-ignitercf',
        'extensionName' => 'Ignitercf',
        'controllerActions' => [
            BackendController::class => ['index'],
        ],
    ],
];
