<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Tests\Fixtures\Tables\CommentsTable;
use Manyou\Mango\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

class KernelTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public static function setUpBeforeClass(): void
    {
        /** @var SchemaProvider */
        $schema = self::getContainer()->get(SchemaProvider::class);

        foreach ($schema->toDropSql() as $sql) {
            try {
                $schema->getConnection()->executeStatement($sql);
            } catch (TableNotFoundException) {
            }
        }

        foreach ($schema->toSql() as $sql) {
            $schema->getConnection()->executeStatement($sql);
        }

        self::ensureKernelShutdown();
        self::$class  = null;
        self::$kernel = null;
        self::$booted = false;
    }

    public function testCreateSchema()
    {
        /** @var SchemaProvider */
        $schema = self::getContainer()->get(SchemaProvider::class);

        $rowNum = $schema->createQuery()->insert(CommentsTable::NAME, [
            'id' => new Ulid(),
        ])->executeStatement();

        $this->assertSame(1, $rowNum);
    }
}
