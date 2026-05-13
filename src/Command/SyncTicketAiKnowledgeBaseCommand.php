<?php

namespace App\Command;

use App\Repository\SubTicketRepository;
use App\Repository\TicketRepository;
use App\Service\TicketSupportAIService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-ticket-ai-knowledge-base',
    description: 'Sync closed tickets with resolutions from database to the AI knowledge base',
)]
class SyncTicketAiKnowledgeBaseCommand extends Command
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private SubTicketRepository $subTicketRepository,
        private TicketSupportAIService $ticketSupportAIService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show what would be synced without making API calls'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Sync Ticket AI Knowledge Base');

        $tickets = $this->ticketRepository->findBy(
            ['status' => 'CLOSED'],
            ['closedAt' => 'DESC']
        );

        $toSync = [];
        foreach ($tickets as $ticket) {
            if ($ticket->getResolution()) {
                $toSync[] = $ticket;
            }
        }

        $io->text(sprintf('Found %d closed tickets with resolutions.', count($toSync)));

        if (empty($toSync)) {
            $io->warning('No closed tickets with resolutions to sync.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->section('Dry run - would sync:');
            foreach ($toSync as $ticket) {
                $io->text(sprintf('  #%d: %s', $ticket->getId(), $ticket->getSubject()));
            }
            $io->success('Dry run complete.');
            return Command::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($toSync as $ticket) {
            $messages = $this->subTicketRepository->findByTicket($ticket->getId(), true);
            $firstMessage = $messages[0] ?? null;
            $problemMessage = $firstMessage ? $firstMessage->getMessage() : '';

            try {
                $this->ticketSupportAIService->updateKnowledgeBase(
                    (string) $ticket->getSubject(),
                    $problemMessage,
                    (string) $ticket->getResolution(),
                    $ticket->getCategory()?->getName()
                );
                $synced++;
                $io->text(sprintf('  Synced ticket #%d: %s', $ticket->getId(), $ticket->getSubject()));
            } catch (\Exception $e) {
                $failed++;
                $io->warning(sprintf('  Failed ticket #%d: %s', $ticket->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf('Synced %d tickets. Failed: %d', $synced, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
