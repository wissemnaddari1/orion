<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use ReflectionProperty;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class BlameableSubscriber implements EventSubscriber
{
    private const SYSTEM_EMAIL = 'system@orion.local';

    private ?User $cachedSystemUser = null;
    private bool $systemUserLookupDone = false;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();
        $user = $this->resolveCurrentUser() ?? $this->resolveSystemUser($event->getObjectManager());

        if ($user === null) {
            return;
        }

        if ($this->hasProperty($entity, 'createdBy') && $this->readProperty($entity, 'createdBy') === null) {
            $this->writeProperty($entity, 'createdBy', $user);
        }
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        $user = $this->resolveCurrentUser();

        if ($user === null || !$this->hasProperty($entity, 'updatedBy')) {
            return;
        }

        $this->writeProperty($entity, 'updatedBy', $user);

        $entityManager = $event->getObjectManager();
        $metadata = $entityManager->getClassMetadata($entity::class);
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
    }

    private function resolveCurrentUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return null;
        }

        $user = $token->getUser();
        return $user instanceof User ? $user : null;
    }

    private function hasProperty(object $entity, string $property): bool
    {
        return property_exists($entity, $property);
    }

    private function readProperty(object $entity, string $property): mixed
    {
        $reflection = new ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($entity);
    }

    private function writeProperty(object $entity, string $property, ?User $user): void
    {
        $reflection = new ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $user);
    }

    private function resolveSystemUser(ObjectManager $objectManager): ?User
    {
        if ($this->cachedSystemUser instanceof User) {
            return $this->cachedSystemUser;
        }

        if ($this->systemUserLookupDone) {
            return null;
        }

        $this->systemUserLookupDone = true;
        $repository = $objectManager->getRepository(User::class);
        $systemUser = $repository->findOneBy(['email' => self::SYSTEM_EMAIL]);

        if ($systemUser instanceof User) {
            $this->cachedSystemUser = $systemUser;
            return $systemUser;
        }

        // Last-resort safety net for CLI contexts before command execution.
        $systemUser = new User();
        $systemUser->setUsername('system');
        $systemUser->setEmail(self::SYSTEM_EMAIL);
        $systemUser->setFirstName('System');
        $systemUser->setLastName('Account');
        $systemUser->setRole(UserRole::ADMIN);
        $systemUser->setStatus(UserStatus::ACTIVE);
        $systemUser->setEmailVerified(true);
        $systemUser->setPasswordHash(password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT));

        $objectManager->persist($systemUser);
        $this->cachedSystemUser = $systemUser;

        return $systemUser;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }
}
