<?php

declare(strict_types=1);

namespace Manyou\Mango\Security;

use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Security\Doctrine\TableProvider\UsersTable;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(private SchemaProvider $schema)
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (! $user instanceof User) {
            throw new UnsupportedUserException();
        }

        $q = $this->schema->createQuery();

        $q->selectFrom([UsersTable::NAME, 'u'], 'id', 'username')
            ->where($q->eq('u.id', $user->getId()))
            ->setMaxResults(1);

        if (false === $user = $q->fetchAssociative()) {
            throw new UserNotFoundException();
        }

        return new User(...$user['u']);
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $q = $this->schema->createQuery();

        $q->selectFrom([UsersTable::NAME, 'u'], 'id', 'username', 'password')
            ->where($q->eq('u.username', $identifier))
            ->setMaxResults(1);

        if (false === $user = $q->fetchAssociative()) {
            throw new UserNotFoundException();
        }

        return new User(...$user['u']);
    }
}
