<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Enums\PixelBundleStatus;
use App\Models\PixelBundleVersion;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The only reader and writer of `pixel_bundle_versions` — `GOAIEZ_PIXEL_MASTER_
 * BUILD.md` §10's Delivery paragraph: *"immutable `/v/<sha>/p.js` … `/p.js`
 * pointer … Rollback = repoint, one command … Canary 1% for 60 min, auto-halt
 * on JS-error regression >0.5%."*
 *
 * ---------------------------------------------------------------------------
 * WHY `build_token` IS STAMPED IN BEFORE THE HASH, NOT DERIVED FROM IT
 * ---------------------------------------------------------------------------
 * `<sha>` has to be `sha256(the exact bytes `/v/<sha>/p.js` serves)`, because
 * that is what makes the route content-addressed and safe to cache for a year —
 * two publishes of identical content collide on it by design. `pixel.js` also
 * has to be able to report, on every batch it sends, which published version
 * produced it, or {@see PixelDeliveryHealth} has nothing to
 * key a rate by. **Both cannot be the same value.** A build id computed from the
 * bytes it is then embedded in has no fixed point in general — embedding a hash
 * changes the bytes, which changes the hash — so the two are deliberately two
 * different values: `build_token` is minted first, stamped into the source's
 * `__GOAIEZ_PIXEL_BUILD_TOKEN__` placeholder, and `sha` is computed over the
 * result. Two rows can never collide on `build_token` because it is random, and
 * two rows can never collide on `sha` unless the stamped bytes are identical —
 * both are `unique()` in the creating migration for exactly that reason.
 *
 * ---------------------------------------------------------------------------
 * WHY BYTES LIVE IN THE ROW
 * ---------------------------------------------------------------------------
 * See the creating migration. In short: this is a few kilobytes of JavaScript
 * under §10's 14 KB gzipped budget, nothing like L0's seven-year event archive,
 * and Postgres already backs this table up — a storage disk would be new
 * infrastructure to operate for a value smaller than the row that would point
 * at it.
 *
 * ---------------------------------------------------------------------------
 * WHAT "AUTO-HALT" MEANS HERE, AND WHAT IT DOES NOT
 * ---------------------------------------------------------------------------
 * ⚠️ **HALTING A CANARY STOPS `/p.js` OFFERING IT TO NEW REQUESTS. IT DOES NOT,
 * AND CANNOT, UN-SERVE IT FROM A BROWSER THAT ALREADY LOADED IT.** A script tag
 * that already ran is running; nothing server-side reaches back into a tab.
 * What the halt actually buys is bounded exposure — at most
 * `pixel.canary_percent_bp` of pageviews for at most one sweep interval past
 * whenever the regression crossed the threshold — which is the whole promise a
 * 1% canary window was ever making.
 */
final class PixelDelivery
{
    /**
     * §10's own number, `PixelTest`'s gate on the artefact this class stores.
     * Read off the bundle rather than remembered, `CLAUDE.md`'s rule applied to
     * our own build: publishing a bundle over budget would put a version behind
     * `/p.js` that the CI gate would have refused to ship.
     */
    public const int MAX_GZIP_BYTES = 14 * 1024;

    /**
     * The exact placeholder `resources/js/pixel.js` carries unstamped. Any other
     * count of occurrences in the built artefact is refused rather than guessed
     * at — replacing zero would silently ship a version that can never be
     * attributed to a rate, and replacing more than one means the source changed
     * out from under this class without this class changing with it.
     */
    private const string PLACEHOLDER = '__GOAIEZ_PIXEL_BUILD_TOKEN__';

    public function __construct(
        private readonly Vite $vite,
        private readonly DefaultsRegistry $registry,
    ) {}

    /**
     * Build, stamp, hash and store the current source as a new version.
     *
     * ⚠️ **THE FIRST EVER PUBLISH GOES STRAIGHT TO {@see PixelBundleStatus::Active}.**
     * A canary compares itself against an Active baseline — see
     * {@see PixelDeliveryHealth} — so a canary with nothing to compare against
     * would either be judged against zero traffic (256's vacuity) or serve no
     * one at all until promoted, which is not what "publish" means on a bundle
     * `/p.js` has never had a version of before.
     *
     * @throws RuntimeException when a canary is already live, the artefact
     *                          cannot be read, the placeholder is missing or
     *                          duplicated, or the stamped bytes exceed
     *                          {@see self::MAX_GZIP_BYTES} gzipped
     */
    public function publish(string $actor): PixelBundleVersion
    {
        if ($this->currentCanary() instanceof PixelBundleVersion) {
            throw new RuntimeException(
                'A canary is already live. Let pixel:watch-canary resolve it, or halt it with '
                .'pixel:rollback, before publishing another.'
            );
        }

        $source = $this->builtSource();

        $occurrences = substr_count($source, self::PLACEHOLDER);

        if ($occurrences !== 1) {
            throw new RuntimeException(
                "The built pixel bundle carries the build-token placeholder {$occurrences} "
                .'time(s); it must carry it exactly once. resources/js/pixel.js has changed in '
                .'a way this class has not.'
            );
        }

        $buildToken = bin2hex(random_bytes(16));
        $stamped = str_replace(self::PLACEHOLDER, $buildToken, $source);
        $sha = hash('sha256', $stamped);

        // ⛔ **THERE IS DELIBERATELY NO "THESE BYTES ARE ALREADY PUBLISHED"
        // REFUSAL HERE, AND THERE WAS ONE UNTIL IT WAS DRIVEN AT (decision
        // 4989).** It read `where('sha', $sha)->exists()` and its message said
        // *"these exact bytes have not changed since the last publish"* — a
        // check it was not making and could not make. **`$stamped` carries a
        // fresh 128-bit random build token**, minted three lines above, so the
        // bytes differ on every call by construction and the branch was
        // unreachable at odds of 2^-128. A refusal that cannot fire, carrying a
        // message describing a protection it does not provide, is 314–316's
        // shape: what it actually did was stop the next reader wondering
        // whether republishing an unchanged bundle is allowed.
        //
        // **It is allowed, and it is the design.** Two publishes of an
        // identical source are two distinct, individually-addressable versions
        // — that is what `/v/<sha>/p.js` being addressed by the bytes it
        // *serves* means, and `a second publish while an Active version exists
        // goes live as a Canary` pins it.
        //
        // What guards against an actual sha collision is `sha`'s `unique()`
        // index in the creating migration — which is where a guarantee two
        // concurrent publishes cannot both pass belongs anyway, on this
        // migration's own reasoning for the two partial indexes beside it.
        //
        // ⚠️ **THE CONSEQUENCE IS OPERATIONAL AND IS WHY `pixel:publish` IS NOT
        // A `composer deploy` STEP** — see `RUNBOOK.md`: every run publishes a
        // new canary whether or not the bundle changed.

        $gzipSize = strlen((string) gzencode($stamped, 9));

        if ($gzipSize > self::MAX_GZIP_BYTES) {
            throw new RuntimeException(
                "The stamped bundle is {$gzipSize} bytes gzipped, over the ".self::MAX_GZIP_BYTES
                .' byte budget §10 sets. Publishing it would put an over-budget version behind '
                .'/p.js.'
            );
        }

        $hasActive = $this->currentActive() instanceof PixelBundleVersion;
        $now = Carbon::now();

        return PixelBundleVersion::query()->create([
            'sha' => $sha,
            'build_token' => $buildToken,
            'contents' => $stamped,
            'byte_size' => strlen($stamped),
            'gzip_byte_size' => $gzipSize,
            'status' => $hasActive ? PixelBundleStatus::Canary->value : PixelBundleStatus::Active->value,
            'published_at' => $now,
            'canary_started_at' => $hasActive ? $now : null,
            'promoted_at' => $hasActive ? null : $now,
            'actor' => $actor,
        ]);
    }

    /**
     * The row `/v/<sha>/p.js` serves, or null for a sha nothing published.
     *
     * ⚠️ **SERVES A ROLLED-BACK OR RETIRED VERSION TOO, DELIBERATELY.** The
     * whole point of a content-addressed, immutable route is that a URL already
     * handed out — pinned in a cached page, or read back for a rollback — keeps
     * answering for ever. Only `/p.js`'s *pointer* is affected by status.
     */
    public function versionForSha(string $sha): ?PixelBundleVersion
    {
        return PixelBundleVersion::query()->where('sha', $sha)->first();
    }

    public function currentActive(): ?PixelBundleVersion
    {
        return PixelBundleVersion::query()
            ->where('status', PixelBundleStatus::Active->value)
            ->first();
    }

    public function currentCanary(): ?PixelBundleVersion
    {
        return PixelBundleVersion::query()
            ->where('status', PixelBundleStatus::Canary->value)
            ->first();
    }

    /**
     * What `/p.js` serves for one request — §10's 1% coin flip.
     *
     * ⚠️ **A FRESH ROLL PER REQUEST, NOT A STICKY ASSIGNMENT.** `/p.js` is
     * fetched by a classic `<script>` tag before any cookie or storage this
     * bundle could read exists — there is no visitor identity yet for an
     * assignment to stick to. A canary window bounds *exposure* (at most 1% of
     * requests, for at most one sweep past a regression), not *which visitor*
     * sees which version, and §10 asks for the former.
     *
     * @throws RuntimeException when nothing has ever been published
     */
    public function choose(): PixelBundleVersion
    {
        $active = $this->currentActive();
        $canary = $this->currentCanary();

        if (! $active instanceof PixelBundleVersion) {
            throw new RuntimeException(
                'No pixel bundle has been published yet. Run pixel:publish.'
            );
        }

        if (! $canary instanceof PixelBundleVersion) {
            return $active;
        }

        $percentBp = $this->registry->int('pixel.canary_percent_bp');

        return random_int(0, 9_999) < $percentBp ? $canary : $active;
    }

    /**
     * §10's *"Rollback = repoint, one command, <5 min"*.
     *
     * Promotes an already-published version back to {@see PixelBundleStatus::Active}
     * and retires whatever was Active. **Halts a live canary in the same call**
     * — a rollback naming an older Active is not compatible with a canary still
     * being offered to 1% of traffic underneath it.
     *
     * @throws RuntimeException when $sha names no published version, or names
     *                          the version that is already Active
     */
    public function rollback(string $sha, string $actor): PixelBundleVersion
    {
        $target = $this->versionForSha($sha);

        if (! $target instanceof PixelBundleVersion) {
            throw new RuntimeException(
                "No published version has sha {$sha}. Nothing was changed."
            );
        }

        if ($target->status === PixelBundleStatus::Active) {
            throw new RuntimeException(
                "Version {$sha} is already Active. Nothing was changed."
            );
        }

        return DB::transaction(function () use ($target, $actor): PixelBundleVersion {
            $active = $this->currentActive();

            if ($active instanceof PixelBundleVersion) {
                $active->update(['status' => PixelBundleStatus::Retired->value]);
            }

            $canary = $this->currentCanary();

            if ($canary instanceof PixelBundleVersion) {
                $canary->update([
                    'status' => PixelBundleStatus::RolledBack->value,
                    'halted_at' => Carbon::now(),
                    'halt_reason' => "superseded by a rollback to {$target->sha}, by {$actor}",
                ]);
            }

            $target->update([
                'status' => PixelBundleStatus::Active->value,
                'promoted_at' => Carbon::now(),
                'halted_at' => null,
                'halt_reason' => null,
            ]);

            return $target->refresh();
        });
    }

    /**
     * The watch's success arm — the canary window elapsed with no regression.
     *
     * ⚠️ **NO ACTOR PARAMETER.** `pixel_bundle_versions.actor` names who
     * published a row, not who last moved its status — `PlanOffer`'s row never
     * gains a second actor for a status change either. `WatchPixelCanary`'s own
     * log line is where a promotion it triggered is recorded.
     */
    public function promoteCanary(PixelBundleVersion $canary): void
    {
        DB::transaction(function () use ($canary): void {
            $active = $this->currentActive();

            if ($active instanceof PixelBundleVersion) {
                $active->update(['status' => PixelBundleStatus::Retired->value]);
            }

            $canary->update([
                'status' => PixelBundleStatus::Active->value,
                'promoted_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * The watch's failure arm — the canary tripped the regression threshold.
     */
    public function haltCanary(PixelBundleVersion $canary, string $reason): void
    {
        $canary->update([
            'status' => PixelBundleStatus::RolledBack->value,
            'halted_at' => Carbon::now(),
            'halt_reason' => mb_substr($reason, 0, 500),
        ]);
    }

    /**
     * The built artefact's bytes, unstamped.
     *
     * `WidgetScriptController::bundle()`'s pattern exactly, for its reason:
     * `Vite::content()` throws when there is no build, which is every test run
     * before `npm run build`, and falling back to the raw source keeps
     * `pixel:publish` runnable in a checkout that has never built.
     *
     * @throws RuntimeException when neither a build nor the source file exists
     */
    private function builtSource(): string
    {
        try {
            return $this->vite->content('resources/js/pixel.js');
        } catch (Throwable) {
            $source = resource_path('js/pixel.js');
            $contents = is_file($source) ? file_get_contents($source) : false;

            if ($contents === false) {
                throw new RuntimeException('resources/js/pixel.js does not exist.');
            }

            return $contents;
        }
    }
}
