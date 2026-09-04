<?php
declare(strict_types=1);
namespace App\Modules\X171\Domain;
final class SyncEngine
{
    public function isOfflineFirst(): bool
    {
        return true;
    }
}
