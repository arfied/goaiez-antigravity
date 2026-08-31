<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PixelBundleStatus;
use App\Services\Pixel\PixelDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Build, stamp and store a new pixel bundle version — `GOAIEZ_PIXEL_MASTER_
 * BUILD.md` §10's Delivery paragraph, decision 4980.
 *
 * Run after `npm run build`. `PixelDelivery::publish()` does the actual work;
 * this is the shell around it, `offers:close`'s shape — an actor recorded, a
 * refusal printed rather than thrown, an exit code a deploy script can check.
 */
#[Signature('pixel:publish')]
#[Description('Build and publish a new pixel bundle version, live as a canary (or Active, if nothing has been published yet)')]
final class PublishPixelBundle extends Command
{
    /**
     * The actor recorded against the change. `CloseOffer::ACTOR`'s reasoning: a
     * label rather than a user id, because nobody is signed in to a shell.
     */
    public const string ACTOR = 'pixel:publish';

    public function handle(PixelDelivery $delivery): int
    {
        try {
            $version = $delivery->publish(self::ACTOR);
        } catch (RuntimeException $refusal) {
            $this->error($refusal->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Published %s as %s (%d bytes, %d gzipped).',
            $version->sha,
            $version->status->value,
            $version->byte_size,
            $version->gzip_byte_size,
        ));

        if ($version->status === PixelBundleStatus::Canary) {
            $this->line(
                'Live as a canary now. pixel:watch-canary promotes it automatically if no '
                .'regression is found inside its window, or halts it if one is.'
            );
        }

        return self::SUCCESS;
    }
}
