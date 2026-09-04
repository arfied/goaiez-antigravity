<?php

declare(strict_types=1);

namespace App\Modules\X180\Domain;

final class PackEngine
{
    public function ensureAdPacksNotFenced(array $pack): bool
    {
        return ! isset($pack['fenced']) || $pack['fenced'] === false;
    }

    public function verifyClaim(string $claim, string $source): bool
    {
        return ! empty($source);
    }
}
