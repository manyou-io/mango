<?php

declare(strict_types=1);

namespace Manyou\Mango\HttpKernel;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function is_callable;

class DtoInitializerValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ContainerInterface $initializers,
        #[Autowire(service: 'argument_resolver')]
        private ArgumentResolverInterface $argumentResolver,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(MapRequestPayload::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
        if (! $attribute || $attribute->disabled) {
            return [];
        }

        $type = $argument->getType();
        if (! $type || ! $this->initializers->has($type)) {
            return [];
        }

        $initializer = $this->initializers->get($type);
        if (! is_callable($initializer)) {
            return [];
        }

        $arguments = $this->argumentResolver->getArguments($request, $initializer);

        $objectToPopulate = $initializer(...$arguments);

        $attribute = null === $objectToPopulate ? $attribute : new MapRequestPayload(
            $attribute->acceptFormat,
            $attribute->serializationContext + [AbstractObjectNormalizer::OBJECT_TO_POPULATE => $objectToPopulate],
            $attribute->validationGroups,
        );

        $attribute->metadata = $argument;

        yield $attribute;
    }
}
