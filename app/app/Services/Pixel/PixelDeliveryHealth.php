<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Models\PixelDeliverySample;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The only reader and writer of `pixel_delivery_samples` — the counter §10's
 * *"auto-halt on JS-error regression >0.5%"* reads.
 *
 * `SendingHealth`'s shape, one table over: atomic `INSERT … ON CONFLICT DO
 * UPDATE SET col = col + 1` for the writer, `COALESCE(SUM(…), 0)` for the
 * reader, minute buckets instead of hourly ones because a canary window is 60
 * minutes rather than a rolling 24 hours.
 *
 * ## Called from where the batch is already decoded, never re-decoding it
 *
 * {@see PixelCollector::receive()} has the payload as a
 * PHP array already, one call before it archives the raw bytes and dispatches
 * the async job. `observe()` takes that same array — it never touches
 * `ArchivePixelBatchJob`'s payload, which stays the exact received bytes,
 * undecoded, on decision 4874's rule.
 *
 * ## Only accepted batches count, on `SendingHealth`'s "3032" rule
 *
 * *"The denominator must have the same membership as the numerator."* A batch
 * the HIPAA gate refuses is never archived and must never be counted here
 * either — {@see PixelCollector::receive()} calls this only after every refusal
 * has already returned, so a canary's error rate is never computed over traffic
 * this platform declined to keep.
 */
final class PixelDeliveryHealth
{
    /**
     * Count one accepted batch's pageviews and `js_error`s against whichever
     * published version reported them.
     *
     * ⚠️ **A MISSING OR MALFORMED `bv` IS NOT AN ERROR.** Direct installs of an
     * un-versioned `/p.js` fetch, a cached bundle from before this system
     * existed, or a client that is not this codebase's own bundle all carry no
     * build token this table can key on — and that is untagged traffic this
     * canary system simply has nothing to say about, not a batch to refuse.
     *
     * @param  array<string, mixed>  $payload  the decoded batch, inspected only
     */
    public function observe(array $payload): void
    {
        $buildToken = $payload['bv'] ?? null;

        if (! is_string($buildToken) || ! preg_match('/^[0-9a-f]{32}$/', $buildToken)) {
            return;
        }

        $events = $payload['events'] ?? null;

        if (! is_array($events)) {
            return;
        }

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $event['type'] ?? null;

            if ($type === 'pageview') {
                $this->increment($buildToken, 'pageviews');
            } elseif ($type === 'js_error') {
                $this->increment($buildToken, 'js_errors');
            }
        }
    }

    /**
     * Summed pageviews and js_errors for one version since a moment.
     *
     * @return array{pageviews: int, js_errors: int}
     */
    public function ratesSince(string $buildToken, CarbonImmutable $since): array
    {
        /** @var object{pageviews: int|string|null, js_errors: int|string|null}|null $totals */
        $totals = PixelDeliverySample::query()
            ->where('build_token', $buildToken)
            ->where('bucket', '>=', $since->utc()->startOfMinute())
            ->selectRaw('COALESCE(SUM(pageviews), 0) AS pageviews')
            ->selectRaw('COALESCE(SUM(js_errors), 0) AS js_errors')
            ->first();

        return [
            'pageviews' => (int) ($totals->pageviews ?? 0),
            'js_errors' => (int) ($totals->js_errors ?? 0),
        ];
    }

    /**
     * The atomic increment. `SendingHealth::increment()`'s reasoning verbatim:
     * two collector requests landing in the same millisecond must never lose
     * one of the two increments to a read-modify-write race, so the database
     * does the addition rather than PHP — one event, one `+1`, one call, exactly
     * as `SendingHealth` does it, rather than a batch count interpolated into
     * the raw fragment (which PHPStan's `literal-string` check refuses for
     * anything but a fixed set of column names, for the reason that check
     * exists: an interpolated integer here is safe only because it happens to
     * come from a count, and the type system cannot tell that from a string a
     * caller built out of user input).
     *
     * @param  'pageviews'|'js_errors'  $column
     */
    private function increment(string $buildToken, string $column): void
    {
        $now = CarbonImmutable::now();
        $bucket = $now->utc()->startOfMinute();

        DB::table('pixel_delivery_samples')->upsert(
            [[
                'build_token' => $buildToken,
                'bucket' => $bucket,
                'pageviews' => $column === 'pageviews' ? 1 : 0,
                'js_errors' => $column === 'js_errors' ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['build_token', 'bucket'],
            [
                $column => DB::raw("pixel_delivery_samples.{$column} + 1"),
                'updated_at' => $now,
            ],
        );
    }
}
