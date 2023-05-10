<?php

declare(strict_types=1);

namespace Manyou\Mango\RestApi;

interface Request
{
    public function getMethod(): string;

    public function getPath(): string;
}
