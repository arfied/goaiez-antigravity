<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pixel\PixelDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * §10's *"Rollback = repoint, one command, <5 min"* — the off switch a
 * fail-forward delivery pipeline must have, `offers:close`'s own lesson
 * (decision 4345): a fail-open control with no command behind it is one whose
 * only supported release is `php artisan tinker` against production, with no
 * audit actor and nothing written down.
 *
 * ⚠️ **HALTS A LIVE CANARY IN THE SAME CALL.** See {@see
 * \App\Services\Pixel\PixelDelivery::rollback()} — repointing `/p.js` at an
 * older Active while a canary keeps taking 1% of traffic underneath it is not a
 * rollback, it is a rollback with a hole in it.
 */
#[Signature('pixel:rollback {sha : The sha of a previously published version — 64 hex characters}')]
#[Description('Repoint /p.js at a previously published pixel bundle version, and halt any live canary')]
final class RollbackPixelBundle extends Command
{
    /** `PublishPixelBundle::ACTOR`'s reasoning. */
    public const string ACTOR = 'pixel:rollback';

    public function handle(PixelDelivery $delivery): int
    {
        $sha = (string) $this->argument('sha');

        try {
            $version = $delivery->rollback($sha, self::ACTOR);
        } catch (RuntimeException $refusal) {
            $this->error($refusal->getMessage());

            return self::FAILURE;
        }

        $this->info("/p.js now serves {$version->sha}.");

        return self::SUCCESS;
    }
}
