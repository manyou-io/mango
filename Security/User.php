<?php

declare(strict_types=1);

namespace Manyou\Mango\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private Ulid $id,
        private string $username,
        private ?string $password = null,
    ) {
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials()
    {
        $this->password = null;
    }

    public function getUsername(): string
    {   
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}
