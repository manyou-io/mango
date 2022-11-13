<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests;

use Doctrine\DBAL\Connection;
use Manyou\Mango\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testKernel()
    {
        self::bootKernel();

        /** @var Connection */
        $connection = self::getContainer()->get(Connection::class);

        $result = $connection->executeQuery('select ? from dual', ['Hello World'])->fetchOne();

        $this->assertSame('Hello World', $result);
    }
}
