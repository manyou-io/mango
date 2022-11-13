<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
        ];
    }
}
