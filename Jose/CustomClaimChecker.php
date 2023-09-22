<?php

declare(strict_types=1);

namespace Mango\Jose;

use Jose\Component\Checker\ClaimChecker;
use Jose\Component\Checker\InvalidClaimException;

final class CustomClaimChecker implements ClaimChecker
{
    public function __construct(
        private readonly string $name,
        private readonly mixed $value,
    ) {
    }

    public function checkClaim(mixed $value): void
    {
        if ($value !== $this->value) {
            throw new InvalidClaimException('Invalid value.', $this->name, $value);
        }
    }

    public function supportedClaim(): string
    {
        return $this->name;
    }
}
