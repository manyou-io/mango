<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;

use ArrayObject;
use Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;

use function explode;
use function in_array;

class SetFieldsRequired implements SchemasProcessor
{
    public function __invoke(ArrayObject $schemas): ArrayObject
    {
        foreach ($schemas as $name => $schema) {
            $name = explode('-', $name);

            if (isset($name[1])) {
                $groups = explode('.', $name[1]);

                if (in_array('update', $groups)) {
                    continue;
                }
            }

            $properties = $schema['properties'] ?? [];
            $required   = $schema['required'] ?? [];

            foreach ($properties as $field => $property) {
                $type     = $property['type'] ?? (isset($property['$ref']) ? 'object' : false);
                $nullable = $property['nullable'] ?? false;

                if ($type && ! $nullable && ! in_array($field, $required)) {
                    $required[] = $field;
                }
            }

            $schema['required'] = $required;
        }

        return $schemas;
    }
}
