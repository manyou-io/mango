<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;

use ArrayObject;
use Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

use function count;
use function in_array;

#[AsTaggedItem(priority: -110)]
class RemoveAnyOf implements SchemasProcessor
{
    public function __invoke(ArrayObject $schemas): ArrayObject
    {
        foreach ($schemas as $name => $schema) {
            if ($properties = $schema['properties'] ?? false) {
                foreach ($properties as $field => $property) {
                    $type     = $property['type'] ?? false;
                    $nullable = $property['nullable'] ?? false;

                    if ($type === 'object' && isset($property['items'])) {
                        unset($schemas[$name]['properties'][$field]['items']);
                    }

                    if (
                        (in_array($type, ['string']) || ($type === 'object' && isset($property['properties'])))
                        && $nullable
                    ) {
                        unset($schemas[$name]['properties'][$field]['anyOf']);
                    } elseif (
                        $nullable
                        && count($schemas[$name]['properties'][$field]['anyOf'] ?? []) === 1
                        && isset($schemas[$name]['properties'][$field]['anyOf'][0]['$ref'])
                    ) {
                        if (! isset($schemas[$name]['properties'][$field]['oneOf'])) {
                            $schemas[$name]['properties'][$field]['allOf']
                                = $schemas[$name]['properties'][$field]['anyOf'];
                        }

                        unset($schemas[$name]['properties'][$field]['anyOf']);
                    }
                }
            }
        }

        return $schemas;
    }
}
