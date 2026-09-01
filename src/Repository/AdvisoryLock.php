<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class AdvisoryLock
{
    public function __construct(private Connection $connection)
    {
    }

    public function acquire(string $resource): void
    {
        $this->connection->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtextextended(:resource, 0))',
            ['resource' => $resource],
        );
    }
}
