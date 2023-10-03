<?php

declare(strict_types=1);

namespace Mango\Serializer;

use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait HydrateTrait
{
    private readonly ClassDiscriminatorResolverInterface $classDiscriminatorResolver;

    #[Required]
    public function setClassDiscriminatorResolver(ClassDiscriminatorResolverInterface $classDiscriminatorResolver): void
    {
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
    }

    private function hydrate(string $_class, mixed ...$data): object
    {
        if ($mapping = $this->classDiscriminatorResolver->getMappingForClass($_class)) {
            $class = $mapping->getClassForType($data[$typeProperty = $mapping->getTypeProperty()]);
            unset($data[$typeProperty]);
        }

        return new $class(...$data);
    }
}
