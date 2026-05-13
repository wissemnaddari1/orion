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
    name: 'app:create-admin',
    description: 'Create an admin user for testing',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if admin already exists
        $existingAdmin = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'admin@orion.com']);

        if ($existingAdmin) {
            // Update existing admin
            $user = $existingAdmin;
            $io->warning('Admin user already exists. Updating password...');
        } else {
            // Create new admin
            $user = new User();
            $user->setUsername('admin');
            $user->setEmail('admin@orion.com');
            $user->setFirstName('Admin');
            $user->setLastName('User');
            $user->setRole(UserRole::ADMIN);
            $user->setStatus(UserStatus::ACTIVE);
            $user->setEmailVerified(true);
            $io->info('Creating new admin user...');
        }

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPasswordHash($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Admin user created/updated successfully!');
        $io->table(
            ['Field', 'Value'],
            [
                ['Email', 'admin@orion.com'],
                ['Password', 'password'],
                ['Role', 'ADMIN'],
                ['Status', 'ACTIVE'],
            ]
        );

        return Command::SUCCESS;
    }
}
