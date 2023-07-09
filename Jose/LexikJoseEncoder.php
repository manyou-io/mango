<?php

declare(strict_types=1);

namespace Manyou\Mango\Jose;

use Closure;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use SpomkyLabs\LexikJoseBundle\Encoder\LexikJoseEncoder as SpomkyLexikJoseEncoder;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: SpomkyLexikJoseEncoder::class)]
class LexikJoseEncoder implements JWTEncoderInterface
{
    private Closure $propertyAccessor;

    public function __construct(
        #[AutowireDecorated]
        private SpomkyLexikJoseEncoder $inner,
        private JWKSLoader $jwksLoader,
    ) {
        $this->propertyAccessor = function & ($object, $property) {
            $value = & Closure::bind(function & () use ($property) {
                return $this->$property;
            }, $object, $object)->__invoke();

            return $value;
        };
    }

    public function encode(array $payload): string
    {
        return $this->inner->encode($payload);
    }

    public function decode($token): array
    {
        $keySet = & ($this->propertyAccessor)($this->inner, 'signatureKeyset');
        $keySet = ($this->jwksLoader)();

        return $this->inner->decode($token);
    }
}
