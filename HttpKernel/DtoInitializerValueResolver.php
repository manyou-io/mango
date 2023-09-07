<?php

declare(strict_types=1);

namespace Mango\HttpKernel;

use Closure;
use Mango\HttpKernel\MapRequestPayload as MangoMapRequestPayload;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function is_string;

class DtoInitializerValueResolver implements ValueResolverInterface
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container,
        private ContainerInterface $initializers,
        #[Autowire(service: 'argument_resolver')]
        private ArgumentResolverInterface $argumentResolver,
    ) {
    }

    private function getInitializer(MapRequestPayload $attribute, ArgumentMetadata $argument): ?callable
    {
        try {
            if ($attribute instanceof MangoMapRequestPayload && $attribute->initializer !== null) {
                if (is_string($attribute->initializer)) {
                    return $this->container->get($attribute->initializer);
                }

                return Closure::fromCallable([$this->container->get($attribute->initializer[0]), $attribute->initializer[1]]);
            }

            if ($type = $argument->getType()) {
                return $this->initializers->get($type);
            }
        } catch (NotFoundExceptionInterface) {
            return null;
        }
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(MapRequestPayload::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
        if (! $attribute || $attribute->disabled) {
            return [];
        }

        if (! $initializer = $this->getInitializer($attribute, $argument)) {
            return [];
        }

        $arguments = $this->argumentResolver->getArguments($request, $initializer);

        $objectToPopulate = $initializer(...$arguments);

        $serializationContext = $request->attributes->get('denormalization_context', []) + [AbstractObjectNormalizer::OBJECT_TO_POPULATE => $objectToPopulate];

        $attribute = null === $objectToPopulate ? $attribute : new MapRequestPayload(
            $attribute->acceptFormat,
            $attribute->serializationContext + $serializationContext,
            $attribute->validationGroups,
        );

        $attribute->metadata = $argument;

        yield $attribute;
    }
}
