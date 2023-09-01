<?php

declare(strict_types=1);

namespace Manyou\Mango\Jose;

use DateInterval;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JKUFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function spl_object_hash;

class CachedJWKSLoader implements JWKSLoader
{
    public function __construct(
        private JKUFactory $jkuFactory,
        private CacheInterface $cache,
        #[Autowire('%env(OIDC_JWKS_URI)%')]
        private string $url,
        private array $header = [],
        private int|DateInterval|null $expiresAfter = 120,
    ) {
    }

    public function __invoke(): JWKSet
    {
        return $this->cache->get(spl_object_hash($this), function (ItemInterface $item) {
            $item->expiresAfter($this->expiresAfter);

            return $this->jkuFactory->loadFromUrl($this->url, $this->header);
        });
    }
}
