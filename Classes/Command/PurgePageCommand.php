<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Command;

use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to purge Cloudflare cache for a specific page
 */
final class PurgePageCommand extends Command
{
    public function __construct(
        private readonly CacheClearService $cacheClearService,
        private readonly ConfigurationService $configurationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Purge Cloudflare cache for a specific page')
            ->setHelp('Purges the Cloudflare cache for a specific page and optionally a specific language.')
            ->addOption(
                'page',
                'p',
                InputOption::VALUE_REQUIRED,
                'Page UID'
            )
            ->addOption(
                'language',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Language UID (default: all languages)'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be purged without actually purging'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pageId = $input->getOption('page');
        $languageId = $input->getOption('language');
        $dryRun = (bool)$input->getOption('dry-run');

        if (!$this->configurationService->isEnabled()) {
            $io->error('IgniterCF is globally disabled.');
            return Command::FAILURE;
        }

        if (empty($pageId) || !is_numeric($pageId)) {
            $io->error('Page UID required. Use --page=<uid>');
            return Command::FAILURE;
        }

        $pageId = (int)$pageId;

        $io->title('IgniterCF - Purge Page');

        if ($dryRun) {
            $io->note('Dry-run mode - no actual purging');
        }

        $io->text(sprintf('Page: %d', $pageId));

        if ($languageId !== null && is_numeric($languageId)) {
            $languageId = (int)$languageId;
            $io->text(sprintf('Language: %d', $languageId));
        } else {
            $languageId = null;
            $io->text('Language: all');
        }

        try {
            if ($dryRun) {
                if ($languageId !== null) {
                    $io->success(sprintf('Would purge page %d, language %d.', $pageId, $languageId));
                } else {
                    $io->success(sprintf('Would purge page %d, all languages.', $pageId));
                }
            } else {
                if ($languageId !== null) {
                    $this->cacheClearService->clearCacheForPages([$pageId], [$languageId]);
                    $io->success(sprintf('Page %d, language %d purged.', $pageId, $languageId));
                } else {
                    $this->cacheClearService->clearCacheForPage($pageId);
                    $io->success(sprintf('Page %d purged (all languages).', $pageId));
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Purge failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
