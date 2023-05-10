<?php

declare(strict_types=1);

namespace Manyou\Mango\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
class Naive extends Constraint
{
    public function __construct(public string $validator, ?array $groups = null)
    {
        parent::__construct(groups: $groups);
    }

    public function validatedBy(): string
    {
        return $this->validator;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
