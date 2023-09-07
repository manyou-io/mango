<?php

declare(strict_types=1);

namespace Mango\RestApi;

use Symfony\Component\Serializer\Annotation\Ignore;

interface Request
{
    #[Ignore]
    public function getMethod(): string;

    #[Ignore]
    public function getPath(): string;
}
