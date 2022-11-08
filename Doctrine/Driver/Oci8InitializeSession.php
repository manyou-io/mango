<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\OCI8\Driver as OCI8Driver;
use Doctrine\DBAL\Driver\OCI8\Middleware\InitializeSession;

class Oci8InitializeSession extends InitializeSession
{
    public function wrap(Driver $driver): Driver
    {
        if ($driver instanceof OCI8Driver) {
            return parent::wrap($driver);
        }

        return $driver;
    }
}
