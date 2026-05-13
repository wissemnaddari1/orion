<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clear-profile-picture',
    description: 'Clear profile picture for a user by email',
)]
class ClearProfilePictureCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('User with email "%s" not found.', $email));
            return Command::FAILURE;
        }

        if (!$user->getProfilePicture()) {
            $io->warning(sprintf('User "%s" has no profile picture set.', $user->getFullName()));
            return Command::SUCCESS;
        }

        $io->info(sprintf('Clearing profile picture for: %s (%s)', $user->getFullName(), $user->getEmail()));
        $io->info(sprintf('Current profile picture path: %s', $user->getProfilePicture()));

        $user->setProfilePicture(null);
        $this->entityManager->flush();

        $io->success('Profile picture cleared successfully! User will now display initials.');

        return Command::SUCCESS;
    }
}
