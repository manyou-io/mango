<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Tests\Fixtures\Tables\GroupTable;
use Manyou\Mango\Tests\Fixtures\Tables\PostsTable;
use Manyou\Mango\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineTest extends KernelTestCase
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

    public function testBasics()
    {
        /** @var SchemaProvider */
        $schema = self::getContainer()->get(SchemaProvider::class);

        // table name with reserved keyword
        $rowNum = $schema->createQuery()->insert(GroupTable::NAME, [
            'id' => 1,
            // column name with reserved keyword
            'order' => 'foo',
        ])->executeStatement();

        $this->assertSame(1, $rowNum);

        $rowNum = $schema->createQuery()->insert(GroupTable::NAME, [
            'id' => 2,
            // column name alias
            'orderString' => 'bar',
        ])->executeStatement();

        $this->assertSame(1, $rowNum);

        $rowNum = $schema->createQuery()->bulkInsert(
            GroupTable::NAME,
            ['id' => 3, 'order' => 'bulk foo'],
            ['id' => 4, 'order' => 'bulk bar'],
        );
        $this->assertSame(2, $rowNum);

        $rowNum = $schema->createQuery()->bulkInsert(
            GroupTable::NAME,
            ['id' => 5, 'orderString' => 'bulk alias foo'],
            ['id' => 6, 'orderString' => 'bulk alias bar'],
        );
        $this->assertSame(2, $rowNum);

        // implicitly select all columns
        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME)
            ->where($q->eq('id', 1));
        $this->assertEqualsCanonicalizing([
            [GroupTable::NAME => ['id' => 1, 'orderString' => 'foo']],
        ], $q->fetchAllAssociative());

        // explicitly select specific columns with reserved keyword
        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME, 'id', 'order')
            ->where($q->eq('id', 5));
        $this->assertEqualsCanonicalizing([
            [GroupTable::NAME => ['id' => 5, 'order' => 'bulk alias foo']],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME, myId: 'id', myAlias: 'order')
            ->where($q->eq('id', 3));
        $this->assertEqualsCanonicalizing([
            [GroupTable::NAME => ['myId' => 3, 'myAlias' => 'bulk foo']],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME, 'id', myAlias: 'orderString')
            ->where($q->eq('id', 4));
        $this->assertEqualsCanonicalizing([
            [GroupTable::NAME => ['id' => 4, 'myAlias' => 'bulk bar']],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME)
            ->where($q->eq('id', 2), $q->eq('order', 'bar'));
        $this->assertEqualsCanonicalizing([
            [GroupTable::NAME => ['id' => 2, 'orderString' => 'bar']],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->from(GroupTable::NAME)->select()
            ->where(id: 6, orderString: 'bulk alias bar');
        $this->assertEqualsCanonicalizing([
            [GroupTable::NAME => ['id' => 6, 'orderString' => 'bulk alias bar']],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME)
            ->where($q->eq('id', 2), $q->eq('orderString', 'bulk alias bar'));
        $this->assertEqualsCanonicalizing([], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME)
            ->where($q->in('order', ['foo', 'bar']))
            ->orWhere($q->in('id', [4, 6]));
        $this->assertEqualsCanonicalizing([
            [GroupTable::NAME => ['id' => 1, 'orderString' => 'foo']],
            [GroupTable::NAME => ['id' => 2, 'orderString' => 'bar']],
            [GroupTable::NAME => ['id' => 4, 'orderString' => 'bulk bar']],
            [GroupTable::NAME => ['id' => 6, 'orderString' => 'bulk alias bar']],
        ], $q->fetchAllAssociative());

        $rowNum = $schema->createQuery()->bulkInsert(
            PostsTable::NAME,
            ['id' => 11, 'group_id' => 1, 'title' => 'post 1'],
            ['id' => 12, 'group_id' => 2, 'title' => 'post 2'],
        );
        $this->assertSame(2, $rowNum);

        $q = $schema->createQuery();
        $q->selectFrom([PostsTable::NAME, 'p'], null)
            ->selectRaw(['p', 'count'], Types::INTEGER, 'count(*)');

        $this->assertEqualsCanonicalizing([
            ['p' => ['count' => 2]],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom([PostsTable::NAME, 'p'], null)
            ->selectRaw(['p', 'sum'], Types::INTEGER, '(', ['p', 'id'], '+', ['p', 'group_id'], ')');

        $this->assertEqualsCanonicalizing([
            ['p' => ['sum' => 12]],
            ['p' => ['sum' => 14]],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom([PostsTable::NAME, 'p'])
            ->join('p', [GroupTable::NAME, 'g'], ['group_id', 'id']);
        $this->assertEqualsCanonicalizing([
            [
                'p' => ['id' => 11, 'group_id' => 1, 'title' => 'post 1'],
                'g' => ['id' => 1, 'orderString' => 'foo'],
            ],
            [
                'p' => ['id' => 12, 'group_id' => 2, 'title' => 'post 2'],
                'g' => ['id' => 2, 'orderString' => 'bar'],
            ],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom(PostsTable::NAME, '*', myId: 'id')
            ->joinOn(GroupTable::NAME, 'id', 'group_id', myAlias: 'order');
        $this->assertEqualsCanonicalizing([
            [
                PostsTable::NAME => ['myId' => 11, 'group_id' => 1, 'title' => 'post 1'],
                GroupTable::NAME => ['myAlias' => 'foo'],
            ],
            [
                PostsTable::NAME => ['myId' => 12, 'group_id' => 2, 'title' => 'post 2'],
                GroupTable::NAME => ['myAlias' => 'bar'],
            ],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom([PostsTable::NAME, 'p'], 'title')
            ->joinOn([GroupTable::NAME, 'g'], 'id', 'group_id', null)
            ->where($q->eq('id', 2));
        $this->assertEqualsCanonicalizing([
            [
                'p' => ['title' => 'post 2'],
            ],
        ], $q->fetchAllAssociative());

        $q = $schema->createQuery();
        $q->selectFrom([PostsTable::NAME, 'p'], null)
            ->joinOn([GroupTable::NAME, 'g'], 'id', 'group_id', '*', myAlias: 'orderString')
            ->where($q->eq(['p', 'id'], 12));
        $this->assertEqualsCanonicalizing([
            [
                'g' => ['id' => 2, 'myAlias' => 'bar'],
            ],
        ], $q->fetchAllAssociative());
    }

    /** @depends testBasics */
    public function testFetchPatterns()
    {
        /** @var SchemaProvider */
        $schema = self::getContainer()->get(SchemaProvider::class);

        $q = $schema->createQuery();
        $q->selectFrom(PostsTable::NAME, 'title', post_id: 'id')
            ->joinOn(GroupTable::NAME, 'id', 'group_id', name: 'order', group_id: 'id');
        $this->assertEqualsCanonicalizing([
            ['post_id' => 11, 'group_id' => 1, 'title' => 'post 1', 'name' => 'foo'],
            ['post_id' => 12, 'group_id' => 2, 'title' => 'post 2', 'name' => 'bar'],
        ], $q->fetchAllAssociativeFlat());

        $q = $schema->createQuery();
        $q->selectFrom(PostsTable::NAME, 'id')
            ->joinOn(GroupTable::NAME, 'id', 'group_id', 'id');
        $this->assertEqualsCanonicalizing([
            ['id' => 1],
            ['id' => 2],
        ], $q->fetchAllAssociativeFlat());

        $q = $schema->createQuery();
        $q->selectFrom(PostsTable::NAME, 'title')
            ->joinOn(GroupTable::NAME, 'id', 'group_id', 'orderString');
        $this->assertEqualsCanonicalizing([
            'post 1' => 'foo',
            'post 2' => 'bar',
        ], $q->fetchAllKeyValue());

        $q = $schema->createQuery();
        $q->selectFrom([PostsTable::NAME, 'p'], 'id')
            ->orderBy(['p', 'id'], 'DESC');
        $this->assertSame([12, 11], $q->fetchFirstColumn());

        $q = $schema->createQuery();
        $q->selectFrom(PostsTable::NAME, 'title')
            ->orderBy('id', 'DESC')
            ->setMaxResults(1);
        $this->assertSame('post 2', $q->fetchOne());

        $q = $schema->createQuery();
        $q->selectFrom(PostsTable::NAME, 'title', post_id: 'id')
            ->joinOn(GroupTable::NAME, 'id', 'group_id', group_id: 'id', name: 'orderString');
        $this->assertEqualsCanonicalizing([
            'post 1' => ['post_id' => 11, 'group_id' => 1, 'name' => 'foo'],
            'post 2' => ['post_id' => 12, 'group_id' => 2, 'name' => 'bar'],
        ], $q->fetchAllAssociativeIndexed());

        $q = $schema->createQuery();
        $q->selectFrom(PostsTable::NAME, 'title', 'title', post_id: 'id')
            ->joinOn(GroupTable::NAME, 'id', 'group_id', group_id: 'id', name: 'orderString');
        $this->assertEqualsCanonicalizing([
            'post 1' => ['title' => 'post 1', 'post_id' => 11, 'group_id' => 1, 'name' => 'foo'],
            'post 2' => ['title' => 'post 2', 'post_id' => 12, 'group_id' => 2, 'name' => 'bar'],
        ], $q->fetchAllAssociativeIndexed());

        $rowNum = $schema->createQuery()->bulkInsert(
            PostsTable::NAME,
            ['id' => 13, 'group_id' => 1, 'title' => 'post 3'],
            ['id' => 14, 'group_id' => 2, 'title' => 'post 4'],
        );
        $this->assertSame(2, $rowNum);

        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME, 'orderString')
            ->joinOn(PostsTable::NAME, 'group_id', 'id', 'id', 'title');
        $this->assertEqualsCanonicalizing([
            'foo' => [['title' => 'post 1', 'id' => 11], ['title' => 'post 3', 'id' => 13]],
            'bar' => [['title' => 'post 2', 'id' => 12], ['title' => 'post 4', 'id' => 14]],
        ], $q->fetchAllAssociativeGrouped());

        $q = $schema->createQuery();
        $q->selectFrom(GroupTable::NAME, 'orderString')
            ->joinOn(PostsTable::NAME, 'group_id', 'id', 'title');
        $this->assertEqualsCanonicalizing([
            'foo' => ['post 1', 'post 3'],
            'bar' => ['post 2', 'post 4'],
        ], $q->fetchColumnGrouped());
    }
}
