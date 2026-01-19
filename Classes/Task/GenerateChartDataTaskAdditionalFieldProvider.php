<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Additional field provider for GenerateChartDataTask
 *
 * Provides configuration field for the number of days to include in statistics.
 * Compatible with TYPO3 v12 and v13
 */
class GenerateChartDataTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}
    /**
     * @param array<string, mixed> $taskInfo
     * @param AbstractTask|null $task
     * @param SchedulerModuleController $schedulerModule
     * @return array<string, array<string, mixed>>
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        // Check if we're editing an existing task (compatible with v12 and v13)
        $isEdit = $this->isEditAction($schedulerModule);

        // Initialize field value
        if ($isEdit && $task instanceof GenerateChartDataTask) {
            $taskInfo['ignitercf_days'] = $task->days;
        }

        if (!isset($taskInfo['ignitercf_days'])) {
            $taskInfo['ignitercf_days'] = 7;
        }

        $fieldId = 'ignitercf_days';
        $fieldCode = sprintf(
            '<input type="number" class="form-control" name="tx_scheduler[%s]" id="%s" value="%d" min="1" max="365" />',
            $fieldId,
            $fieldId,
            (int)$taskInfo['ignitercf_days']
        );

        return [
            $fieldId => [
                'code' => $fieldCode,
                'label' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.generateChartData.days',
                'cshKey' => '_MOD_system_txschedulerM1',
                'cshLabel' => $fieldId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $submittedData
     * @param SchedulerModuleController $schedulerModule
     * @return bool
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        $days = (int)($submittedData['ignitercf_days'] ?? 0);

         if ($days < 1 || $days > 365) {
             $this->addMessage(
                 $this->languageService->sL('LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.generateChartData.days.invalid') ?: 'Days must be between 1 and 365',
                 \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
             );

             return false;
         }

        return true;
    }

    /**
     * @param array<string, mixed> $submittedData
     * @param AbstractTask $task
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof GenerateChartDataTask) {
            $task->days = (int)($submittedData['ignitercf_days'] ?? 7);
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
