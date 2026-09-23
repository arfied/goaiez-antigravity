<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\ProofHashDomain;
use App\Listeners\RecordFailedSignIn;
use App\Models\FailedSignIn;
use App\Services\Config\DefaultsRegistry;
use App\Support\HashedIp;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The register of failed credential checks — decisions 9860–9879.
 *
 * ⛔ **THE ONLY FILE IN `app/` ALLOWED TO READ OR WRITE `failed_sign_ins`**,
 * held by a lint in `tests/Feature/Auth/FailedSignInRecordTest.php`. Reading is
 * included, on decision 624's rule: the table has no tenant and no row-level
 * security, so nothing beneath the application layer refuses a stray read of
 * which addresses somebody tried, and the chokepoint is the whole protection.
 *
 * ## ⛔ WHAT THIS DOES NOT DO
 *
 * It refuses nobody. 9723 measured the spraying gap on `POST /login` and
 * refused a lockout, a captcha and a stuffing detector **by arithmetic** —
 * spraying is defined by being low volume per address, so no per-source ceiling
 * separates it from a large tenant's office at nine in the morning, and all
 * three are a support surface the owner owns. **This makes the attack visible
 * and moves no limit.** Nothing here may grow a refusal without a ruling.
 *
 * ## ⚠️ THE FINGERPRINT, AND WHY IT IS NOT `HashedIp`
 *
 * `hash_hmac('sha256', 'failed_sign_ins|'.$address, APP_KEY)` — the
 * construction `TrialEligibility::identityHash()` uses and
 * {@see ProofHashDomain} argues for, domain-separated so this
 * register's fingerprints stay comparable with each other and **with nothing
 * else**. It is deliberately *not* `HashedIp::hash()`: that function is named
 * for what it hashes, and feeding an address through it would make two
 * different kinds of identifier collide in one namespace for no gain.
 *
 * ⚠️ **`APP_KEY` ROTATION MAKES EVERY STORED FINGERPRINT UNMATCHABLE, SILENTLY**
 * — `HashedIp`'s hazard (8080), inherited whole, and it fails in the same
 * harmless direction: a report reads as a quiet week rather than refusing
 * anybody, which is why this is documented here rather than gated by
 * `IdentifierHashEpochs`. A register that refused sign-ins because the key
 * moved would lock every operator out on the day they most need to be in.
 *
 * ⚠️ **NORMALISED WITH `Str::lower(trim())` AND DELIBERATELY NOT
 * `Str::transliterate()`.** The `login` limiter transliterates because a
 * limiter key may collide harmlessly — two addresses sharing a bucket costs one
 * of them four attempts a minute. A *register* that collides two addresses
 * reports a spray as a hammering, which is the one distinction it exists to
 * draw. Fortify's `CanonicalizeUsername` has already lowercased the submitted
 * value by the time the event fires; the normalisation here is what makes the
 * fingerprint stable for a caller that has not been through it — the
 * `--email` lookup below, which an operator types by hand.
 */
final class FailedSignIns
{
    /**
     * How long a bucket covers, in minutes.
     *
     * ⚠️ **AN HOUR IS THE UNIT THE QUESTION IS ASKED IN**, and 9723's own
     * arithmetic is stated per hour: 5 a minute × 60 per (address, source). A
     * finer bucket is a bigger table for a report nobody reads at that
     * resolution; a coarser one cannot separate a burst from a slow drip
     * inside a working day.
     */
    public const int WINDOW_MINUTES = 60;

    /**
     * Record one failed credential check.
     *
     * ⛔ **BUCKETED, AND THE UPSERT IS THE WHOLE REASON THIS IS RAW SQL.**
     * `attempts = attempts + 1` is not something Eloquent's `upsert()` can
     * express — it sets a column to a literal — and a read-modify-write through
     * the model would lose counts under exactly the concurrency this register
     * exists to measure. One statement, atomic in Postgres, no lock taken by us.
     *
     * ⛔ **AND IT DOES NOT SWALLOW.** A throw here fails the request, and that
     * is the correct direction: the realistic cause is a database that is
     * already refusing the credential check itself, and a register that
     * silently stops recording is precisely the shape this slice exists to
     * remove. `RecordSuccessfulLogin` does not catch either.
     *
     * ⚠️ **`GREATEST` ON `last_seen_at` RATHER THAN THE EXCLUDED VALUE.** Two
     * application servers with a second of clock skew would otherwise be able
     * to move the column backwards past `first_seen_at`, which the table's own
     * CHECK refuses — turning a skew into a 500 on the login path.
     */
    public function __construct(
        private readonly DefaultsRegistry $defaults,
    ) {}

    public function record(string $address, ?string $ipHash, bool $accountExisted, CarbonImmutable $at): void
    {
        $window = $at->floorMinutes($this->windowMinutes());

        DB::statement(<<<'SQL'
            INSERT INTO failed_sign_ins
                (email_hash, ip_hash, window_start, account_existed, attempts, first_seen_at, last_seen_at)
            VALUES (?, ?, ?, ?, 1, ?, ?)
            ON CONFLICT (email_hash, ip_hash, window_start) DO UPDATE SET
                attempts        = failed_sign_ins.attempts + 1,
                account_existed = excluded.account_existed,
                last_seen_at    = GREATEST(failed_sign_ins.last_seen_at, excluded.last_seen_at)
        SQL, [
            self::fingerprint($address),
            $ipHash,
            $window,
            $accountExisted,
            $at,
            $at,
        ]);
    }

    /**
     * The stored form of an address.
     *
     * Public because an operator asking *"is this specific person being
     * targeted"* has the address in cleartext and the table does not — see
     * `auth:failed-sign-ins --email=`. {@see RecordFailedSignIn} is the
     * only other caller.
     */
    public static function fingerprint(string $address): string
    {
        return hash_hmac(
            'sha256',
            'failed_sign_ins|'.Str::lower(trim($address)),
            (string) config('app.key'),
        );
    }

    /**
     * The six figures the report leads with.
     *
     * ⛔ **`COUNT(DISTINCT ip_hash)` DOES NOT COUNT A NULL SOURCE, SO THE
     * SOURCELESS ATTEMPTS ARE RETURNED SEPARATELY AND THE TWO FIGURES
     * RECONCILE.** A console-driven attempt has no client address
     * ({@see HashedIp}), and folding it in would put a fact about our own
     * machine into a count of the internet.
     *
     * ⚠️ **RETURNING ONLY THE `COUNT(DISTINCT)` WAS THE FIRST DRAFT AND IT MADE
     * THE REPORT CONTRADICT ITSELF ON ONE SCREEN** (9876): the header said
     * *"2 sources"* above a three-row table, because a sourceless bucket is a
     * row and correctly is not a source. Twenty tests were green and the defect
     * was visible only in the rendered output. **A figure a reader can disprove
     * by counting the rows under it is worse than no figure.**
     *
     * @return array{attempts: int, addresses: int, sources: int, sourceless_attempts: int, attempts_on_accounts: int, addresses_with_accounts: int}
     */
    public function summarySince(CarbonImmutable $since): array
    {
        /** @var object{attempts: int|string, addresses: int|string, sources: int|string, sourceless_attempts: int|string, attempts_on_accounts: int|string, addresses_with_accounts: int|string}|null $row */
        $row = FailedSignIn::query()
            ->where('window_start', '>=', $since->floorMinutes($this->windowMinutes()))
            ->selectRaw('COALESCE(SUM(attempts), 0) AS attempts')
            ->selectRaw('COUNT(DISTINCT email_hash) AS addresses')
            ->selectRaw('COUNT(DISTINCT ip_hash) AS sources')
            ->selectRaw('COALESCE(SUM(attempts) FILTER (WHERE ip_hash IS NULL), 0) AS sourceless_attempts')
            ->selectRaw('COALESCE(SUM(attempts) FILTER (WHERE account_existed), 0) AS attempts_on_accounts')
            ->selectRaw('COUNT(DISTINCT email_hash) FILTER (WHERE account_existed) AS addresses_with_accounts')
            ->toBase()
            ->first();

        return [
            'attempts' => (int) ($row->attempts ?? 0),
            'addresses' => (int) ($row->addresses ?? 0),
            'sources' => (int) ($row->sources ?? 0),
            'sourceless_attempts' => (int) ($row->sourceless_attempts ?? 0),
            'attempts_on_accounts' => (int) ($row->attempts_on_accounts ?? 0),
            'addresses_with_accounts' => (int) ($row->addresses_with_accounts ?? 0),
        ];
    }

    /**
     * Sources ordered by how many DISTINCT addresses they tried.
     *
     * ⛔ **BY BREADTH AND NOT BY VOLUME, AND THAT IS THE WHOLE REPORT.** A
     * source ordered by attempts puts one person mistyping their own password
     * at the top. **Spraying is defined by breadth** (9723), so the column that
     * separates it from ordinary failure is the count of addresses, and it is
     * the one this orders by.
     *
     * @return Collection<int, FailedSignIn>
     */
    public function widestSourcesSince(CarbonImmutable $since, int $limit): Collection
    {
        return FailedSignIn::query()
            ->where('window_start', '>=', $since->floorMinutes($this->windowMinutes()))
            ->selectRaw('ip_hash')
            ->selectRaw('COUNT(DISTINCT email_hash) AS addresses')
            ->selectRaw('SUM(attempts) AS attempts')
            ->selectRaw('COALESCE(SUM(attempts) FILTER (WHERE account_existed), 0) AS attempts_on_accounts')
            ->selectRaw('MAX(last_seen_at) AS last_seen_at')
            ->groupBy('ip_hash')
            // ⚠️ `orderByRaw … NULLS LAST` RATHER THAN `orderByDesc`, WHICH IS
            // `ConventionsTest`'s PRESCRIBED SPELLING AND NOT AN EXEMPTION.
            // Postgres sorts NULL FIRST on a DESC order (289), so one undated or
            // un-summed row would sit above every real one for ever. Both of
            // these are aggregates over a NOT NULL column and cannot be null —
            // which is exactly the argument a `$permitted` entry would have had
            // to make in a file this lane does not need to touch, and *three
            // comments are not a mechanism* is that lint's own header.
            ->orderByRaw('addresses DESC NULLS LAST')
            ->orderByRaw('attempts DESC NULLS LAST')
            // `ip_hash` last so the order is total: two sources with the same
            // breadth and the same volume must not swap between two runs of the
            // same report (289's rule, one screen down). Ascending, where
            // Postgres puts NULL last anyway, so the sourceless bucket has a
            // fixed place rather than a lucky one.
            ->orderBy('ip_hash')
            ->limit($limit)
            ->get();
    }

    /**
     * Addresses ordered by how hard they were hit.
     *
     * @return Collection<int, FailedSignIn>
     */
    public function mostTargetedSince(CarbonImmutable $since, int $limit): Collection
    {
        return FailedSignIn::query()
            ->where('window_start', '>=', $since->floorMinutes($this->windowMinutes()))
            ->selectRaw('email_hash')
            ->selectRaw('SUM(attempts) AS attempts')
            ->selectRaw('COUNT(DISTINCT ip_hash) AS sources')
            ->selectRaw('bool_or(account_existed) AS account_existed')
            ->selectRaw('MAX(last_seen_at) AS last_seen_at')
            ->groupBy('email_hash')
            ->orderByRaw('attempts DESC NULLS LAST')
            ->orderBy('email_hash')
            ->limit($limit)
            ->get();
    }

    /**
     * Every bucket for one address, newest first.
     *
     * @return Collection<int, FailedSignIn>
     */
    public function forAddressSince(string $address, CarbonImmutable $since): Collection
    {
        return FailedSignIn::query()
            ->where('email_hash', self::fingerprint($address))
            ->where('window_start', '>=', $since->floorMinutes($this->windowMinutes()))
            ->orderByRaw('window_start DESC NULLS LAST')
            ->orderBy('ip_hash')
            ->get();
    }

    /**
     * Drop every bucket that closed before the cutoff.
     *
     * Chunked, on `PrunePublicAudits`' reasoning: a backlog should not be one
     * long transaction on a table the login path also writes to.
     *
     * ⚠️ **`DB::table()` RATHER THAN THE MODEL, DELIBERATELY** — `FailedSignIn`
     * refuses `deleting` outright, which is right for every caller except this
     * one. `PruneTrialOriginClaims` reaches past `TrialClaim`'s identical guard
     * for the identical reason: one named writer, argued at the line. The lint
     * that holds this file as the chokepoint is what keeps the exception to one
     * place.
     */
    public function prune(CarbonImmutable $cutoff, int $chunk): int
    {
        $deleted = 0;

        do {
            $batch = DB::table('failed_sign_ins')
                ->whereIn('id', fn ($query): mixed => $query
                    ->select('id')
                    ->from('failed_sign_ins')
                    ->where('window_start', '<', $cutoff)
                    ->limit($chunk))
                ->delete();

            $deleted += $batch;
        } while ($batch === $chunk);

        return $deleted;
    }

    public function windowMinutes(): int
    {
        return $this->defaults->int('auth.failed_sign_ins.window_minutes');
    }
}
