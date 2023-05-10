<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform;

use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\MapDecorated;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[AsDecorator('api_platform.serializer.context_builder', priority: -10)]
class SerializerInitializerContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        #[MapDecorated]
        private SerializerContextBuilderInterface $decorated,
        #[TaggedLocator('mango.api_platform.dto_initializer', 'input_class')]
        private ContainerInterface $initializers,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if (
            $normalization
            || ! isset($context['input']['class'])
            || ! $this->initializers->has($context['input']['class'])
        ) {
            return $context;
        }

        $initializer = $this->initializers->get($context['input']['class']);

        if (! $initializer instanceof DtoInitializerInterface) {
            return $context;
        }

        $objectToPopulate = $initializer->initialize($context['input']['class'], $extractedAttributes ?? []);

        if (null === $objectToPopulate) {
            return $context;
        }

        $request->attributes->set('data', null);
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $objectToPopulate;

        return $context;
    }
}
