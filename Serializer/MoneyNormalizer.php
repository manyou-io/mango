<?php

declare(strict_types=1);

namespace Manyou\Mango\Serializer;

use Money\Currency;
use Money\Money;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MoneyNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface, DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if ($data instanceof Money) {
            return $data;
        }

        // TODO: throw exception if amount and currency are not set

        return new Money($data['amount'], new Currency($data['currency']));
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === Money::class;
    }

    public function normalize($object, ?string $format = null, array $context = []): array
    {
        return [
            'amount' => (int) $object->getAmount(),
            'currency' => $object->getCurrency()->getCode(),
        ];
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Money;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Money::class => true,
        ];
    }
}
