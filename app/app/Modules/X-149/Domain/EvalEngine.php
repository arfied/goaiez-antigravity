<?php

declare(strict_types=1);

namespace App\Modules\X149\Domain;

use InvalidArgumentException;

final class EvalEngine
{
    public function enforcePersonaSplitIsTest(string $actionType): void
    {
        if ($actionType === 'permit_change') {
            throw new InvalidArgumentException('Persona split must be a test, never a permit change');
        }
    }

    public function enforceSingleDatabaseCorpus(int $databaseCount): void
    {
        if ($databaseCount !== 1) {
            throw new InvalidArgumentException('Must use a single database corpus');
        }
    }
}
