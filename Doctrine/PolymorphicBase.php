<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine;

use Manyou\Mango\Doctrine\Contract\PolymorphicSubType;
use Symfony\Component\Uid\Ulid;

trait PolymorphicBase
{
    /** @var PolymorphicSubType[] */
    private iterable $subTypes;

    private SchemaProvider $schema;

    private string $tableName;

    public function findById(Ulid $id)
    {
        $prefix  = 't';
        $counter = 0;

        $q = $this->schema->createQuery();
        $q->selectFrom([
            $this->tableName,
            $baseAlias = $prefix . $counter++,
        ]);

        /** @var PolymorphicSubType[] */
        $subTypeMap = [];
        foreach ($this->subTypes as $subType) {
            $subTypeAlias = $prefix . $counter++;
            $q->leftJoin(
                $baseAlias,
                $subType->getTableName(),
                $subTypeAlias,
                $subTypeAlias . '.id = ' . $baseAlias . '.id',
                'id',
            );
            $subTypeMap[$subTypeAlias] = $subType;
        }

        $q->where($q->eq($baseAlias . '.id', $id))->setMaxResults(1);
        if (false === $row = $q->fetchAssociative()) {
            return null;
        }

        $baseResult = $row[$baseAlias];
        unset($row[$baseAlias]);

        foreach ($row as $subTypeAlias => $subTypeResult) {
            if (! isset($subTypeResult['id'])) {
                continue;
            }

            return [$subTypeMap[$subTypeAlias], $baseResult];
        }

        return null;
    }
}
