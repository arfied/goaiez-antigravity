<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ComplianceList;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Models\ComplianceSuppression;
use App\Support\Identifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of `compliance_suppressions` — DNC, reassigned numbers, and
 * known litigators (`29` §2 rule 11, `24` §3.4).
 *
 * ⚠️ **THIS SAID "THE ONLY READER AND THE ONLY WRITER" AND IT HAS NOT BEEN THE
 * ONLY READER SINCE `compliance:load-suppressions` SHIPPED — CORRECTED
 * 2026-08-22** (8190). Two other files read the table and neither touches
 * `value_hash`: that command's *"which registers are still empty"* summary, and
 * `IdentifierHashEpochs`, which asks whether **any** row exists at all so that
 * *no epoch recorded* can be told apart from *nothing has ever been stored*.
 * **The enumeration lives where being wrong fails a test** — `Architecture/
 * ConsentTest`'s *"only the suppression registry reads or writes a compliance
 * suppression"* — and what that lint protects is the hashing rather than the
 * reading: every `value_hash` in this table must have gone through
 * `Identifier::hash()`, and an existence count cannot put one there. A prose
 * count in this docblock could only go stale silently, which is 2505.
 *
 * ⚠️ IT HAS A WRITER AND A CALLER BEFORE IT HAS DATA, AND THAT ORDER IS THE
 * POINT. Five tables in this schema have shipped with an RLS policy, a factory,
 * an isolation test and nothing in `app/` able to create a row —
 * `Business::provision()`, `autopilot_settings` (377), `feedback_pages`,
 * `review_destinations`, `plugins` (399). CLAUDE.md now states the rule the
 * fifth one produced: *check for a writer before depending on any table in this
 * schema*. So `load()` and `remove()` are here, `compliance:load-suppressions`
 * calls them, and `ConsentService` reads them, before a single row exists.
 *
 * ⚠️ THE REGISTERS THEMSELVES ARE NOT FREE AND ARE NOT IN THIS REPOSITORY.
 * Federal DNC access is a subscription through telemarketing.donotcall.gov, the
 * Reassigned Numbers Database is an FCC-designated administrator with per-query
 * pricing, and litigator lists are commercial products. Nothing here fabricates
 * a sample; a seeded fake register would be indistinguishable from a real one
 * at the call site and would make `isLoaded()` answer yes to a question nobody
 * had actually answered.
 *
 * ⚠️ SO THE SHIPPED STATE IS "NO REGISTER LOADED", AND IT FAILS CLOSED ON
 * MARKETING. `isLoaded()` is false until **every** register a marketing send
 * depends on has been imported **on the channel being sent** (1600, tightened
 * again at 1611 — it used to be satisfied by any one of them, on any channel,
 * which let the cheapest list stand in for the federal registry), and
 * `ConsentService` refuses every marketing send while it is. That is free today:
 * `24` §3.3 makes review requests — the only messages this product sends —
 * transactional, so nothing in the product is blocked, and row 4's first
 * marketing campaign meets the constraint at build time rather than a plaintiff
 * meeting it later.
 *
 * ⚠️ HASHES, NEVER IDENTIFIERS. Every method here takes a raw identifier and
 * hashes it through `Identifier::hash()` before touching the database. A caller
 * cannot pass a pre-computed hash, because a caller who computes their own is a
 * caller who skipped normalisation — and a hash of an un-normalised value
 * matches nothing while looking exactly like one that matches.
 *
 * ⚠️ NOTHING HERE WRITES TO `audit_log`, AND THAT IS A FINDING RATHER THAN AN
 * OMISSION. `29` §2 rule 42 wants every sensitive action in the append-only log,
 * and `AuditService::record()` calls `Tenancy::idOrFail()` because `audit_log`
 * is tenant-owned with RLS on `business_id`. A federal register import belongs
 * to no tenant and its only caller is a console command with no tenant resolved,
 * so an audit call here throws in production — which the first version of this
 * class did, and which no test caught because every test wrapped itself in
 * `Tenancy::actingAs()`. Forcing a tenant would file a national Do Not Call load
 * under one arbitrary business, which is worse than not filing it.
 *
 * So the rows are the record, which is the precedent `LegalDocument` set for the
 * other platform-scoped table here ("this row is the audit record of the act"):
 * a load is evidenced by `source_reference` and `created_at`, and a **removal is
 * soft**, carrying `removed_at`, `removed_by` and `removed_reason`. A hard
 * delete would have left the one direction that *unblocks* somebody with no
 * trace at all.
 */
final class SuppressionRegistry
{
    public function __construct(private readonly IdentifierHashEpochs $epochs) {}

    /**
     * The registers a marketing send has to have been scrubbed against.
     *
     * `24` §3.4 names four; `StateDnc` is not here because it is *per state* and
     * a tenant with no customers in a state that runs its own registry has
     * nothing to load — requiring it would fail closed on a jurisdiction that
     * does not apply, which is a different mistake from the one below.
     *
     * @var list<ComplianceList>
     */
    private const array REQUIRED_FOR_MARKETING = [
        ComplianceList::FederalDnc,
        ComplianceList::Litigator,
        ComplianceList::ReassignedNumber,
    ];

    /**
     * Whether every register a marketing send depends on has been loaded.
     *
     * ⚠️ **THIS USED TO ANSWER "HAS *ANY* REGISTER GOT A LIVE ROW", AND THAT WAS
     * THE PERMISSIVE BRANCH** (1600). A litigator list is a commercial product
     * costing a fraction of a federal DNC subscription, so the cheap one is the
     * one an operator loads first — and one row in it flipped this to true,
     * after which every marketing send passed **with the federal Do Not Call
     * registry never consulted**. Nothing anywhere would have said so: the gate
     * reported loaded, the sends went out, and the register that matters was
     * empty. That is 398's shape inside the check that exists to prevent it, and
     * it is why the answer is now per register rather than a single `exists()`.
     *
     * ⚠️ THE QUESTION IS STILL "HAS ANYBODY EVER SCRUBBED", NOT "IS THIS LIST
     * FRESH", and that half stays deferred (see this class's docblock and 1600).
     * Freshness is a real requirement — the federal registry must be re-scrubbed
     * at least every 31 days — but a staleness rule with no import schedule
     * behind it is a number somebody tunes until it stops complaining. Whoever
     * subscribes to a register: add a per-register `loaded_at` and a maximum age,
     * and put the maximum age in `platform_settings` rather than a constant.
     *
     * ✅ **IT IS PER CHANNEL NOW, AND THE EXPOSURE WAS THE REVERSE OF THE ONE
     * THIS PARAGRAPH USED TO DESCRIBE** (1611). The old note said a register
     * loaded for `sms` counted for an email send, and called that the next
     * honest tightening. The live case is worse and runs the other way: an
     * operator who loads the federal Do Not Call extract against `--channel=
     * email` — the wrong flag on a real import, not a contrived one — flipped
     * this gate to true for **SMS**, where `refusalsFor()` filters on
     * `identifier_type` and matched nothing. Every SMS marketing send then
     * passed with the federal registry never consulted, which is the exact
     * failure 1600 was written to remove, surviving 1600 through the axis it did
     * not check. The channel is now part of the question and `ConsentService`
     * passes the send's own.
     */
    public function isLoaded(OutreachChannel $channel): bool
    {
        // ⛔ **THE SECOND CONJUNCT IS 8080 AND IT IS NOT DEFENCE IN DEPTH — IT
        // IS THE ONE THIS METHOD COULD NOT SEE.** `missingForMarketing()` counts
        // rows by list and channel and never touches `value_hash`, so after an
        // `APP_KEY` rotation it goes on answering *"every register is loaded"*
        // over registers that refuse nobody: measured on 2026-08-22,
        // `isLoaded` **true** with `refusalsFor()` returning `[]` for an
        // identifier that had been in the litigator register a moment earlier.
        // **A fail-closed marketing gate passing over inert registers is
        // `sending_health_windows`' shape with a compliance register in it.**
        //
        // ⚠️ **AND IT IS DELIBERATELY NOT FOLDED INTO `missingForMarketing()`.**
        // That method answers *"what does an operator have to import"*, and the
        // answer to this is not *import something* — importing here would write
        // rows under the new key while every row under the old one stayed inert,
        // an operator doing as they were told and making it worse.
        return $this->epochs->isReadable() && $this->missingForMarketing($channel) === [];
    }

    /**
     * Which required registers still hold nothing **for this channel** — what an
     * operator has to load before a marketing send on it can pass.
     *
     * Exists so the refusal can be explained rather than merely returned: the
     * same list, asked once, so a screen or a command cannot grow its own copy
     * of the rule and drift from the gate.
     *
     * ⚠️ **`identifier_type` IS THE FILTER, WHICH IS THE SAME PREDICATE
     * `refusalsFor()` USES.** That is the whole point of the fix: the gate and
     * the lookup now ask about the same rows, so a register that satisfies this
     * is a register that can actually refuse somebody.
     *
     * @return list<ComplianceList>
     */
    public function missingForMarketing(OutreachChannel $channel): array
    {
        // ⚠️ THE RAW COLUMN, NOT THE CAST. `Builder::pluck()` does resolve the
        // enum cast, and relying on that would make this comparison depend on
        // a framework detail rather than on the value in the column — the same
        // reason `refusalsFor()` hashes rather than trusting a caller's digest.
        //
        // ⚠️ **`whereIn`, NOT `where`, SINCE WAVE 39 LANE B.**
        // `$channel->suppressionSharedWith()` is `[$channel]` for every channel
        // but `Voice` — so this is exactly `where('identifier_type', $channel)`
        // for Sms, Email and Whatsapp, unchanged. For `Voice` it also counts a
        // register loaded under `Sms`/`Whatsapp` as loaded, because those are
        // the only channels this platform has ever actually imported an extract
        // for and a Voice-only reading of "loaded" can never become true (see
        // the enum method's own docblock for the compliance argument).
        $loaded = ComplianceSuppression::query()
            ->whereIn('identifier_type', $channel->suppressionSharedWith())
            ->whereNull('removed_at')
            ->distinct()
            ->toBase()
            ->pluck('list')
            ->all();

        return array_values(array_filter(
            self::REQUIRED_FOR_MARKETING,
            static fn (ComplianceList $list): bool => ! in_array($list->value, $loaded, true),
        ));
    }

    /**
     * Which registers refuse this identifier, for this purpose, right now.
     *
     * ⚠️ ONE QUERY ACROSS EVERY REGISTER, then filtered in PHP by purpose and
     * date. The alternative — a query per register — would make the number of
     * round trips grow with the number of registers on a path that runs before
     * every single send.
     *
     * ⚠️ `$consentGivenAt` IS WHAT MAKES A REASSIGNMENT DIFFERENT FROM A BLOCK.
     * A reassignment row says the number changed hands on a date; consent
     * captured before that date belongs to the previous subscriber and consent
     * captured after it does not. Passing null means "no consent record to
     * protect", and every reassignment then applies — which is the safe reading,
     * because a caller with no consent date has nothing to send under anyway.
     *
     * @return list<ComplianceList>
     */
    public function refusalsFor(
        string $identifier,
        OutreachChannel $channel,
        OutreachPurpose $purpose,
        ?CarbonImmutable $consentGivenAt = null,
    ): array {
        // ⚠️ **THE SAME FAILURE AS A NULL HASH, ARRIVED AT FROM THE STORED SIDE
        // RATHER THAN THE FRESH ONE** (8080). Every row in this register was
        // hashed under a key this install no longer has, so none of them can be
        // compared against anything — and an empty refusal list would report
        // that as *scrubbed clean*. `ConsentService::decide()` refuses before it
        // ever reaches here, which is 398's shape, so this arm is driven
        // directly in `SuppressionRegistryTest` rather than through the gate.
        if (! $this->epochs->isReadable()) {
            return ComplianceList::cases();
        }

        $hash = Identifier::hash($identifier, $channel);

        if ($hash === null) {
            // Fails closed, exactly as `ConsentService::hasOptedOut()` does. An
            // identifier that cannot be normalised cannot be compared against a
            // stored hash, so treating it as clean would make an unparseable
            // number permanently un-scrubbable while looking sendable.
            return ComplianceList::cases();
        }

        // ⚠️ **`whereIn` OVER `suppressionSharedWith()`, MATCHING
        // `missingForMarketing()` ABOVE** (wave 39 lane B). The hash is
        // identical across every phone-shaped channel — `Identifier::hash()`
        // routes on shape, never on the exact channel — so this widens which
        // STORED rows may match it without changing what a fresh hash computes.
        $entries = ComplianceSuppression::query()
            ->whereIn('identifier_type', $channel->suppressionSharedWith())
            ->where('value_hash', $hash)
            // A removed row stays for the record and stops refusing. Numbers
            // genuinely come off the DNC registry, and a register copy that only
            // grows eventually blocks people entitled to be contacted.
            ->whereNull('removed_at')
            ->get();

        $refusals = [];

        foreach ($entries as $entry) {
            if (! in_array($purpose, $entry->list->appliesTo(), true)) {
                continue;
            }

            if ($entry->list === ComplianceList::ReassignedNumber
                && ! $this->reassignmentInvalidates($entry, $consentGivenAt)) {
                continue;
            }

            $refusals[] = $entry->list;
        }

        return array_values(array_unique($refusals, SORT_REGULAR));
    }

    /**
     * Whether a reassignment postdates the consent it would invalidate.
     *
     * ⚠️ THE COMPARISON IS `>=`, NOT `>`, AND THE BOUNDARY IS THE WHOLE
     * QUESTION. `effective_from` is a date and a consent record carries a
     * timestamp, so a reassignment recorded on the same day as the consent
     * cannot be ordered against it — and the two orderings give opposite
     * answers about whether we may message a stranger. Same day is treated as
     * "reassigned after", which costs a message to somebody who consented that
     * morning and saves one to somebody who never did.
     */
    private function reassignmentInvalidates(
        ComplianceSuppression $entry,
        ?CarbonImmutable $consentGivenAt,
    ): bool {
        if ($consentGivenAt === null) {
            return true;
        }

        if ($entry->effective_from === null) {
            // The CHECK constraint forbids this, so reaching it means the row
            // arrived by a path that bypassed the schema. Refuse.
            return true;
        }

        return $entry->effective_from->startOfDay()
            ->greaterThanOrEqualTo($consentGivenAt->startOfDay());
    }

    /**
     * Add identifiers to a register.
     *
     * ⚠️ TAKES RAW IDENTIFIERS AND HASHES THEM HERE. An import that arrives
     * pre-hashed by the vendor cannot be loaded through this method, and that is
     * correct rather than a limitation: our hash is keyed with the application
     * key, so a vendor's digest of the same number is a different string and
     * would match nothing forever.
     *
     * Identifiers that do not normalise are skipped and counted rather than
     * throwing. A federal DNC extract is millions of rows and will contain
     * malformed ones; failing the whole import on the first would mean no
     * scrubbing at all, which is the strictly worse outcome.
     *
     * @param  iterable<string>  $identifiers
     * @return array{loaded: int, skipped: int}
     */
    public function load(
        ComplianceList $list,
        OutreachChannel $channel,
        iterable $identifiers,
        string $sourceReference,
        ?string $state = null,
        ?CarbonImmutable $effectiveFrom = null,
    ): array {
        $state = $this->validatedState($list, $state);

        if ($list->requiresEffectiveDate() && $effectiveFrom === null) {
            throw new InvalidArgumentException(
                "The [{$list->value}] register is dated: an entry with no effective date would "
                .'become a permanent block on a number whose current subscriber may have '
                .'consented legitimately, instead of invalidating the consent that predates it.'
            );
        }

        if (! $list->requiresEffectiveDate() && $effectiveFrom !== null) {
            throw new InvalidArgumentException(
                "The [{$list->value}] register carries no effective date. Supplying one implies "
                .'a dated rule that nothing reads, which is a claim the code does not honour.'
            );
        }

        $loaded = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $list, $channel, $identifiers, $sourceReference, $state, $effectiveFrom, &$loaded, &$skipped
        ): void {
            foreach ($identifiers as $identifier) {
                $hash = Identifier::hash($identifier, $channel);

                if ($hash === null) {
                    $skipped++;

                    continue;
                }

                // ⚠️ `updateOrCreate` RATHER THAN `firstOrCreate`, BECAUSE A
                // PREVIOUSLY-REMOVED ROW MUST COME BACK. A number taken off the
                // register and later re-listed is an ordinary event, and
                // `firstOrCreate` would find the soft-removed row, change
                // nothing, and report the identifier as already present while
                // it kept on not refusing anything. That is the silent shape
                // this whole slice is built against.
                $entry = ComplianceSuppression::query()->updateOrCreate(
                    [
                        'list' => $list,
                        'identifier_type' => $channel,
                        'value_hash' => $hash,
                    ],
                    [
                        'state' => $state,
                        'effective_from' => $effectiveFrom?->toDateString(),
                        'source_reference' => $sourceReference,
                        'created_at' => now(),
                        'removed_at' => null,
                        'removed_by' => null,
                        'removed_reason' => null,
                    ],
                );

                if ($entry->wasRecentlyCreated || $entry->wasChanged('removed_at')) {
                    $loaded++;
                }
            }

            // ⛔ **INSIDE THE TRANSACTION, AND THAT IS 8200.** This call sat
            // after the closing brace of this very transaction, so an
            // interruption between the commit and this line left a register
            // full of durable hashes with no live epoch claiming them —
            // `status()` `Unattributed`, `isReadable()` false, and
            // `ConsentService::decide()` refusing **every send on the
            // platform**, transactional included, with no key having changed.
            // The only exit was a person running `consent:hash-epoch --adopt`.
            // Reproduced against this method before it was fixed.
            //
            // ⚠️ **AND IT CLOSES THE OTHER DIRECTION TOO**: a rolled-back import
            // no longer leaves an epoch asserting that this key wrote rows that
            // are not there.
            //
            // ⚠️ **STILL OUTSIDE THE LOOP, WHICH MATTERS ON THIS PATH AND
            // NOWHERE ELSE.** A federal DNC extract is millions of rows;
            // `observe()` is memoised and would be free after the first call
            // anyway, but a per-row call to a method that can write is the kind
            // of line somebody reads as a per-row write.
            //
            // ⚠️ **AND ONLY WHEN SOMETHING WAS ACTUALLY WRITTEN.** An import
            // whose every row was skipped for not normalising has stored no
            // hash, so there is nothing under this key to become unreadable.
            if ($loaded > 0) {
                $this->epochs->observe('compliance-register');
            }
        });

        return ['loaded' => $loaded, 'skipped' => $skipped];
    }

    /**
     * Remove entries from a register.
     *
     * ⚠️ THE ONE PLACE THIS SLICE DIFFERS FROM `OptOut`, WHICH CANNOT BE
     * REMOVED AT ALL. An opt-out is evidence that a person said STOP to us. A
     * register entry is a copy of somebody else's list, numbers genuinely come
     * off the DNC registry, and a copy that can only grow drifts further from
     * its source every month until it blocks people entitled to be contacted.
     *
     * ⚠️ SOFT, AND THE ROW IS THE AUDIT RECORD. `audit_log` cannot hold this —
     * see the class docblock — so a hard delete would leave the direction that
     * *unblocks* somebody with no trace anywhere, indistinguishable from the
     * number never having been listed. `removed_by` and `removed_reason` are
     * required by a CHECK constraint alongside `removed_at`, so a removal is
     * always attributed.
     *
     * @param  iterable<string>  $identifiers
     */
    public function remove(
        ComplianceList $list,
        OutreachChannel $channel,
        iterable $identifiers,
        string $actor,
        string $reason,
    ): int {
        $hashes = [];

        foreach ($identifiers as $identifier) {
            $hash = Identifier::hash($identifier, $channel);

            if ($hash !== null) {
                $hashes[] = $hash;
            }
        }

        if ($hashes === []) {
            return 0;
        }

        return ComplianceSuppression::query()
            ->where('list', $list)
            ->where('identifier_type', $channel)
            ->whereIn('value_hash', $hashes)
            // Already-removed rows are not removed twice: doing so would rewrite
            // the actor and reason of the original removal, which is the one
            // thing this column set exists to preserve.
            ->whereNull('removed_at')
            ->update([
                'removed_at' => now(),
                'removed_by' => $actor,
                'removed_reason' => $reason,
            ]);
    }

    /**
     * Supersede **every** entry in every register, in one attributed act.
     *
     * ⛔ **THE CALLER IS `IdentifierHashEpochs::retire()` AND THERE IS NO OTHER
     * LEGITIMATE ONE** (8186). Accepting the loss of a hash epoch declares every
     * stored digest on the platform permanently unmatchable — and until this
     * existed, the act that declared it also restored `isLoaded()` to **true**
     * over these rows, because that gate counts by list and channel and never
     * touches `value_hash`. **A register that refuses nobody while the gate
     * reports it loaded is the exact shape this whole slice was built against**,
     * and the command warned about it in prose, which is 314–316.
     *
     * ⚠️ **IT SUPERSEDES ROWS WRITTEN UNDER THE CURRENT KEY TOO, AND CANNOT DO
     * OTHERWISE.** 8081 refused a stored key-version column, so nothing in this
     * schema distinguishes a row hashed under the retired key from one hashed
     * after it. The registers are the recoverable half — `source_reference`
     * names the batch or dated extract each row came from — so over-superseding
     * costs a re-import, where under-superseding costs a register that silently
     * refuses nobody.
     *
     * ⚠️ **SOFT, ATTRIBUTED, AND NEVER APPLIED TWICE.** `whereNull('removed_at')`
     * keeps a second run from rewriting the actor and reason of the first, which
     * is `remove()`'s rule and the one thing these columns exist to preserve.
     *
     * @return int how many entries were superseded
     */
    public function supersedeEveryEntry(string $actor, string $reason): int
    {
        return ComplianceSuppression::query()
            ->whereNull('removed_at')
            ->update([
                'removed_at' => now(),
                'removed_by' => $actor,
                'removed_reason' => $reason,
            ]);
    }

    /**
     * Replace a register wholesale — the shape a monthly extract arrives in.
     *
     * ⚠️ WHY THIS IS NOT `remove()` FOLLOWED BY `load()` AT THE CALL SITE. It is
     * one transaction, so the register is never observably empty. A send
     * arriving between the delete and the insert would see `isLoaded()` answer
     * false — and on the marketing path that is a refusal, which is safe — but
     * would also see zero refusals on the transactional path, which is not. The
     * window is small and it is exactly the window a monthly cron opens.
     *
     * ⚠️ **THE STATE IS VALIDATED HERE, AND IT USED TO BE NORMALISED TWO
     * DIFFERENT WAYS IN ONE METHOD** (2038). The supersede below scoped itself
     * with `strtoupper($state)` while the `load()` it hands the same argument to
     * normalises with `validatedState()` — `strtoupper(trim(…))`. The two agree
     * on every input except a padded one, and `--state=' ca '` from the console
     * is exactly that: the UPDATE scopes to `' CA '`, matches no row, supersedes
     * nothing, and then `load()` inserts under `'CA'`. **The monthly refresh
     * silently stops being a replace and becomes an append** — every number that
     * dropped off the state's register keeps refusing forever, and the operator
     * is told `removed: 0`, which is what a month with no delistings also looks
     * like. Calling `validatedState()` once and passing its output to both is
     * what makes a second normal form unspellable here.
     *
     * ⚠️ **AND IT MOVES THE REFUSAL IN FRONT OF THE WRITE — WHICH NO TEST CAN
     * SEE, SO THE CLAIM IS MADE HERE AND NOT ASSERTED THERE** (2039). Validation
     * used to happen inside `load()`, which runs *after* the supersede; a bad
     * state threw with every row in the register already marked removed, and
     * only the transaction rollback undid it. Restoring that ordering was
     * mutated and **survived the whole file**, because the rollback makes the
     * two indistinguishable from outside. It is a legibility argument rather
     * than a behavioural one, and it is written down as one (314–316).
     *
     * @param  iterable<string>  $identifiers
     * @return array{loaded: int, skipped: int, removed: int}
     */
    public function replace(
        ComplianceList $list,
        OutreachChannel $channel,
        iterable $identifiers,
        string $actor,
        string $sourceReference,
        ?string $state = null,
        ?CarbonImmutable $effectiveFrom = null,
    ): array {
        $state = $this->validatedState($list, $state);

        return DB::transaction(function () use (
            $list, $channel, $identifiers, $actor, $sourceReference, $state, $effectiveFrom
        ): array {
            $reason = 'superseded by '.$sourceReference;

            ComplianceSuppression::query()
                ->where('list', $list)
                ->where('identifier_type', $channel)
                ->when($state !== null, fn (Builder $query) => $query->where('state', $state))
                ->whereNull('removed_at')
                ->update([
                    'removed_at' => now(),
                    'removed_by' => $actor,
                    'removed_reason' => $reason,
                ]);

            // Anything still in the new extract is reactivated by `load()`'s
            // `updateOrCreate`, so the net effect is a diff rather than a churn
            // of the whole register — and the rows that really did drop off keep
            // their removal attributed to this batch.
            $result = $this->load(
                $list, $channel, $identifiers, $sourceReference, $state, $effectiveFrom
            );

            // ⚠️ COUNTED AFTER THE LOAD, NOT BEFORE IT. The UPDATE above touches
            // every row in the register, most of which the new extract restores
            // a moment later — reporting that number as "removed" would tell an
            // operator their monthly refresh had dropped the entire list.
            $removed = ComplianceSuppression::query()
                ->where('list', $list)
                ->where('identifier_type', $channel)
                ->where('removed_reason', $reason)
                ->whereNotNull('removed_at')
                ->count();

            return [...$result, 'removed' => $removed];
        });
    }

    /**
     * A state code the register will accept, or a refusal the caller can act on.
     *
     * The database CHECK enforces the same rule and the enum states it; this is
     * the layer that produces a message rather than a SQLSTATE (314–316).
     */
    private function validatedState(ComplianceList $list, ?string $state): ?string
    {
        if ($list->requiresState() && $state === null) {
            throw new InvalidArgumentException(
                "The [{$list->value}] register is per state, so an entry without one cannot be "
                .'attributed to a jurisdiction and would be applied in every state.'
            );
        }

        if (! $list->requiresState() && $state !== null) {
            throw new InvalidArgumentException(
                "The [{$list->value}] register is national. Naming a state implies a scope the "
                .'read path does not apply, which is a claim the code does not honour.'
            );
        }

        if ($state === null) {
            return null;
        }

        $state = strtoupper(trim($state));

        if (preg_match('/^[A-Z]{2}$/', $state) !== 1) {
            throw new InvalidArgumentException(
                'A state is a two-letter USPS code. Anything else is a row no lookup will match.'
            );
        }

        return $state;
    }
}
