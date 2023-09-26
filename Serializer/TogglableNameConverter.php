<?php

declare(strict_types=1);

namespace Mango\Serializer;

use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class TogglableNameConverter implements AdvancedNameConverterInterface
{
    public const DISABLE_NAME_CONVERTER = 'disable_name_converter';

    public function __construct(
        #[AutowireDecorated]
        private NameConverterInterface $inner,
    ) {
    }

    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if ($context[self::DISABLE_NAME_CONVERTER] ?? false) {
            return $propertyName;
        }

        return $this->inner->normalize($propertyName, $class, $format, $context);
    }

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if ($context[self::DISABLE_NAME_CONVERTER] ?? false) {
            return $propertyName;
        }

        return $this->inner->denormalize($propertyName, $class, $format, $context);
    }
}
