<?php

declare(strict_types=1);

namespace Manyou\Mango\RestApi;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface Client
{
    public function request(Request $request): ResponseInterface;
}
