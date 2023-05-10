<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;

use ArrayObject;
use Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

use function explode;
use function strlen;
use function strpos;
use function substr;

#[AsTaggedItem(priority: -100)]
class RemoveEnumGroups implements SchemasProcessor
{
    public function __invoke(ArrayObject $schemas): ArrayObject
    {
        foreach ($schemas as $name => $schema) {
            foreach ($schema['properties'] ?? [] as $property) {
                if ($property instanceof ArrayObject) {
                    $property->exchangeArray($this->alterProperty($property->getArrayCopy(), $schemas));
                }
            }

            $schemas[$name] = $schema;
        }

        return $schemas;
    }

    private function alterProperty(array $property, ArrayObject $schemas): array
    {
        if (
            ($ref = $property['$ref'] ?? false)
            && strpos($ref, '-')
        ) {
            $enum = explode('-', $ref, 2)[0];
            $enum = substr($enum, strlen('#/components/schemas/'));
            if (isset($schemas[$enum]['enum'])) {
                $property['$ref'] = '#/components/schemas/' . $enum;
            }

            return $property;
        }

        $ofField = match (true) {
            isset($property['anyOf']) => 'anyOf',
            isset($property['allOf']) => 'allOf',
            isset($property['oneOf']) => 'oneOf',
            default => null,
        };

        $ofProperties = null === $ofField ? [] : $property[$ofField];
        foreach ($ofProperties as $i => $ofProperty) {
            $property[$ofField][$i] = $this->alterProperty($ofProperty, $schemas);
        }

        return $property;
    }
}
