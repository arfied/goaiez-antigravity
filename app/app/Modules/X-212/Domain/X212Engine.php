<?php

declare(strict_types=1);

namespace App\Modules\X212\Domain;

final class X212Engine
{
    public function validateImport(array $records): void
    {
        foreach ($records as $record) {
            if (empty($record['phone']) && empty($record['email'])) {
                throw new \DomainException('REFUSES: weak identifier rejected');
            }
        }
    }
}
