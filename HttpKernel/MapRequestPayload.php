<?php

declare(strict_types=1);

namespace Mango\HttpKernel;

use Attribute;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload as SymfonyMapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\Validator\Constraints\GroupSequence;

#[Attribute(Attribute::TARGET_PARAMETER)]
class MapRequestPayload extends SymfonyMapRequestPayload
{
    public function __construct(
        array|string|null $acceptFormat = null,
        array $serializationContext = [],
        string|GroupSequence|array|null $validationGroups = null,
        string $resolver = RequestPayloadValueResolver::class,
        public readonly array|string|null $initializer = null,
    ) {
        parent::__construct($acceptFormat, $serializationContext, $validationGroups, $resolver);
    }
}
