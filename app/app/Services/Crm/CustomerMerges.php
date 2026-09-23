<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\OutreachChannel;
use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Two contacts folded into one, reversibly (`34` §1.2, and `34` §7's
 * build-failing *"merge is undoable 30 days"*).
 *
 * ⚠️ **THE ONLY READER AND WRITER OF `customer_merges`, AND THE FIRST WRITER
 * `customers.merged_into_id` HAS EVER HAD.** The column has existed since Stage
 * 0 with zero readers and zero writers anywhere in `app/` — decision 272's
 * shape, which this codebase has now recorded fifteen times at table level and
 * four at column level (`display_on_website` 403, `users.role` 740,
 * `gating_ack_at` 520–528, `triage_threshold` 1423). Two lints in
 * `tests/Feature/Architecture/CrmTest.php` hold both halves here.
 *
 * ## Undo is the requirement; merge is the side effect
 *
 * Decision 1326: a merge that cannot be reversed is a data-loss feature wearing
 * a tidy-up label. Everything below is arranged so the reversal is total — the
 * change set carries both rows' prior values, and `undo()` refuses rather than
 * restoring half of them.
 *
 * ## What may be picked, and the narrowing that is a finding rather than a cut
 *
 * §1.2 asks for a *"side-by-side field picker"*. The owner picks **`name` and
 * `tags`**, and deliberately not email or phone. `consent_records` carries **no
 * identifier column** — a record means *"this customer row agreed on this
 * channel"* and `ConsentService::decide()` reads the identifier live off the
 * row — so moving an identifier onto a row that already holds a consent record
 * would grant a permit to an address nobody proved themselves reachable on.
 * That is decisions 334–337's finding exactly, and a merge would be the first
 * thing in this application to produce it: nothing else in `app/` changes a
 * customer's email or phone after creation except `FeedbackSubmission`'s
 * null-backfill, which guards itself the same way.
 *
 * So the survivor **fills a gap and never makes a swap**: it takes the merged
 * contact's email or phone only where it has neither that identifier nor a
 * consent record on that channel. There is nothing for the owner to choose,
 * which is why it is not on the picker.
 *
 * ⚠️ **`region_code` JOINS THAT RULE RATHER THAN THE PICKER** (1599). A
 * jurisdiction is a fact about where somebody lives, not a preference, and
 * offering a side would let a merge move a named person into another state's
 * quiet hours by clicking the wrong radio. The survivor keeps what it holds and
 * takes the merged contact's only into a gap — and without this the merge would
 * *drop* a jurisdiction, because the folded-away row leaves every list while the
 * survivor goes back to `StateUnknown`.
 *
 * ## Why the merged-away row is emptied of its identifiers
 *
 * Always, whether the survivor took them or not, and recorded in the change set.
 * `customers` carries `UNIQUE(business_id, email)` and `UNIQUE(business_id,
 * phone)`, so a hidden row goes on holding a live slot the owner can no longer
 * see; and `FeedbackSubmission::findByIdentifier()` matches on the raw column
 * with **no exclusion for archived or merged rows**, so the next submission
 * carrying that address would land on the row nobody can open — where its review
 * would still reach the survivor's timeline (this class's `mergedInto()`) while
 * its invite would be refused as `MergedAway`.
 *
 * ## Chains are refused, not walked
 *
 * Both sides of a merge come from a picker that excludes merged-away contacts,
 * and `merge()` refuses either side that is already merged away. So a chain
 * cannot arise, one level of union is complete, and **the refusal is what keeps
 * that true** — building a transitive walk for a state the service forbids would
 * be code for a case that cannot occur.
 */
final class CustomerMerges
{
    /**
     * ⚠️ **A CONSTANT, NOT A REGISTRY SEED, AND THAT IS DELIBERATE.** `34` §7
     * puts *"merge is undoable 30 days"* on its build-failing list, and a figure
     * an operator can move in Ops is a build-failing test an operator can
     * defeat — silently, because every screen would go on looking correct.
     * Decision 1505's rule pointing the same way: a key with no reader that
     * needs it is the mirror of 272's shape, and this one has a reader that must
     * not be allowed to read anything else.
     */
    public const int UNDO_WINDOW_DAYS = 30;

    /** The fields §1.2's picker offers a side for — see the class docblock. */
    public const array CHOOSABLE_FIELDS = ['name', 'tags'];

    private const string SURVIVOR = 'survivor';

    private const string MERGED = 'merged';

    /**
     * ⚠️ **THE NAME AND TAG WRITES GO THROUGH `CustomerEditor`, AND THAT IS
     * DECISION 624'S RULE RATHER THAN A CONVENIENCE.** `customers.tags` sits
     * behind a chokepoint lint naming exactly one file (1484), and the tidy
     * implementation here — `$survivor->tags = $merged->tags` — reddens it. The
     * two available moves are widening that allowlist for one feature, which is
     * *"reasonable on its own diff and does not show up as a security change"*,
     * or asking whether this caller belongs behind the service. It does: a merge
     * taking the other contact's tags is the owner changing this contact's tags,
     * which is what `retag()` is. **Nothing was widened.**
     */
    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly AuditService $audit,
        private readonly ConsentService $consent,
        private readonly CustomerEditor $editor,
    ) {}

    /**
     * Fold one contact into another.
     *
     * @param  array<string, string>  $choices  field => 'survivor'|'merged', for
     *                                          `CHOOSABLE_FIELDS`. A field left
     *                                          out keeps the survivor's value,
     *                                          which is the safe default: an
     *                                          omitted key must never silently
     *                                          overwrite.
     *
     * @throws InvalidArgumentException when the pair or the choices are refused
     */
    public function merge(Customer $survivor, Customer $merged, array $choices, User $actor): CustomerMerge
    {
        $this->assertBelongsToTenant($survivor);
        $this->assertBelongsToTenant($merged);

        if ($survivor->getKey() === $merged->getKey()) {
            throw new InvalidArgumentException(
                'A contact cannot be merged into itself. The undo would restore that '
                .'contact from its own cleared identifiers, which is not a smaller merge.',
            );
        }

        // ⚠️ BOTH DIRECTIONS, NOT ONE. Refusing only the merged side would allow
        // a chain — A into B, then B into C — which is the state `mergedInto()`
        // is documented as not walking.
        foreach ([$survivor, $merged] as $side) {
            if ($side->merged_into_id !== null) {
                throw new InvalidArgumentException(
                    'That contact has already been merged into another one. Undo that '
                    .'merge first; merging a merged contact would make a chain, and the '
                    .'history of the one in the middle would belong to two people.',
                );
            }
        }

        // ⚠️ **NEITHER SIDE MAY BE ARCHIVED, AND THE REFUSAL IS IN BOTH
        // DIRECTIONS FOR TWO DIFFERENT REASONS.** Folding a live contact into an
        // archived one takes it off the list without the owner ever archiving
        // it; folding an archived one into a live contact silently un-hides
        // history the owner deliberately put away (their notes and reviews join
        // the survivor's timeline through `mergedInto()`). The detector already
        // refuses to propose either, and this is 391's rule that a control which
        // is not rendered must not be honoured through a side door — reachable
        // here, because the candidate id arrives from the browser.
        foreach ([$survivor, $merged] as $side) {
            if ($side->archived_at !== null) {
                throw new InvalidArgumentException(
                    'One of those customers is archived. Restore them first — merging '
                    .'would either hide a customer you never archived or bring back '
                    .'history you put away.',
                );
            }
        }

        // ⚠️ **AND NEITHER MAY BE DELETED** (1545), for the archive reasons plus
        // one this file's own design makes worse: a merge clears the folded-away
        // row's identifiers (1523), and a deleted contact's identifiers are what
        // `FeedbackSubmission` matches to resurrect them when they come back.
        // Folding a tombstone into a live contact would strip that, so the
        // person's next submission would create a second row and the tombstone
        // — and the seven-day undo attached to it — would never be reachable
        // again.
        foreach ([$survivor, $merged] as $side) {
            if ($side->deleted_at !== null) {
                throw new InvalidArgumentException(
                    'One of those customers is deleted. Bring them back first — merging '
                    .'would either hide a customer you never deleted or quietly revive '
                    .'one you did.',
                );
            }
        }

        // ⚠️ **THE PAIR MUST ACTUALLY BE A DUPLICATE, AND THE CHECK IS HERE
        // RATHER THAN ON THE SCREEN.** §1.2 words merge as the last step of one
        // chain — *auto-detected (phone/email match) → banner → picker → merge*
        // — so a merge of two contacts nothing detected is not a smaller
        // feature, it is folding two real people together on the strength of a
        // customer id typed into a Livewire call. That is decision 391's *"a
        // button that is not rendered but is still honoured"* with thirty days
        // of undo and then permanence behind it, and 398's rule says the
        // refusal belongs in the service so it is falsifiable without the
        // screen.
        //
        // ⚠️ `44` §8's manual duplicate queue is the caller that will want this
        // relaxed. Relaxing it is a deliberate change with its own decision, not
        // a parameter somebody adds while wiring a screen.
        if (! MergeDuplicateDetector::isDuplicate($survivor, $merged)) {
            throw new InvalidArgumentException(
                'Those two customers do not share a phone number or an email address, so '
                .'nothing says they are the same person. Merging is only offered for '
                .'contacts we detected as duplicates.',
            );
        }

        $choices = $this->cleanChoices($choices);

        return DB::transaction(function () use ($survivor, $merged, $choices, $actor): CustomerMerge {
            $changes = [
                'survivor_before' => [
                    'name' => $survivor->name,
                    'tags' => $survivor->tags,
                    'email' => $survivor->email,
                    'phone' => $survivor->phone,
                    'region_code' => $survivor->region_code,
                ],
                'merged_before' => [
                    'email' => $merged->email,
                    'phone' => $merged->phone,
                    'region_code' => $merged->region_code,
                ],
                'chose' => $choices,
            ];

            if (($choices['name'] ?? self::SURVIVOR) === self::MERGED) {
                $this->editor->rename($survivor, $merged->name);
            }

            if (($choices['tags'] ?? self::SURVIVOR) === self::MERGED) {
                $this->editor->retag($survivor, $merged->tags ?? []);
            }

            // ⚠️ **THE STATE FILLS A GAP AND IS NOT ON THE PICKER** (1599), for
            // the reason email and phone are not: it is not the owner's to
            // choose between. A jurisdiction is a fact about where somebody
            // lives, and offering a side would let a merge move a named person
            // into another state's quiet hours by clicking the wrong radio. So
            // the survivor keeps whatever it holds, and takes the merged
            // contact's only where it holds nothing — which is strictly more
            // knowledge than either row had alone.
            //
            // Through the editor, because that is where the write lives and
            // where the audit entry comes from. `merge()` writing it directly
            // would redden the chokepoint lint, and 624's rule says ask whether
            // this caller belongs behind the service rather than widen the
            // allowlist. It does.
            if ($survivor->region_code === null && $merged->region_code !== null) {
                $this->editor->setRegion($survivor, $merged->region_code, $actor);
            }

            // The gap fill, never a swap — see the class docblock. Resolved
            // BEFORE the merged row is emptied, or there is nothing left to take.
            $agreed = $this->channelsWithConsentRecord($survivor);

            $takesEmail = $survivor->email === null
                && $merged->email !== null
                && ! in_array(OutreachChannel::Email, $agreed, true);

            $takesPhone = $survivor->phone === null
                && $merged->phone !== null
                && ! $this->anyPhoneChannel($agreed);

            // ⚠️ THE MERGED ROW IS EMPTIED FIRST AND THE SURVIVOR WRITTEN
            // SECOND. `UNIQUE(business_id, email)` is checked per statement, so
            // writing the survivor first would collide with the row still
            // holding that address. `undo()` reverses the order for the same
            // reason, which is why neither is left to whichever save happens to
            // run first.
            $merged->email = null;
            $merged->phone = null;
            $merged->merged_into_id = $survivor->getKey();
            $merged->save();

            if ($takesEmail) {
                $survivor->email = $changes['merged_before']['email'];
            }

            if ($takesPhone) {
                $survivor->phone = $changes['merged_before']['phone'];
            }

            $survivor->save();

            $record = CustomerMerge::query()->create([
                'survivor_id' => $survivor->getKey(),
                'merged_id' => $merged->getKey(),
                'merged_by' => $actor->getKey(),
                'changes' => $changes,
                'merged_at' => now(),
            ]);

            // ⚠️ ITS OWN ACTION, NEVER `consent.withdrawn` AND NEVER ARCHIVE'S.
            // A merge is a claim about identity — that these two rows are one
            // person — and filing it as a suppression would put a statement we
            // authored about the customer into the table whose job is to be true
            // (1225, 1501). The metadata carries the two typed ids and no
            // personal data: `audit_log` is read by more people than `customers`
            // is, and the values are one lookup away through the entity
            // reference (805's pattern).
            $this->audit->record('crm.customers_merged', 'user:'.$actor->getKey(), $record, [
                'survivor_id' => $survivor->getKey(),
                'merged_id' => $merged->getKey(),
                'chose' => $choices,
                'identifiers_taken' => array_values(array_filter([
                    $takesEmail ? 'email' : null,
                    $takesPhone ? 'phone' : null,
                ])),
                'took_region' => $changes['survivor_before']['region_code'] === null
                    && $changes['merged_before']['region_code'] !== null,
            ]);

            return $record;
        });
    }

    /**
     * Put both contacts back exactly as they were.
     *
     * ⚠️ **IT RESTORES EVERYTHING OR IT REFUSES.** A partial undo is the failure
     * this whole table exists to prevent: the owner would be told the merge was
     * reversed while one of the two rows kept somebody else's name or lost its
     * phone number for good. So the one state that cannot be fully reversed —
     * another contact has taken an identifier this undo must give back — is a
     * refusal with a sentence naming it, not a best effort.
     *
     * @throws RuntimeException when the window has closed or a restore would collide
     */
    public function undo(CustomerMerge $merge, User $actor): CustomerMerge
    {
        Tenancy::idOrFail();

        $this->assertMergeBelongsToTenant($merge);

        if (! $merge->isLive()) {
            throw new RuntimeException('That merge has already been undone.');
        }

        if (! $this->isWithinWindow($merge)) {
            throw new RuntimeException(
                'This merge is more than '.$this->undoWindowDays().' days old, so it can no '
                .'longer be undone. The two customers stay as one.',
            );
        }

        /** @var Customer $survivor */
        $survivor = Customer::query()->findOrFail($merge->survivor_id);
        /** @var Customer $merged */
        $merged = Customer::query()->findOrFail($merge->merged_id);

        $before = $this->snapshotFrom($merge, 'survivor_before');
        $mergedBefore = $this->snapshotFrom($merge, 'merged_before');

        // ⚠️ ASKED BEFORE ANYTHING IS WRITTEN, so the refusal leaves both rows
        // untouched. Checked against the whole tenant rather than against these
        // two rows, because the collision that matters is a THIRD contact
        // created since the merge — the identifier was freed, and a feedback
        // submission or an import is free to have taken it.
        foreach (['email', 'phone'] as $column) {
            $value = $mergedBefore[$column] ?? null;

            if ($value === null) {
                continue;
            }

            $taken = Customer::query()
                ->where($column, $value)
                ->whereNotIn('id', [$survivor->getKey(), $merged->getKey()])
                ->exists();

            if ($taken) {
                throw new RuntimeException(
                    'Another customer now uses that '.$column.', so undoing this merge '
                    .'would give two customers the same one. Change theirs first, then '
                    .'undo this.',
                );
            }
        }

        return DB::transaction(function () use ($merge, $survivor, $merged, $before, $mergedBefore, $actor): CustomerMerge {
            // ⚠️ THE SURVIVOR IS WRITTEN FIRST HERE AND SECOND IN `merge()`, and
            // the asymmetry is the unique index rather than an oversight: the
            // survivor may be holding an identifier that is about to go back to
            // the merged row, so it has to let go before the other one takes it.
            $survivor->email = $before['email'] ?? null;
            $survivor->phone = $before['phone'] ?? null;
            $survivor->save();

            // Through the editor for the reason the constructor states — the
            // tags chokepoint is not widened for an undo either.
            $name = $before['name'] ?? null;
            $this->editor->rename($survivor, is_string($name) ? $name : null);

            $tags = $before['tags'] ?? null;
            $this->editor->retag($survivor, is_array($tags) ? array_values(array_map('strval', $tags)) : []);

            // ⚠️ **ONLY WHEN THE SNAPSHOT ACTUALLY RECORDED ONE, AND ONLY WHEN
            // IT DIFFERS** (1599). Two reasons, and neither applies to the two
            // lines above: a merge written before the state joined the change
            // set has no key here, and restoring "null" from its absence would
            // erase a jurisdiction this undo never took; and `setRegion()`
            // writes an audit entry where `rename()` and `retag()` do not, so an
            // unconditional call would file a compliance record for a change
            // that did not happen.
            $region = $before['region_code'] ?? null;
            $region = is_string($region) ? $region : null;

            if (array_key_exists('region_code', $before) && $survivor->region_code !== $region) {
                $this->editor->setRegion($survivor, $region, $actor);
            }

            $merged->email = $mergedBefore['email'] ?? null;
            $merged->phone = $mergedBefore['phone'] ?? null;
            $merged->merged_into_id = null;
            $merged->save();

            $merge->undone_at = now();
            $merge->undone_by = $actor->getKey();
            $merge->save();

            $this->audit->record('crm.merge_undone', 'user:'.$actor->getKey(), $merge, [
                'survivor_id' => $survivor->getKey(),
                'merged_id' => $merged->getKey(),
            ]);

            return $merge;
        });
    }

    /**
     * One merge row, or a 404 — `CustomerDirectory::find()`'s boundary shape:
     * the global scope answers a foreign id exactly as it answers a missing one,
     * and RLS sits beneath.
     */
    public function find(int $mergeId): CustomerMerge
    {
        Tenancy::idOrFail();

        return CustomerMerge::query()->findOrFail($mergeId);
    }

    /**
     * The still-undoable merges this contact is the survivor of, newest first.
     *
     * ⚠️ **THE WINDOW IS APPLIED IN SQL AND RE-ASKED IN `undo()`.** Two
     * expressions of one rule, the way `CrmTasks::dueNow()` and `isDueNow()`
     * are — the screen needs a query and the service needs a guard on a single
     * row it was handed. A feature test drives both across day 29 and day 31 and
     * asserts they agree, which is what stops the button rendering on a merge
     * the service will refuse.
     *
     * @return Collection<int, CustomerMerge>
     */
    public function undoableFor(Customer $survivor): Collection
    {
        Tenancy::idOrFail();

        return CustomerMerge::query()
            ->where('survivor_id', $survivor->getKey())
            ->whereNull('undone_at')
            ->where('merged_at', '>', $this->windowOpensAt())
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The contacts folded into this one — what the timeline unions across.
     *
     * ⚠️ **NOT LIMITED TO THE UNDO WINDOW.** A merge older than thirty days is
     * permanent, and permanent is precisely when its history has to keep
     * arriving on the survivor's profile. The window governs the reversal and
     * nothing else.
     *
     * @return Collection<int, Customer>
     */
    public function mergedInto(Customer $survivor): Collection
    {
        Tenancy::idOrFail();

        return Customer::query()
            ->where('merged_into_id', $survivor->getKey())
            ->orderBy('id')
            ->get();
    }

    /**
     * The live merge that folded this contact away, if it is one.
     *
     * What the merged-away contact's own profile reads to say where its history
     * went. ⚠️ A bookmarked URL for a merged contact resolves rather than 404s
     * (`CustomerDirectory::find()` is unchanged), because a 404 on a contact
     * somebody merged reads as data loss rather than as tidying.
     */
    public function mergeAwayOf(Customer $customer): ?CustomerMerge
    {
        Tenancy::idOrFail();

        if ($customer->merged_into_id === null) {
            return null;
        }

        return CustomerMerge::query()
            ->where('merged_id', $customer->getKey())
            ->whereNull('undone_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Whether a merge can still be reversed.
     *
     * Public because the screen asks it of a row it already holds, and because
     * `undo()` asking the same method is what makes the button and the refusal
     * one rule rather than two.
     */
    public function isWithinWindow(CustomerMerge $merge): bool
    {
        return $merge->merged_at->isAfter($this->windowOpensAt());
    }

    /**
     * The moment a merge stops being reversible.
     *
     * ⚠️ Written once and used by both expressions of the rule. `>` rather than
     * `>=` on both sides: a merge made exactly thirty days ago to the second is
     * outside the window, which is the conservative reading of "undoable for 30
     * days" and the only one that cannot drift between the query and the guard.
     */
    private function windowOpensAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->undoWindowDays());
    }

    /**
     * Which channels the survivor already holds a consent record on.
     *
     * ⚠️ **ASKED THROUGH `ConsentService`, NEVER BY QUERYING `consent_records`.**
     * That table sits behind a chokepoint lint, and `badgesFor()`'s `agreed`
     * list is already exactly this question — decision 624's rule twice over
     * (1223): ask whether the caller belongs behind the service before widening
     * an allowlist. Nothing was widened.
     *
     * ⚠️ It is **not** a permission and must never be read as one. `ConsentBadge`
     * says so in as many words: "agreed" means a record exists, and the gate is
     * `permit()`. What it is used for here is narrower and safer — refusing to
     * move an identifier onto a row whose consent record would then imply it.
     *
     * @return list<OutreachChannel>
     */
    private function channelsWithConsentRecord(Customer $customer): array
    {
        $badge = $this->consent->badgesFor(collect([$customer]))[$customer->getKey()] ?? null;

        return $badge === null ? [] : $badge->agreed;
    }

    /**
     * @param  list<OutreachChannel>  $channels
     */
    private function anyPhoneChannel(array $channels): bool
    {
        foreach ($channels as $channel) {
            if ($channel->usesPhoneIdentifier()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $choices
     * @return array<string, string>
     */
    private function cleanChoices(array $choices): array
    {
        $clean = [];

        foreach ($choices as $field => $side) {
            if (! in_array($field, self::CHOOSABLE_FIELDS, true)) {
                throw new InvalidArgumentException(
                    'A merge cannot pick a side for "'.$field.'". Only '
                    .implode(' and ', self::CHOOSABLE_FIELDS).' are the owner\'s to choose; '
                    .'contact details are never moved onto a row that holds a consent record.',
                );
            }

            if (! in_array($side, [self::SURVIVOR, self::MERGED], true)) {
                throw new InvalidArgumentException(
                    'A merge picks either the customer you keep or the one you fold in, '
                    .'and "'.$side.'" is neither.',
                );
            }

            $clean[$field] = $side;
        }

        return $clean;
    }

    /**
     * One half of the change set, typed.
     *
     * @return array<string, mixed>
     */
    private function snapshotFrom(CustomerMerge $merge, string $key): array
    {
        $snapshot = $merge->changes[$key] ?? null;

        if (! is_array($snapshot)) {
            // Unreachable through this service, which writes both halves in one
            // insert — and stated rather than assumed, because the undo reads
            // this and nothing else does, so a silently empty array here would
            // blank a contact instead of restoring it.
            throw new RuntimeException(
                'This merge has no record of what "'.$key.'" held, so it cannot be undone '
                .'without blanking a contact. Nothing has been changed.',
            );
        }

        /** @var array<string, mixed> $snapshot */
        return $snapshot;
    }

    /**
     * The wrong-tenant refusal — the case RLS cannot catch once a model is in
     * hand, which `CustomerEditor` and `ConsentService::record()` both refuse
     * for the same reason. Driven directly in the tests, per 398's rule, because
     * the screen resolves through `CustomerDirectory::find()` and would refuse
     * first.
     */
    private function assertBelongsToTenant(Customer $customer): void
    {
        if ($customer->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That customer belongs to another tenant. A merge rewrites both contacts, so '
            .'this would fold one business\'s customer into another\'s.',
        );
    }

    private function assertMergeBelongsToTenant(CustomerMerge $merge): void
    {
        if ($merge->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That merge belongs to another tenant.',
        );
    }

    public function undoWindowDays(): int
    {
        return $this->defaults->int('crm.merge.undo_window_days');
    }
}
