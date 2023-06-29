<?php

declare(strict_types=1);

namespace Manyou\Mango\Jose;

use Jose\Component\Core\Algorithm;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet as CoreJWKSet;

class JWKSet extends CoreJWKSet
{
    protected array $keys = [];

    public function __construct(
        array $keys = [],
        private ?JWKSLoader $jwksLoader = null,
    ) {
        parent::__construct($keys);

        $this->keys = $this->getKeys();
    }

    private function getKeys(): array
    {
        if ($this->jwksLoader !== null) {
            return ($this->jwksLoader)();
        }

        return $this->keys;
    }

    public function selectKey(string $type, ?Algorithm $algorithm = null, array $restrictions = []): ?JWK
    {
        $this->keys = $this->getKeys();

        return parent::selectKey($type, $algorithm, $restrictions);
    }
}
