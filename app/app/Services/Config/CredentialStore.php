<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Enums\CredentialChangeAction;
use App\Enums\CredentialEnvironment;
use App\Enums\PlatformHealthSignal;
use App\Models\CredentialChange;
use App\Models\PlatformCredential;
use App\Services\Ops\PlatformHealth;
use App\Support\CredentialManifest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * THE CREDENTIALS MANAGER — doc `38` Part 1 (D-149), the runtime half.
 *
 * `38` Part 1: "environment variables are **bootstrap seeds only**. At runtime,
 * every vendor credential resolves from the encrypted `platform_credentials`
 * store." This is the only class that reads or writes that store, and an
 * `ArchitectureTest` lint holds it — a sharper chokepoint than the usual one,
 * because every read of the value column decrypts a live vendor secret into
 * memory.
 *
 * Callers do not use this class. They use `App\Support\PlatformCredentials`,
 * which is the seam every existing call site already went through and which
 * delegates here. That seam is why this slice changes no call site at all.
 *
 * ## The resolution order, and why the fallback stays
 *
 * Store first, then `config('credentials.*')`, which is `.env`. D-149 calls env
 * vars "bootstrap seeds", and the seed has to keep working for two situations
 * that are not edge cases: a fresh install, where nobody has opened Ops yet and
 * the marketing home already needs a Places key; and an `APP_KEY` rotation,
 * which makes every ciphertext in the table unreadable at once. Deleting the
 * fallback would turn both into an outage with no way back in.
 *
 * ## The memo is per process, and never the cache store
 *
 * `38` Part 1 asks for "a cached accessor with instant bust on rotate", and
 * `DefaultsRegistry` points here when it explains why *it* is uncached
 * (decision 510). The cache is a private array on this singleton, deliberately
 * not `Cache::`:
 *
 * ⚠️ **PRODUCTION RUNS `CACHE_STORE=database`** (decision 415 — Redis is not
 * installed on that box). Putting a decrypted vendor secret through
 * `Cache::remember()` would write every one of these keys, in plaintext, into a
 * `cache` table that has no encryption, no rotation and no audit — undoing the
 * entire reason the column above is encrypted, and doing it invisibly, because
 * every test and every screen would still behave perfectly.
 *
 * A per-process memo gives the hot-path saving that matters (one query per key
 * per request, not one per vendor call) and cannot outlive the request, which
 * makes "instant bust on rotate" structurally true rather than a promise. This
 * is decision 519's distinction — a memo scoped to one object versus a shared
 * warm cache — applied where the stakes are higher.
 */
final class CredentialStore
{
    /**
     * Resolved values for this process, keyed by credential key.
     *
     * `null` is a cached answer too: "asked, and there is nothing anywhere". A
     * missing key would otherwise re-query on every call from the one path that
     * hits this hardest, which is a job loop calling a vendor that is not
     * configured.
     *
     * @var array<string, ?string>
     */
    private array $memo = [];

    /**
     * Keys whose `last_used_at` has already been touched in this process.
     *
     * @var array<string, true>
     */
    private array $touched = [];

    /**
     * `"{signal}|{key}"` for every fault already counted in this hour, in this
     * process (9320–9327).
     *
     * ⛔ **THE MEMO ABOVE CANNOT DO THIS JOB AND USING IT WOULD HAVE MADE THE
     * COUNTER LIE IN THE ONE PROCESS THAT MATTERS MOST.** `$memo` caches the
     * answer for the life of the object, so a resolution recorded from inside it
     * would be recorded **once per process** — which is once per request on the
     * web, and once per *worker lifetime* on the queue. A `queue:work` process
     * lives for hours: it would count the first refused credential read of the
     * morning and nothing after it, so the sweep's window would be empty every
     * time it looked, on the very path that hammers an unconfigured vendor
     * hardest. This is keyed by hour instead, which bounds the writes to one per
     * key per hour per process **and** keeps every window that contains a fault
     * carrying a row.
     *
     * @var array<string, true>
     */
    private array $counted = [];

    /**
     * The fault the last resolution of each key produced, where it produced one
     * (9320–9327).
     *
     * ⚠️ **IT RIDES BESIDE `$memo` BECAUSE THE MEMO IS AN ANSWER AND THIS IS A
     * DIAGNOSIS.** A key that resolved to null is re-counted on every subsequent
     * call — that is what keeps a long-lived queue worker writing a row into
     * every hourly window rather than only into the one it started in — and the
     * count has to name the same fault the first read found. Reading the fault
     * back off the memo is not possible: *absent* and *stored-but-unreadable*
     * both answer null, and they send an operator to two different places.
     *
     * @var array<string, PlatformHealthSignal>
     */
    private array $fault = [];

    /**
     * The value of a credential, or null when neither the store nor `.env` has
     * one.
     *
     * ## ⛔ The ciphertext is read inside a `try`, and it was not until 2026-08-25
     *
     * ⛔ **THIS CLASS'S OWN DOCBLOCK NAMES AN `APP_KEY` ROTATION AS ONE OF THE
     * TWO REASONS THE `.env` FALLBACK EXISTS — *"which makes every ciphertext in
     * the table unreadable at once. Deleting the fallback would turn both into
     * an outage with no way back in"* — AND THE FALLBACK COULD NOT BE REACHED IN
     * THAT STATE** (9321). `$stored->value` is Laravel's `encrypted` cast, so an
     * unreadable payload **throws** `DecryptException` at the comparison two
     * lines below, before any `config()` read. Measured rather than reasoned: a
     * row whose ciphertext was replaced answered `DecryptException: The payload
     * is invalid.` from `resolve()`, from `sourceOf()` **and** from
     * `PlatformCredentials::has()`, whose own docblock promises it does not
     * throw — in backticks rather than a `{@see}`, because Pint turns a docblock
     * reference into a `use` statement and this class is the one thing that
     * class delegates *to*. **The paragraph describing the rescue is what
     * stopped anybody checking whether the rescue worked** — 314–316, in the
     * class that resolves every vendor credential this platform has.
     *
     * ⚠️ **FALLING BACK TO THE SEED IS THE BEHAVIOUR THIS CLASS ALREADY
     * DOCUMENTED, AND IT IS NOT FREE.** The value the platform then runs on is
     * whatever `.env` holds, which may be a key an operator believes they
     * rotated away in Ops. **That is why the fall-through is counted rather than
     * silent**: {@see PlatformHealthSignal::CredentialUnreadable} is recorded
     * whether or not a seed rescues the read, so the state reaches the operator
     * alert board instead of being a quiet success. The alternative — throwing —
     * is the outage with no way back in that the docblock above refuses.
     *
     * @throws InvalidArgumentException when the manifest does not declare $key
     */
    public function resolve(string $key): ?string
    {
        $this->assertDeclared($key);

        if (array_key_exists($key, $this->memo)) {
            // ⚠️ **THE FAULT IS RE-COUNTED AND THE VALUE IS NOT RE-READ.** The
            // memo is what makes a job loop cheap; the counter is what makes the
            // fault visible. See {@see self::$counted} for why one memo cannot
            // serve both.
            $this->countFault($key);

            return $this->memo[$key];
        }

        $stored = PlatformCredential::query()->find($key);
        Log::info("CredentialStore resolve($key) found: ".json_encode($stored));

        if ($stored !== null) {
            $value = $this->readable($stored);

            if ($value !== null) {
                $this->touch($stored);

                return $this->memo[$key] = $value;
            }

            // ⛔ **A ROW THAT EXISTS AND PRODUCED NOTHING IS NOT "ABSENT", AND
            // COUNTING IT AS ABSENT WOULD SEND THE OPERATOR TO THE WRONG
            // SCREEN.** `set()` refuses a blank value, so the only way a stored
            // row yields nothing is that this install can no longer decrypt it —
            // the remedy is an `APP_KEY`, not a paste. ⚠️ **A row blanked by a
            // hand-written `UPDATE` is folded in here and reported the same
            // way**, which is the one inaccuracy in this classification and is
            // preferred to a silent third state.
            $this->fault[$key] = PlatformHealthSignal::CredentialUnreadable;
        }

        $seed = config("credentials.{$key}");
        $resolved = is_string($seed) && $seed !== '' ? $seed : null;

        if ($resolved === null && ! isset($this->fault[$key])) {
            $this->fault[$key] = PlatformHealthSignal::CredentialAbsent;
        }

        // ⚠️ **THE UNREADABLE FAULT IS COUNTED EVEN WHERE THE SEED RESCUED THE
        // READ.** The platform then runs on whatever `.env` holds — which may be
        // a key an operator believes they rotated away in Ops — and nothing else
        // in this application would ever say so.
        $this->countFault($key);

        return $this->memo[$key] = $resolved;
    }

    /**
     * The stored value, or null when the ciphertext cannot be decrypted.
     *
     * ⚠️ **THE `catch` IS `Throwable` AND NOT `DecryptException`, ON THIS
     * FEATURE'S OWN R25 RULE.** The observed exception is a `DecryptException`
     * and that is what the test plants; what may not happen here is that a
     * credential read on a customer-facing path dies of an exception class
     * nobody predicted, in a method whose entire purpose is to degrade. The
     * counter records the fault either way, so nothing is swallowed silently.
     *
     * ⚠️ **AN EMPTY STRING AND AN UNREADABLE PAYLOAD ARE BOTH `null` HERE, AND
     * ONLY ONE OF THEM IS COUNTED.** `set()` refuses a blank value, so a stored
     * empty string is not a state this application can write; folding it in
     * keeps the caller's guard to one comparison instead of two that must agree.
     *
     * ⛔ **IT COUNTS NOTHING, AND THAT IS WHY {@see self::sourceOf()} MAY CALL
     * IT.** The classification lives in {@see self::resolve()}, which is the only
     * method here that *spends* a credential; a reporting method that wrote the
     * counter would be a screen that rings its own bell by being looked at.
     */
    private function readable(PlatformCredential $stored): ?string
    {
        try {
            // ⚠️ **`getAttribute()` RATHER THAN `->value`, AND THE ANALYSER IS
            // THE REASON RATHER THAN A STYLE CHOICE.** Larastan models the magic
            // property as a plain `string` with no throw at all, so `composer
            // stan` calls the `catch` below **dead code** — over the one line in
            // this class that genuinely throws in production. The two spellings
            // reach the identical cast; this one is a method call, which the
            // analyser will admit can fail. ⛔ **A reader who "tidies" this back
            // to `->value` removes the guard and the build says nothing**, which
            // is why the sentence is here rather than in the docblock.
            $value = $stored->getAttribute('value');
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Count the fault this key's last resolution produced, at most once per key
     * per hour per process — and nothing at all where there was no fault.
     *
     * ⛔ **IT MAY NOT THROW AND IT MAY NOT SLOW ANYTHING DOWN — R25, "a bell,
     * never a brake".** {@see PlatformHealth::recordFailure()} already swallows
     * its own failures, and this method is reached from a marketing page render,
     * from a payment webhook and from inside a queued job. The `PlatformHealth`
     * instance is built here rather than injected because this class is a
     * container singleton resolved during bootstrap on some paths, and a
     * constructor dependency would drag the counter into that resolution.
     */
    private function countFault(string $key): void
    {
        $signal = $this->fault[$key] ?? null;

        if ($signal === null) {
            return;
        }

        $slot = $signal->value.'|'.$key.'|'.CarbonImmutable::now()->utc()->format('Y-m-d H');

        if (isset($this->counted[$slot])) {
            return;
        }

        $this->counted[$slot] = true;

        (new PlatformHealth)->recordFailure($signal, $key);
    }

    /**
     * Where a credential's value is coming from right now.
     *
     * `38` Part 1 requires the board to show "exactly which key is absent", and
     * three states answer that honestly where two would not: a key sitting in
     * `.env` on a server where the operator believes they rotated it in Ops is
     * neither present-in-the-way-they-think nor absent, and showing it as a
     * plain green light is how a rotation silently does nothing.
     *
     * ⛔ **THIS IS A THIN WRAPPER OVER {@see self::state()} SINCE 2026-08-25,
     * AND IT WAS A SECOND COPY OF THAT RULE BEFORE THAT** (9440). It still has
     * **no caller anywhere in `app/`** — 9326(a), unchanged — and that is
     * precisely why the two copies were free to drift: this one gained 9321's
     * decrypt guard and {@see self::board()}, the copy a person actually reads,
     * did not. **A rule with two copies goes wrong at the copy nobody exercises,
     * and the exercised copy is the one on the screen.**
     */
    public function sourceOf(string $key): CredentialSource
    {
        $this->assertDeclared($key);

        return $this->state(PlatformCredential::query()->find($key), $key)['source'];
    }

    /**
     * Whether this install can produce a value for a key — **without spending
     * it, and without counting anything** (11640–11651).
     *
     * ## ⛔ Why this is not `PlatformCredentials::has()`
     *
     * ⛔ **`has()` GOES THROUGH {@see self::resolve()}, WHICH WRITES A HEALTH
     * COUNTER.** That is correct there: `resolve()` is the method that *spends*
     * a credential, and {@see PlatformHealthSignal::CredentialAbsent}'s own
     * docblock says the observation is *"something needed it"* rather than *"it
     * is unset"*. ⛔ **So an at-rest census asking `has()` for every webhook
     * secret on every deployment would ring the credential bell by looking** —
     * the exact thing {@see self::board()} refuses in writing, and it would ring
     * it with a count that is the census's own reads rather than any real
     * traffic. **A screen that rings its own bell by being looked at is not a
     * bell**, and neither is a deploy step.
     *
     * ⚠️ **SO THIS IS THE SAME CLASSIFIER AND NOT A SECOND COPY OF IT.** It
     * answers off {@see self::state()} — the one `match` 9440 collapsed two
     * copies into — so a stored row this install cannot decrypt is *present*
     * only when a `.env` seed is genuinely carrying the platform, which is what
     * `resolve()` would return.
     *
     * ⛔ **AND IT IS NOT A CLAIM THAT THE VALUE IS RIGHT.** A wrong secret and a
     * right one are indistinguishable from here for ever; the only thing that
     * tells them apart is a genuine delivery.
     *
     * @throws InvalidArgumentException when the manifest does not declare $key
     */
    public function holds(string $key): bool
    {
        return $this->sourceOf($key) !== CredentialSource::Absent;
    }

    /**
     * What this board can say about one declared key, without saying its value.
     *
     * ⛔ **THE ONE COPY OF THIS RULE, AND THERE WERE TWO UNTIL 2026-08-25**
     * (9440). {@see self::board()} carried its own `match (true)` whose first
     * arm was `$row !== null => CredentialSource::Store` — no decrypt guard —
     * so on a ciphertext this install can no longer read, the board **a person
     * opens during the incident** reported *"Managed here"*, withheld the
     * degradation sentence, and offered Rotate and Clear. `CLAUDE.md`'s 8460:
     * two copies of one rule that agree today are one wave from disagreeing,
     * and **the guarded copy was the dead one** — {@see self::sourceOf()} has
     * no caller in `app/` and never had, so the drift could only ever appear on
     * the copy nobody exercised.
     *
     * ⛔ **AN UNREADABLE ROW IS NOT A FOURTH *SOURCE*, AND 9326(a)'s REFUSAL OF
     * A FOURTH ENUM CASE SURVIVES THIS INTACT** (9441). *Source* is where the
     * value in use comes from, and on an unreadable row the value in use comes
     * from the seed or from nowhere — which is exactly what the three cases
     * already say, and saying `Store` there was the defect rather than a
     * missing word. Unreadability is a second, **orthogonal** fact about the
     * stored row: `unreadable` and `source` answer different questions and the
     * screen renders both. ⚠️ **Folding them into one enum would have made the
     * two sub-states unsayable** — *unreadable, and a `.env` seed is quietly
     * carrying the platform* is a different sentence from *unreadable, and
     * nothing is answering at all*, and only the second one is degraded.
     *
     * ⛔ **ONE DECRYPT ATTEMPT PER ROW, WHICH IS WHY THIS RETURNS BOTH ANSWERS
     * RATHER THAN BEING TWO PREDICATES.** Two helpers reading the same row
     * would open the same ciphertext twice per render for no gain.
     *
     * ⛔ **AND IT COUNTS NOTHING.** {@see self::readable()} is the guard 9321
     * built and it writes no counter, which is what lets a *report* call it: a
     * screen that rings its own bell by being looked at is not a bell. The
     * decrypted string never leaves this method.
     *
     * @return array{source: CredentialSource, unreadable: bool}
     */
    private function state(?PlatformCredential $row, string $key): array
    {
        if ($row !== null && $this->readable($row) !== null) {
            return ['source' => CredentialSource::Store, 'unreadable' => false];
        }

        $seed = config("credentials.{$key}");

        return [
            'source' => is_string($seed) && $seed !== ''
                ? CredentialSource::EnvironmentSeed
                : CredentialSource::Absent,
            // A row that exists and produced nothing. `set()` refuses a blank
            // value, so the only way a stored row yields nothing is that this
            // install can no longer decrypt it — the same classification
            // `resolve()` makes, and the same one inaccuracy: a row blanked by
            // a hand-written `UPDATE` is folded in here.
            'unreadable' => $row !== null,
        ];
    }

    /**
     * Set or rotate a credential.
     *
     * `38` Part 1: "**Set/Rotate** (paste, never displayed again)". The value
     * goes in encrypted, four characters of it are kept for the mask, and the
     * act is logged. Nothing anywhere reads it back out to a screen.
     *
     * ⚠️ THE TRANSACTION IS LOAD-BEARING, and it is here on the first writing
     * rather than after a review found it missing (decision 518, which is the
     * same shape one slice earlier). A failing log insert that left the value
     * rotated would produce the one outcome this log exists to prevent: a secret
     * that changed with no record of when or by whom, invisible, because the
     * board would look perfectly correct.
     *
     * @throws InvalidArgumentException when the manifest does not declare $key,
     *                                  or when $value is blank
     */
    public function set(
        string $key,
        string $value,
        string $actor,
        CredentialEnvironment $environment,
    ): PlatformCredential {
        $this->assertDeclared($key);

        // Trimmed because a pasted key routinely carries a trailing newline, and
        // a credential that differs from the vendor's by one invisible character
        // fails as an authentication error that reads like a wrong key.
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                "A blank value is not a way to remove credential [{$key}]. Clearing it is a "
                .'deliberate act with its own log entry — use clear().'
            );
        }

        return DB::transaction(function () use ($key, $value, $actor, $environment): PlatformCredential {
            $existing = PlatformCredential::query()->find($key);

            $lastFour = self::lastFour($value);

            $credential = PlatformCredential::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'last_four' => $lastFour,
                    'environment' => $environment,
                    'rotated_at' => Carbon::now(),
                    'rotated_by' => $actor,
                ],
            );

            CredentialChange::create([
                'credential_key' => $key,
                'action' => $existing === null
                    ? CredentialChangeAction::Set
                    : CredentialChangeAction::Rotated,
                'last_four_before' => $existing?->last_four,
                'last_four_after' => $lastFour,
                'actor' => $actor,
                'created_at' => Carbon::now(),
            ]);

            $this->forget($key);

            return $credential;
        });
    }

    /**
     * Correct which account a stored credential opens, without touching the
     * value.
     *
     * Its own act with its own log entry rather than a field on set(), because
     * the alternative is asking an operator to paste a live secret again in
     * order to fix a badge — and a rotation performed to fix a label is a
     * rotation nobody records as one.
     *
     * @throws InvalidArgumentException when $key has no stored row
     */
    public function setEnvironment(string $key, CredentialEnvironment $environment, string $actor): PlatformCredential
    {
        $this->assertDeclared($key);

        return DB::transaction(function () use ($key, $environment, $actor): PlatformCredential {
            $credential = PlatformCredential::query()->find($key);

            if ($credential === null) {
                throw new InvalidArgumentException(
                    "Credential [{$key}] has no stored row, so there is no environment to "
                    .'correct. A value seeded from .env carries no badge, which is itself '
                    .'a reason to set it here.'
                );
            }

            if ($credential->environment !== $environment) {
                $credential->environment = $environment;
                $credential->save();

                CredentialChange::create([
                    'credential_key' => $key,
                    'action' => CredentialChangeAction::EnvironmentChanged,
                    // Unchanged on both sides, and written rather than left null
                    // so the row still says which key this was about at a glance.
                    'last_four_before' => $credential->last_four,
                    'last_four_after' => $credential->last_four,
                    'actor' => $actor,
                    'created_at' => Carbon::now(),
                ]);
            }

            $this->forget($key);

            return $credential;
        });
    }

    /**
     * Remove a stored credential, returning the key to whatever `.env` holds.
     *
     * ⚠️ THIS IS NOT NECESSARILY A REMOVAL. If `.env` still carries a seed, the
     * key keeps working from it — which is the correct behaviour under D-149 and
     * is also the surprising one, so `sourceOf()` reports it and the board says
     * so. An operator clearing a compromised key needs to know that the old one
     * may still be in the environment file.
     */
    public function clear(string $key, string $actor): void
    {
        $this->assertDeclared($key);

        DB::transaction(function () use ($key, $actor): void {
            $existing = PlatformCredential::query()->find($key);

            if ($existing === null) {
                return;
            }

            $existing->delete();

            CredentialChange::create([
                'credential_key' => $key,
                'action' => CredentialChangeAction::Cleared,
                'last_four_before' => $existing->last_four,
                'last_four_after' => null,
                'actor' => $actor,
                'created_at' => Carbon::now(),
            ]);

            $this->forget($key);
        });
    }

    /**
     * Every declared credential's board row, grouped by vendor.
     *
     * ⚠️ NOTHING IN THE RETURNED STRUCTURE IS THE VALUE, OR CAN BE USED TO
     * DERIVE IT. The screen renders this and only this.
     *
     * ⛔ **AND THIS METHOD DOES NOW OPEN CIPHERTEXT, WHICH IT DID NOT UNTIL
     * 2026-08-25 — THE TRADE IS WRITTEN DOWN RATHER THAN ASSUMED** (9442). The
     * old sentence was *"the encrypted column is never opened by anything this
     * screen touches"*, and it bought a real property: an Ops page render
     * decrypted nothing. **What it cost was the incident.** Because the source
     * was decided by `$row !== null` alone, a stored row this install can no
     * longer read reported `Store` — *"Managed here"*, with the degradation
     * sentence withheld and a Rotate button offered — on the one screen an
     * operator opens during an `APP_KEY` rotation. `CredentialFaultBellTest`
     * named that in a comment a wave before anybody fixed it.
     *
     * ⚠️ **WHAT THE DECRYPT COSTS, STATED HONESTLY**: one render now decrypts
     * every stored credential this platform holds, once each, into a temporary
     * {@see self::state()} discards without returning it. It is the exposure `resolve()` already creates one key at a time on
     * the request path, taken all at once on a screen behind
     * `AdminAccess::GATE`. **It buys the only thing this board is for** — `38`
     * Part 1's *"shows exactly which key is absent — never a stack trace, never
     * a silent stall"* — being true in the state where it matters.
     *
     * ⛔ **IT STILL COUNTS NOTHING** (9321's rule, and the reason `readable()`
     * exists apart from `resolve()`): opening this screen may not ring the
     * credential bell, or looking becomes an incident.
     *
     * @return array<string, list<array{key: string, label: string, description: string, degradation: string, source: CredentialSource, unreadable: bool, lastFour: ?string, environment: ?CredentialEnvironment, rotatedAt: ?Carbon, rotatedBy: ?string, lastUsedAt: ?Carbon}>>
     */
    public function board(): array
    {
        // One query for the whole board rather than one per key — decision 519's
        // N+1, avoided on the way in rather than found later.
        $stored = PlatformCredential::query()->get()->keyBy('key');

        $board = [];

        foreach (CredentialManifest::grouped() as $vendor => $declared) {
            foreach ($declared as $entry) {
                /** @var ?PlatformCredential $row */
                $row = $stored->get($entry['key']);

                // ⛔ **THE SHARED CLASSIFIER, NOT A SECOND COPY OF IT.** A
                // `match (true)` over `$row !== null` stood here until
                // 2026-08-25 and that is what 9440 is about. Both answers come
                // out of one call, so one row is opened once.
                $state = $this->state($row, $entry['key']);

                $board[$vendor][] = [
                    'key' => $entry['key'],
                    'label' => $entry['label'],
                    'description' => $entry['description'],
                    'degradation' => $entry['degradation'],
                    'source' => $state['source'],
                    'unreadable' => $state['unreadable'],
                    'lastFour' => $row?->last_four,
                    'environment' => $row?->environment,
                    'rotatedAt' => $row?->rotated_at,
                    'rotatedBy' => $row?->rotated_by,
                    'lastUsedAt' => $row?->last_used_at,
                ];
            }
        }

        return $board;
    }

    public const int HISTORY_LIMIT = 20;

    /**
     * The change history for one credential, newest first.
     *
     * Ordered by `id`, the only descending sort this codebase permits without
     * saying NULLS LAST — and the correct one anyway, because two rotations in
     * the same second have an order that `created_at` cannot express.
     *
     * @return list<CredentialChange>
     */
    public function historyFor(string $key, ?int $limit = null): array
    {
        $limit ??= app(DefaultsRegistry::class)->int('credentials.history_limit');

        return array_values(
            CredentialChange::query()
                ->where('credential_key', $key)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->all()
        );
    }

    /**
     * Drop the memo for one key.
     *
     * Public because a test that rotates a credential through a second instance
     * of this service needs it, and because `38`'s "instant bust on rotate" is
     * worth being able to state explicitly.
     */
    public function forget(string $key): void
    {
        unset($this->memo[$key], $this->touched[$key], $this->fault[$key]);
    }

    /**
     * The mask `38` Part 1 asks for: "masked value (last-4 visible)".
     *
     * A value shorter than eight characters yields no tail at all rather than
     * most of itself — four of six characters is not a mask. No real vendor key
     * is that short, so this is about pasted rubbish rather than about a vendor.
     */
    public static function lastFour(string $value): ?string
    {
        return mb_strlen($value) >= 8 ? mb_substr($value, -4) : null;
    }

    /**
     * Record that a stored credential was used today.
     *
     * At most one UPDATE per key per process, and only when the stored date is
     * not today's. `38` Part 1 asks for "last-used"; a timestamp accurate to the
     * second would mean a write on every vendor call, on the hot path, for
     * information whose only question — "is this key still in use, or can it be
     * retired" — is answered by a date.
     *
     * ⚠️ A READ INSIDE A TRANSACTION THAT LATER ROLLS BACK LOSES ITS TOUCH.
     * Accepted: this column is telemetry, and the alternative (a separate
     * connection to escape the caller's transaction) would put a write outside
     * the caller's control on a path whose whole job is to hand back a string.
     */
    private function touch(PlatformCredential $credential): void
    {
        if (isset($this->touched[$credential->key])) {
            return;
        }

        $this->touched[$credential->key] = true;

        $now = Carbon::now();

        if ($credential->last_used_at !== null && $credential->last_used_at->isSameDay($now)) {
            return;
        }

        // A bare UPDATE rather than saving the model: `$credential->value` is
        // decrypted in memory here, and a `save()` would re-encrypt and rewrite
        // the secret to satisfy a telemetry column. Laravel's encryption is
        // randomised, so that rewrite would also change the ciphertext daily for
        // no reason, which makes a diff of this table unreadable as evidence.
        PlatformCredential::query()
            ->whereKey($credential->key)
            ->update(['last_used_at' => $now]);

        $credential->last_used_at = $now;
    }

    /**
     * @throws InvalidArgumentException when the manifest does not declare $key
     */
    private function assertDeclared(string $key): void
    {
        if (CredentialManifest::declares($key)) {
            return;
        }

        throw new InvalidArgumentException(
            "`{$key}` is not declared in CredentialManifest. The manager answers only for "
            .'keys the manifest names — an undeclared one is a typo far more often than it '
            .'is a new vendor, and resolving it quietly to null produces a 401 from the '
            .'vendor that gets blamed on the vendor.'
        );
    }
}
