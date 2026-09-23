<?php

namespace App\Modules\X129\Domain;

class MigrationEngine
{
    public function migrate($oldUrls)
    {
        return ['status' => 'migrated', 'redirects' => count($oldUrls)];
    }

    public function get404s($newHost)
    {
        return 0;
    }

    public function canCutover($has404)
    {
        return ! $has404;
    }

    public function ensureReversible(bool $dnsPropagated, bool $cutoverComplete)
    {
        if ($cutoverComplete && $dnsPropagated) {
            throw new \RuntimeException('Cutover cannot be reversed once DNS propagates');
        }

        return true;
    }
}
