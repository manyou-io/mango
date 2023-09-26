<?php

declare(strict_types=1);

namespace Mango\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

trait NormalizerTrait
{
    private NormalizerInterface $normalizer;
    private DenormalizerInterface $denormalizer;

    private function normalize(?object $data, ...$context): ?array
    {
        return $data === null ? $data
            : $this->normalizer->normalize($data, null, $context);
    }

    private function denormalize(?array $data, string $type, ...$context): ?object
    {
        return $data === null ? $data
            : $this->denormalizer->denormalize($data, $type, null, $context);
    }
}
