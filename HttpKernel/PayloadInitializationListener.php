<?php

declare(strict_types=1);

namespace Mango\HttpKernel;

use Closure;
use Mango\HttpKernel\MapRequestPayload as MangoMapRequestPayload;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function is_string;

class PayloadInitializationListener
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container,
        private ContainerInterface $initializers,
        #[Autowire(service: 'argument_resolver')]
        private ArgumentResolverInterface $argumentResolver,
    ) {
    }

    private function getInitializer(MapRequestPayload $attribute): ?callable
    {
        try {
            if ($attribute instanceof MangoMapRequestPayload && $attribute->initializer !== null) {
                if (is_string($attribute->initializer)) {
                    return $this->container->get($attribute->initializer);
                }

                return Closure::fromCallable([$this->container->get($attribute->initializer[0]), $attribute->initializer[1]]);
            }

            if ($type = $attribute->metadata->getType()) {
                return $this->initializers->get($type);
            }
        } catch (NotFoundExceptionInterface) {
            return null;
        }
    }

    #[AsEventListener(priority: 1)]
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $arguments = $event->getArguments();

        foreach ($arguments as $i => $argument) {
            if (! $argument instanceof MapRequestPayload) {
                continue;
            }

            if (! $initializer = $this->getInitializer($argument)) {
                continue;
            }

            $initializerArgs = $arguments;
            if ($argument instanceof MangoMapRequestPayload && $argument->initializerResolve) {
                $initializerArgs = $argument->$this->argumentResolver->getArguments($event->getRequest(), $initializer);
            }

            if (! $object = $initializer(...$initializerArgs)) {
                continue;
            }

            $attribute = new MapRequestPayload(
                $argument->acceptFormat,
                $argument->serializationContext + [AbstractObjectNormalizer::OBJECT_TO_POPULATE => $object],
                $argument->validationGroups,
            );

            $attribute->metadata = $argument->metadata;

            $arguments[$i] = $attribute;
        }

        $event->setArguments($arguments);
    }
}
