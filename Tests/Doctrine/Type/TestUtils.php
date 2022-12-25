<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests\Doctrine\Type;

use function is_object;
use function is_scalar;

trait TestUtils
{
    private function assertValues(callable $callable, array $assertions)
    {
        foreach ($assertions as [$value, $expected]) {
            match (true) {
                $expected === null => $this->assertNull($callable($value)),
                is_object($expected) => $this->assertEquals($expected, $callable($value)),
                is_scalar($expected) => $this->assertSame($expected, $callable($value)),
            };
        }
    }
}
