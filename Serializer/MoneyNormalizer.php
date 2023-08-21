<?php

declare(strict_types=1);

namespace Manyou\Mango\Serializer;

use ErrorException;
use Money\Currency;
use Money\Money;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MoneyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if ($data instanceof Money) {
            return $data;
        }

        try {
            return new Money($data['amount'], new Currency($data['currency']));
        } catch (ErrorException $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, ['{amount: int|string, currency: string}']);
        }
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

    public function getSupportedTypes(?string $format): array
    {
        return [Money::class => true];
    }
}
