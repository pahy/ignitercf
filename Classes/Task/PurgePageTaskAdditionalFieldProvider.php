<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Additional field provider for PurgePageTask
 *
 * Compatible with TYPO3 v12 and v13
 * Supports both constructor injection and fallback
 */
class PurgePageTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    private readonly LanguageService $languageService;

    public function __construct(?LanguageService $languageService = null)
    {
        $this->languageService = $languageService ?? $GLOBALS['LANG'];
    }
    public function getAdditionalFields(
        array &$taskInfo,
        $task,
        SchedulerModuleController $schedulerModule
    ): array {
        // Check if we're editing an existing task (compatible with v12 and v13)
        $isEdit = $this->isEditAction($schedulerModule);

        if ($isEdit && $task instanceof PurgePageTask) {
            $taskInfo['pageUid'] = $task->pageUid;
            $taskInfo['languageUid'] = $task->languageUid;
        }

        if (!isset($taskInfo['pageUid'])) {
            $taskInfo['pageUid'] = 0;
        }
        if (!isset($taskInfo['languageUid'])) {
            $taskInfo['languageUid'] = -1;
        }

        return [
            'pageUid' => [
                'code' => sprintf(
                    '<input type="number" name="tx_scheduler[pageUid]" value="%d" class="form-control" min="1" required />',
                    (int)$taskInfo['pageUid']
                ),
                'label' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgePage.pageUid',
                'cshKey' => '_MOD_system_txschedulerM1',
                'cshLabel' => 'pageUid',
            ],
            'languageUid' => [
                'code' => sprintf(
                    '<input type="number" name="tx_scheduler[languageUid]" value="%d" class="form-control" min="-1" />',
                    (int)$taskInfo['languageUid']
                ),
                'label' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgePage.languageUid',
                'cshKey' => '_MOD_system_txschedulerM1',
                'cshLabel' => 'languageUid',
            ],
        ];
    }

    public function validateAdditionalFields(
        array &$submittedData,
        SchedulerModuleController $schedulerModule
    ): bool {
        $pageUid = (int)($submittedData['pageUid'] ?? 0);
        $languageUid = (int)($submittedData['languageUid'] ?? -1);

         if ($pageUid <= 0) {
              $this->addMessage(
                  $this->languageService->sL('LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgePage.pageUidInvalid'),
                  \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
              );
              return false;
          }

         if ($languageUid < -1) {
              $this->addMessage(
                  $this->languageService->sL('LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgePage.languageUidInvalid'),
                  \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
              );
              return false;
          }

         $submittedData['pageUid'] = $pageUid;
         $submittedData['languageUid'] = $languageUid;

         return true;
    }

    public function saveAdditionalFields(
        array $submittedData,
        AbstractTask $task
    ): void {
        if ($task instanceof PurgePageTask) {
            $task->pageUid = (int)$submittedData['pageUid'];
            $task->languageUid = (int)$submittedData['languageUid'];
        }
    }

    /**
     * Check if we're in edit mode (compatible with TYPO3 v12 and v13)
     *
     * v12: Action is TYPO3\CMS\Scheduler\Task\Enumeration\Action (class with constants)
     * v13: Action is TYPO3\CMS\Scheduler\SchedulerManagementAction (backed enum)
     */
    private function isEditAction(SchedulerModuleController $schedulerModule): bool
    {
        $currentAction = $schedulerModule->getCurrentAction();

        // Handle both v12 (Enumeration class) and v13 (backed enum)
        if (is_object($currentAction)) {
            // v13: backed enum - compare value
            if ($currentAction instanceof \BackedEnum) {
                return $currentAction->value === 'edit';
            }
            // v12: Enumeration class - cast to string
            return (string)$currentAction === 'edit';
        }

        return false;
    }
}
