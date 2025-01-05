<?php

declare(strict_types=1);

namespace Mango\Tests\Doctrine\Type;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Mango\Doctrine\Type\BackedEnumType;
use Mango\Doctrine\Type\EnumType;
use PHPUnit\Framework\TestCase;

class EnumTypeTest extends TestCase
{
    use TestUtils;

    public function testArrayEnum(): void
    {
        $this->testEnumType($type = new class extends Type {
            use EnumType;

            public function getName(): string
            {
                return 'my_array_enum';
            }

            private function getEnums(): array
            {
                return [
                    'foo',
                    'bar',
                    'baz',
                ];
            }
        });

        $platform = new PostgreSQLPlatform();

        $this->assertSame('bar', $type->convertToPHPValue('bar', $platform));
    }

    public function testBackedEnum(): void
    {
        $this->testEnumType($type = new class extends Type {
            use BackedEnumType;

            public function getName(): string
            {
                return 'my_backed_enum';
            }

            private function getEnumClass(): string
            {
                return MyBackedEnum::class;
            }
        });

        $platform = new PostgreSQLPlatform();

        $this->assertSame(MyBackedEnum::FOO, $type->convertToPHPValue('foo', $platform));
    }

    private function testEnumType(Type $type): void
    {
        $postgres = new PostgreSQLPlatform();
        $mariadb  = new MariaDBPlatform();
        $oracle   = new OraclePlatform();
        $sqlite   = new SQLitePlatform();

        $this->assertValues(
            static fn ($platform) => $type->getSQLDeclaration([], $platform),
            [
                [$postgres, 'TEXT'],
                [$mariadb, "ENUM('foo', 'bar', 'baz')"],
                [$oracle, "ENUM('foo', 'bar', 'baz')"],
                [$sqlite, 'INTEGER'],
            ],
        );

        $this->assertNull($type->convertToDatabaseValue(null, $postgres));
        $this->assertNull($type->convertToPHPValue(null, $postgres));

        $e = null;
        try {
            $type->convertToDatabaseValue('bax', $postgres);
        } catch (ConversionException $e) {
        }

        $this->assertNotNull($e);

        $this->assertValues(
            static fn ($platform) => $type->convertToDatabaseValue('bar', $platform),
            [
                [$postgres, 'bar'],
                [$mariadb, 'bar'],
                [$oracle, 'bar'],
                [$sqlite, 2],
            ],
        );

        $this->assertValues(
            static fn ($platform) => $type->convertToDatabaseValueSQL('?', $platform),
            [
                [$postgres, '?'],
                [$mariadb, '?'],
                [$oracle, '?'],
                [$sqlite, '?'],
            ],
        );

        $this->assertValues(
            static fn ($platform) => $type->convertToPHPValueSQL('?', $platform),
            [
                [$postgres, '?'],
                [$mariadb, '?'],
                [$oracle, '?'],
                [$sqlite, "CASE WHEN ? = 1 THEN 'foo' WHEN ? = 2 THEN 'bar' WHEN ? = 3 THEN 'baz' END"],
            ],
        );
    }
}

enum MyBackedEnum: string
{
    case FOO = 'foo';
    case BAR = 'bar';
    case BAZ = 'baz';
}
