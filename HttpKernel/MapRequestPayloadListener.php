<?php

declare(strict_types=1);

namespace Mango\HttpKernel;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

use function in_array;

class MapRequestPayloadListener
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container,
        private ContainerInterface $initializers,
        private DenormalizerInterface $denormalizer,
    ) {
    }

    #[AsEventListener(priority: 1)]
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        $arguments = $event->getArguments();

        foreach ($arguments as $i => $argument) {
            if (! $argument instanceof MapRequestPayload) {
                continue;
            }

            if ($argument->disabled) {
                continue;
            }

            if (str_ends_with($name = $argument->metadata->getName(), 'Payload')) {
                $object = $event->getNamedArguments()[substr($name, 0, -7)] ?? null;
            } else if ($routeParams = $request->attributes->get('_route_params', [])) {
                $groups = $argument->serializationContext['groups'] ?? [];

                if ($groups !== [] && ! in_array('route', $groups, true)) {
                    continue;
                }
    
                $object = $this->denormalizer->denormalize(
                    $routeParams,
                    $argument->metadata->getType(),
                    context: ['groups' => ['route']] + $argument->serializationContext,
                );
            }

            if (!isset($object)) {
                continue;
            }

            $attribute = new MapRequestPayload(
                $argument->acceptFormat,
                [AbstractObjectNormalizer::OBJECT_TO_POPULATE => $object] + $argument->serializationContext,
                $argument->validationGroups,
            );

            $attribute->metadata = $argument->metadata;

            $arguments[$i] = $attribute;
        }

        $event->setArguments($arguments);
    }
}
