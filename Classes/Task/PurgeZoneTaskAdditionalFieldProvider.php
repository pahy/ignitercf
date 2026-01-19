<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use Pahy\Ignitercf\Service\ConfigurationService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Additional field provider for PurgeZoneTask
 *
 * Compatible with TYPO3 v12 and v13
 * Supports both constructor injection and container fallback
 */
class PurgeZoneTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    private readonly SiteFinder $siteFinder;
    private readonly ConfigurationService $configurationService;
    private readonly LanguageService $languageService;

    public function __construct(
        ?SiteFinder $siteFinder = null,
        ?ConfigurationService $configurationService = null,
        ?LanguageService $languageService = null,
    ) {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->configurationService = $configurationService ?? GeneralUtility::getContainer()->get(ConfigurationService::class);
        $this->languageService = $languageService ?? $GLOBALS['LANG'];
    }

    public function getAdditionalFields(
        array &$taskInfo,
        $task,
        SchedulerModuleController $schedulerModule
    ): array {
        // Check if we're editing an existing task (compatible with v12 and v13)
        $isEdit = $this->isEditAction($schedulerModule);

        if ($isEdit && $task instanceof PurgeZoneTask) {
            $taskInfo['siteIdentifier'] = $task->siteIdentifier;
        }

        if (!isset($taskInfo['siteIdentifier'])) {
            $taskInfo['siteIdentifier'] = '';
        }

        $options = $this->buildSiteOptions($taskInfo['siteIdentifier']);

        return [
            'siteIdentifier' => [
                'code' => '<select name="tx_scheduler[siteIdentifier]" class="form-select">' . $options . '</select>',
                'label' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeZone.siteIdentifier',
                'cshKey' => '_MOD_system_txschedulerM1',
                'cshLabel' => 'siteIdentifier',
            ],
        ];
    }

    public function validateAdditionalFields(
        array &$submittedData,
        SchedulerModuleController $schedulerModule
    ): bool {
        $submittedData['siteIdentifier'] = trim($submittedData['siteIdentifier'] ?? '');

         if (empty($submittedData['siteIdentifier'])) {
             $this->addMessage(
                 $this->languageService->sL('LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeZone.siteRequired'),
                 \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
             );
             return false;
         }

        return true;
    }

    public function saveAdditionalFields(
        array $submittedData,
        AbstractTask $task
    ): void {
        if ($task instanceof PurgeZoneTask) {
            $task->siteIdentifier = $submittedData['siteIdentifier'];
        }
    }

    private function buildSiteOptions(string $selectedIdentifier): string
    {
        $selectSiteLabel = $this->languageService->sL('LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeZone.selectSite');
        $notConfiguredLabel = $this->languageService->sL('LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:task.purgeZone.notConfigured');

        $options = '<option value="">' . htmlspecialchars($selectSiteLabel) . '</option>';

        foreach ($this->siteFinder->getAllSites() as $site) {
            $identifier = $site->getIdentifier();
            $configured = $this->configurationService->isSiteConfigured($site);
            $label = $identifier . ($configured ? '' : ' ' . $notConfiguredLabel);
            $selected = $identifier === $selectedIdentifier ? ' selected="selected"' : '';
            $disabled = $configured ? '' : ' disabled="disabled"';

            $options .= sprintf(
                '<option value="%s"%s%s>%s</option>',
                htmlspecialchars($identifier),
                $selected,
                $disabled,
                htmlspecialchars($label)
            );
        }

        return $options;
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
