<?php

declare(strict_types=1);

namespace Mango\Tests\Doctrine\Type;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Mango\Doctrine\Type\UlidType;
use Mango\Doctrine\Type\UuidType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

use function fopen;
use function fwrite;
use function hex2bin;
use function rewind;

class UidTypeTest extends TestCase
{
    use TestUtils;

    public function testUuidType()
    {
        $uuidType = new UuidType();
        $ulidType = new UlidType();

        $postgres = new PostgreSQLPlatform();
        $mariadb  = new MariaDBPlatform();
        $oracle   = new OraclePlatform();
        $sqlite   = new SqlitePlatform();

        $this->assertValues(
            static fn ($platform) => $uuidType->getSQLDeclaration([], $platform),
            [
                [$postgres, 'UUID'],
                [$mariadb, 'UUID'],
                [$oracle, 'RAW(16)'],
                [$sqlite, 'BLOB'],
            ],
        );

        $this->assertNull($uuidType->convertToDatabaseValue(null, $postgres));
        $this->assertNull($uuidType->convertToDatabaseValue('', $postgres));
        $this->assertNull($uuidType->convertToPHPValue(null, $postgres));

        $this->assertValues(
            static fn ($platform) => $uuidType->convertToDatabaseValue('ABA0E360-1E04-41B3-91A0-1F2263E1E0FB', $platform),
            [
                [$postgres, 'aba0e360-1e04-41b3-91a0-1f2263e1e0fb'],
                [$mariadb, 'aba0e360-1e04-41b3-91a0-1f2263e1e0fb'],
                [$oracle, 'aba0e3601e0441b391a01f2263e1e0fb'],
                [$sqlite, hex2bin('aba0e3601e0441b391a01f2263e1e0fb')],
            ],
        );

        $this->assertValues(
            static fn ($platform) => $ulidType->convertToDatabaseValue('01GN44MDFW99TFQ8TGDKH38Z3V', $platform),
            [
                [$postgres, '0185484a-35fc-4a74-fba3-506ce2347c7b'],
                [$mariadb, '0185484a-35fc-4a74-fba3-506ce2347c7b'],
                [$oracle, '0185484a35fc4a74fba3506ce2347c7b'],
                [$sqlite, hex2bin('0185484a35fc4a74fba3506ce2347c7b')],
            ],
        );

        $this->assertValues(
            static fn ($platform) => $uuidType->convertToDatabaseValue(new Uuid('00000000-0000-0000-0000-000000000000'), $platform),
            [
                [$postgres, '00000000-0000-0000-0000-000000000000'],
                [$mariadb, '00000000-0000-0000-0000-000000000000'],
                [$oracle, '00000000000000000000000000000000'],
                [$sqlite, hex2bin('00000000000000000000000000000000')],
            ],
        );

        $this->assertValues(
            static fn ($platform) => $uuidType->convertToDatabaseValueSQL('?', $platform),
            [
                [$postgres, '?'],
                [$mariadb, '?'],
                [$oracle, 'HEXTORAW(?)'],
                [$sqlite, '?'],
            ],
        );

        $this->assertValues(
            static fn ($platform) => $uuidType->convertToPHPValueSQL('?', $platform),
            [
                [$postgres, '?'],
                [$mariadb, '?'],
                [$oracle, '?'],
                [$sqlite, '?'],
            ],
        );

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, hex2bin('6295eb16c80e4050a2b3c31a6ad8b4a6'));
        rewind($stream);

        $this->assertValues(
            static fn ($value) => $uuidType->convertToPHPValue($value, $postgres),
            [
                ['aba0e360-1e04-41b3-91a0-1f2263e1e0fb', new UuidV4('aba0e360-1e04-41b3-91a0-1f2263e1e0fb')],
                ['00000000-0000-0000-0000-000000000000', new NilUuid('00000000-0000-0000-0000-000000000000')],
                ['8A1F18FA-7B29-4524-A12E-426C453AF857', new UuidV4('8a1f18fa-7b29-4524-a12e-426c453af857')],
                [$stream, new UuidV4('6295eb16-c80e-4050-a2b3-c31a6ad8b4a6')],
            ],
        );

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, hex2bin('0185484c17ea9421382d3d3acad4c2da'));
        rewind($stream);

        $this->assertValues(
            static fn ($value) => $ulidType->convertToPHPValue($value, $postgres),
            [
                ['0185484d-1269-6832-02b4-3bacb0091f2c', new Ulid('01GN44T4K9D0S05D1VNJR0J7SC')],
                ['0185484D-6E25-78C2-8D1B-737D51F1C375', new Ulid('01GN44TVH5F318T6VKFN8Z3GVN')],
                [$stream, new Ulid('01GN44R5ZAJGGKGB9X7B5D9GPT')],
            ],
        );
    }
}
