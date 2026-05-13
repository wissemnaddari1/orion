<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:ensure-system-user',
    description: 'Ensures the system fallback creator account exists.',
)]
final class EnsureSystemUserCommand extends Command
{
    private const SYSTEM_EMAIL = 'system@orion.local';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(User::class);

        $systemUser = $repository->findOneBy(['email' => self::SYSTEM_EMAIL]);
        if (!$systemUser instanceof User) {
            $systemUser = new User();
            $systemUser->setUsername($this->nextAvailableUsername($repository, 'system'));
            $systemUser->setEmail(self::SYSTEM_EMAIL);
            $systemUser->setFirstName('System');
            $systemUser->setLastName('Account');
            $systemUser->setRole(UserRole::ADMIN);
            $systemUser->setStatus(UserStatus::ACTIVE);
            $systemUser->setEmailVerified(true);

            // Unusable random password while remaining hash-valid.
            $systemUser->setPasswordHash($this->passwordHasher->hashPassword($systemUser, bin2hex(random_bytes(32))));

            $this->entityManager->persist($systemUser);
            $io->text('Created system user.');
        } else {
            $io->text('System user already exists.');
        }

        $this->entityManager->flush();

        $io->success(sprintf('SYSTEM user id: %d', (int) $systemUser->getId()));

        return Command::SUCCESS;
    }

    private function nextAvailableUsername(object $repository, string $base): string
    {
        $candidate = $base;
        $suffix = 0;

        while ($repository->findOneBy(['username' => $candidate]) instanceof User) {
            ++$suffix;
            $candidate = sprintf('%s.%d', $base, $suffix);
        }

        return $candidate;
    }
}
