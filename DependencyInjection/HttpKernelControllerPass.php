<?php

declare(strict_types=1);

namespace Mango\DependencyInjection;

use Mango\HttpKernel\MapRequestPayloadListener;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_fill_keys;
use function class_exists;
use function get_debug_type;
use function implode;
use function interface_exists;
use function is_int;
use function is_string;
use function sprintf;

class HttpKernelControllerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->registerDtoInitializers($container);
    }

    private function getServiceClass(ContainerBuilder $container, string $serviceId): string
    {
        while (true) {
            $definition = $container->findDefinition($serviceId);

            if (! $definition->getClass() && $definition instanceof ChildDefinition) {
                $serviceId = $definition->getParent();

                continue;
            }

            return $definition->getClass();
        }
    }

    private function guessInitializerClasses(ReflectionClass $initializerClass, string $serviceId, string $methodName): iterable
    {
        try {
            $method = $initializerClass->getMethod($methodName);
        } catch (ReflectionException) {
            throw new RuntimeException(sprintf('Invalid initializer service "%s": class "%s" must have an "%s()" method.', $serviceId, $initializerClass->getName(), $methodName));
        }

        $type = $method->getReturnType();

        if (! $type) {
            throw new RuntimeException(sprintf('Invalid initializer service "%s": return value of method "%s::%s()" must have a type-hint corresponding to the message class it handles.', $serviceId, $initializerClass->getName(), $methodName));
        }

        if ($type instanceof ReflectionUnionType) {
            $types        = [];
            $invalidTypes = [];
            foreach ($type->getTypes() as $type) {
                if (! $type->isBuiltin()) {
                    $types[] = (string) $type;
                } else {
                    $invalidTypes[] = (string) $type;
                }
            }

            if ($types) {
                return '__invoke' === $methodName ? $types : array_fill_keys($types, $methodName);
            }

            throw new RuntimeException(sprintf('Invalid initializer service "%s": type-hint of return value in method "%s::__invoke()" must be a class , "%s" given.', $serviceId, $initializerClass->getName(), implode('|', $invalidTypes)));
        }

        if ($type->isBuiltin()) {
            throw new RuntimeException(sprintf('Invalid initializer service "%s": type-hint of return value in method "%s::%s()" must be a class , "%s" given.', $serviceId, $initializerClass->getName(), $methodName, $type instanceof ReflectionNamedType ? $type->getName() : (string) $type));
        }

        return '__invoke' === $methodName ? [$type->getName()] : [$type->getName() => $methodName];
    }

    private function registerDtoInitializers(ContainerBuilder $container): void
    {
        $initializers = [];

        foreach ($container->findTaggedServiceIds('mango.request_payload_initializer', true) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $className = $this->getServiceClass($container, $serviceId);
                $r         = $container->getReflectionClass($className);

                if (null === $r) {
                    throw new RuntimeException(sprintf('Invalid service "%s": class "%s" does not exist.', $serviceId, $className));
                }

                if (isset($tag['class'])) {
                    $methodsByClass = isset($tag['method']) ? [$tag['class'] => $tag['method']] : [$tag['class']];
                } else {
                    $methodsByClass = $this->guessInitializerClasses($r, $serviceId, $tag['method'] ?? '__invoke');
                }

                foreach ($methodsByClass as $class => $method) {
                    if (is_int($class)) {
                        if (is_string($method)) {
                            $class  = $method;
                            $method = [];
                        } else {
                            throw new RuntimeException(sprintf('The handler configuration needs to return an array of messages or an associated array of message and configuration. Found value of type "%s" at position "%d" for service "%s".', get_debug_type($method), $class, $serviceId));
                        }
                    }

                    if (is_string($method)) {
                        $method = ['method' => $method];
                    }

                    $method = $method['method'] ?? '__invoke';

                    if ('*' !== $class && ! class_exists($class) && ! interface_exists($class, false)) {
                        $messageLocation = isset($tag['class']) ? 'declared in your tag attribute "class"' : sprintf('used as argument type in method "%s::%s()"', $r->getName(), $method);

                        throw new RuntimeException(sprintf('Invalid handler service "%s": class or interface "%s" "%s" not found.', $serviceId, $class, $messageLocation));
                    }

                    if (! $r->hasMethod($method)) {
                        throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::%s()" does not exist.', $serviceId, $r->getName(), $method));
                    }

                    if ('__invoke' !== $method) {
                        $wrapperDefinition    = (new Definition('Closure'))->addArgument([new Reference($serviceId), $method])->setFactory('Closure::fromCallable');
                        $initializers[$class] = $wrapperDefinition;
                    } else {
                        $initializers[$class] = new Reference($serviceId);
                    }
                }
            }
        }

        $listener = $container->findDefinition(MapRequestPayloadListener::class);
        $listener->setArgument('$initializers', ServiceLocatorTagPass::register($container, $initializers));
    }
}
