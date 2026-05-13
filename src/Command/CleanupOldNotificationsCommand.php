<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-old-notifications',
    description: 'Delete old notifications automatically (read after N days, or any after M days). Run daily via cron for automatic cleanup.',
)]
class CleanupOldNotificationsCommand extends Command
{
    private const DEFAULT_DAYS_READ = 30;
    private const DEFAULT_DAYS_ALL = 90;

    public function __construct(
        private NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days-read', null, InputOption::VALUE_OPTIONAL, 'Delete read notifications older than this many days (default: ' . self::DEFAULT_DAYS_READ . ', 0 = skip)', (string) self::DEFAULT_DAYS_READ)
            ->addOption('days-all', null, InputOption::VALUE_OPTIONAL, 'Delete any notification older than this many days (default: ' . self::DEFAULT_DAYS_ALL . ', 0 = skip)', (string) self::DEFAULT_DAYS_ALL)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would be deleted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $daysRead = (int) $input->getOption('days-read');
        $daysAll = (int) $input->getOption('days-all');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($daysRead < 0 || $daysAll < 0) {
            $io->error('days-read and days-all must be 0 or positive.');
            return Command::FAILURE;
        }

        $deletedRead = 0;
        $deletedAll = 0;

        if ($daysRead > 0) {
            $beforeRead = (new \DateTimeImmutable())->modify("-{$daysRead} days");
            if ($dryRun) {
                $io->note(sprintf('[DRY-RUN] Would delete read notifications older than %s (%d days).', $beforeRead->format('Y-m-d'), $daysRead));
            } else {
                $deletedRead = $this->notificationRepository->deleteReadOlderThan($beforeRead);
                $io->info(sprintf('Deleted %d read notification(s) older than %d days.', $deletedRead, $daysRead));
            }
        }

        if ($daysAll > 0) {
            $beforeAll = (new \DateTimeImmutable())->modify("-{$daysAll} days");
            if ($dryRun) {
                $io->note(sprintf('[DRY-RUN] Would delete all notifications older than %s (%d days).', $beforeAll->format('Y-m-d'), $daysAll));
            } else {
                $deletedAll = $this->notificationRepository->deleteAllOlderThan($beforeAll);
                $io->info(sprintf('Deleted %d notification(s) older than %d days (any status).', $deletedAll, $daysAll));
            }
        }

        if ($daysRead <= 0 && $daysAll <= 0) {
            $io->warning('Both days-read and days-all are 0; nothing to delete. Use --days-read=30 and/or --days-all=90.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->success('Dry run finished. Run without --dry-run to perform deletion.');
        } else {
            $io->success(sprintf('Cleanup finished. Total deleted: %d.', $deletedRead + $deletedAll));
        }

        return Command::SUCCESS;
    }
}
