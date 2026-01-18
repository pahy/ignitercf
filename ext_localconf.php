<?php

defined('TYPO3') || die();

(static function (): void {
    // Hook: DataHandler - Auto-Purge bei Content-Änderungen
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
        = \Pahy\Ignitercf\Hook\DataHandlerHook::class;

    // Hook: Clear Cache - Reagiert auf "Clear all caches"
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][]
        = \Pahy\Ignitercf\Hook\ClearCacheHook::class . '->clearCachePostProc';

    // Scheduler Tasks
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Pahy\Ignitercf\Task\PurgeAllTask::class] = [
        'extension' => 'ignitercf',
        'title' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeAll.title',
        'description' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeAll.description',
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Pahy\Ignitercf\Task\PurgeZoneTask::class] = [
        'extension' => 'ignitercf',
        'title' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeZone.title',
        'description' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeZone.description',
        'additionalFields' => \Pahy\Ignitercf\Task\PurgeZoneTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Pahy\Ignitercf\Task\PurgePageTask::class] = [
        'extension' => 'ignitercf',
        'title' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgePage.title',
        'description' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgePage.description',
        'additionalFields' => \Pahy\Ignitercf\Task\PurgePageTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Pahy\Ignitercf\Task\PurgeOldLogsTask::class] = [
        'extension' => 'ignitercf',
        'title' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeOldLogs.title',
        'description' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeOldLogs.description',
    ];
})();
