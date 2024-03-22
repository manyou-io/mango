<?php

declare(strict_types=1);

namespace Mango\Doctrine\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\OCI8;
use Doctrine\DBAL\Driver\OCI8\Middleware\InitializeSession;

class Oci8InitializeSession implements Middleware
{
    private readonly Middleware $decorated;

    public function __construct()
    {
        $this->decorated = new InitializeSession();
    }

    public function wrap(Driver $driver): Driver
    {
        if ($driver instanceof OCI8\Driver) {
            return $this->decorated->wrap($driver);
        }

        return $driver;
    }
}
