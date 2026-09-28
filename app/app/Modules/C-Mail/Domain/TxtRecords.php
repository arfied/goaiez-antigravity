<?php

declare(strict_types=1);

namespace App\Modules\CMail\Domain;

interface TxtRecords
{
    /**
     * Every TXT string published at $name, or null when no lookup was possible.
     *
     * An empty array means "we asked and there is nothing there"; null means
     * "we could not ask" — and the two must not collapse, because one is a
     * missing record and the other is a missing answer.
     *
     * @return list<string>|null
     */
    public function txt(string $name): ?array;
}
