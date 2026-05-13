<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\CertificateVerificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-certificate-ai',
    description: 'Test AI certificate verification for a user',
)]
class TestCertificateAiCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private CertificateVerificationService $certificateVerification
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('userId', InputArgument::REQUIRED, 'User ID to test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('userId');

        $user = $this->userRepository->find($userId);

        if (!$user) {
            $io->error('User not found');
            return Command::FAILURE;
        }

        if (!$user->hasCertificate()) {
            $io->error('User has no certificate uploaded');
            return Command::FAILURE;
        }

        $io->title('Testing AI Certificate Verification');
        $io->text([
            'User: ' . $user->getFullName(),
            'Certificate: ' . $user->getCertificatePath(),
            'Current Status: ' . ($user->getCertificateStatus() !== null ? $user->getCertificateStatus()->value : 'none'),
        ]);

        $io->section('Running AI Analysis...');

        try {
            $this->certificateVerification->verifyAndUpdate($user);
            
            $io->success('AI verification completed!');
            
            $io->table(
                ['Field', 'Value'],
                [
                    ['AI Verdict', $user->getCertificateAiVerdict() ?? 'N/A'],
                    ['AI Score', $user->getCertificateAiScore() ? $user->getCertificateAiScore() . '%' : 'N/A'],
                    ['Certificate Status', $user->getCertificateStatus() !== null ? $user->getCertificateStatus()->value : 'N/A'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('AI verification failed: ' . $e->getMessage());
            $io->text('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
