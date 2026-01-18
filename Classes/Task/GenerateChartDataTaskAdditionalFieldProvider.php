<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\Enumeration\Action;

/**
 * Additional field provider for GenerateChartDataTask
 *
 * Provides configuration field for the number of days to include in statistics.
 */
class GenerateChartDataTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    /**
     * @param array<string, mixed> $taskInfo
     * @param AbstractTask|null $task
     * @param SchedulerModuleController $schedulerModule
     * @return array<string, array<string, mixed>>
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $currentAction = $schedulerModule->getCurrentAction();

        // Initialize field value
        if ($currentAction === Action::EDIT && $task instanceof GenerateChartDataTask) {
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
                $this->getLanguageService()->sL('LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.generateChartData.days.invalid') ?: 'Days must be between 1 and 365',
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

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
