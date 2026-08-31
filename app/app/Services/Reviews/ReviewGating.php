<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\ReviewDestination;
use App\Enums\SupportWriteSubject;
use App\Http\Middleware\Impersonating;
use App\Models\AutopilotSettings;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\Destinations\DestinationSettings;
use App\Services\Impersonation\Impersonation;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Who a location asks for a public review — COMP-02's threshold screen's
 * service, and the only writer of `autopilot_settings.triage_threshold`.
 *
 * ⚠️ THIS CLASS WROTE `gating_ack_at` UNTIL 2026-08-12, AND THE COLUMN IS GONE
 * (decisions 2074, 2660). The acknowledgement was `29` §12.1's second
 * build-failing subject: no invite threshold applied without a logged ack, the
 * fallback being to gate nobody. The owner removed it on 2026-08-11, having been
 * shown what it cost — it was the single stored record that a business chose
 * star-thresholded invitation knowingly, and it is the artefact a platform or a
 * regulator would have asked to see. **The build-failing test was replaced
 * rather than deleted** (2075, 1161's route): its subject is now that every
 * rating is captured and kept, and none is ever deleted, suppressed or hidden.
 *
 * ⚠️ THE TWO ANSWERS ARE STILL NOT SYMMETRICAL, AND THE ASYMMETRY IS SMALLER
 * THAN IT WAS. "Ask everyone" writes a threshold of 0 to every gateable
 * destination and leaves the triage boundary alone; "only ask happy customers"
 * writes the threshold and moves triage to one below it. Neither writes an
 * acknowledgement, because there is no longer one to write, and `chosenThreshold()`
 * now reads the stored thresholds alone.
 *
 * ⚠️ THE THRESHOLD IS THE TENANT'S TO SET, AND THIS CLASS USED TO ARGUE THE
 * OPPOSITE. Decision 523 refused a number picker, citing CLAUDE.md's "never add
 * a tenant-facing toggle — opinionated defaults only", and 311 had removed the
 * last tenant-writable threshold before it. **The owner overruled both on
 * 2026-08-07** — *"based on client what they want"* — and 1143 records the
 * override rather than absorbing it quietly, because a standing instruction that
 * disappears without a note is one the next reader re-derives. 1186 fixes the
 * range at 1–5 and the platform default at 4.
 *
 * ⚠️ ONE NUMBER WRITES TWO COLUMNS, AND THAT IS THE OFF-BY-ONE THIS CLASS NOW
 * OWNS. `review_destinations.invite_threshold` and
 * `autopilot_settings.triage_threshold` compare in opposite directions
 * (ReviewRouter's own docblock), so "invite at 4+" and "triage at 3−" are two
 * writes, not one. 1186 says so in as many words: a control that moves one and
 * leaves the other is how a tenant ends up inviting and triaging the same
 * rating. They move together here, in one transaction, or neither moves.
 *
 * ⚠️ AND THAT PAIR WAS ALREADY WRONG ON `main`. The seeded pair was invite 5 /
 * triage 3, so a **4★ customer was neither invited nor triaged** — no
 * invitation, no recovery conversation, nothing. It is the gap the owner's
 * 4-and-above ruling closes, and it is worth stating because nothing failed
 * while it was true.
 *
 * ⚠️ 1 IS THE "ASK EVERYONE" ANSWER RATHER THAN A STORED THRESHOLD OF 1, which
 * is 1187's warning answered rather than argued with. That decision notes the
 * range spans "no gating at all" to "the strictest gating there is" with no
 * signal either end differs in kind — and the ends genuinely do. A stored 1
 * invites every rating while `isGating()` reports the location as filtering,
 * which is the exact misreport that method exists to prevent. So the screens
 * offer five options, the first routes to `inviteEveryone()`, and `gateAt()`
 * refuses anything below 2 by name. ⚠️ The 1–5 range and the default of 4 are
 * the owner's, re-confirmed at 2076 in the same breath as removing the
 * acknowledgement; nothing here moves them.
 *
 * ⚠️ TRUSTPILOT IS SKIPPED BY THE GATE RATHER THAN EXEMPTED FROM IT. Its terms
 * require every customer be invited, so `forcedThreshold()` pins it at 0 and
 * `DestinationSettings::setThreshold()` throws on anything else. Gating a
 * location must not fail because one destination cannot be gated — the owner
 * would see an error naming a platform they may not even use — so this filters
 * on `forcedThreshold() === null` and the screen says why in words.
 */
final class ReviewGating
{
    /**
     * The answer that invites every customer, whatever they rated.
     *
     * ⚠️ IT IS A RATING THE SCREENS SHOW AND NOT A THRESHOLD ANYTHING STORES.
     * 1186 gives the tenant the range 1–5 and 1187 explains why its bottom end
     * is different in kind; this constant is where the two meet. Passing it to
     * `gateAt()` is refused — `inviteEveryone()` is its implementation, and it
     * stores a 0, which is what `chosenThreshold()` reads back as this answer.
     */
    public const int EVERYONE = 1;

    /**
     * The lowest rating a *gated* answer may be set to.
     *
     * 2, because 1 is `EVERYONE` above. Not a range check for its own sake: a
     * stored threshold of 1 invites everybody while `isGating()` reports the
     * location as filtering, which is the misreport that method exists to
     * prevent.
     */
    public const int MIN_GATED_THRESHOLD = 2;

    /** The strictest gate there is — decision 110's original ruling. */
    public const int MAX_THRESHOLD = 5;

    public function __construct(
        private readonly AuditService $audit,
        private readonly DestinationSettings $destinations,
        private readonly Impersonation $impersonation,
    ) {}

    /**
     * The ratings an owner may pick from, strictest last.
     *
     * @return list<int>
     */
    public static function choices(): array
    {
        return range(self::EVERYONE, self::MAX_THRESHOLD);
    }

    /**
     * Invite every customer, whatever they rated.
     *
     * ⚠️ THE THRESHOLD OF 0 IS THE WHOLE RECORD OF THIS ANSWER NOW. Until
     * decision 2074 this branch was also defined by what it did *not* write —
     * `gating_ack_at` stayed null, and `ReviewRouter` read that null as
     * permission to apply no threshold anywhere. The column is gone, so the
     * stored 0 is the only artefact, and `chosenThreshold()` reads it back as
     * `EVERYONE`. That is why `DestinationSettings::defaultThresholdFor()` still
     * refuses to seed a 0: a seeded 0 would be indistinguishable from an owner
     * having answered this question.
     */
    public function inviteEveryone(Location $location, string $actor): void
    {
        $this->assertBelongsToTenant($location);

        DB::transaction(function () use ($location, $actor): void {
            foreach ($this->gateable($location) as $destination) {
                $this->destinations->setThreshold($location, $destination, 0, $actor);
            }

            // ⚠️ `triage_threshold` IS DELIBERATELY UNTOUCHED ON THIS
            // BRANCH, which is the one place the single-number model does not
            // reach. Following it here would mean writing 0 — "triage nobody" —
            // and decision 383 refused that value in a CHECK on the grounds
            // that the recovery path is not the owner's to remove. Choosing to
            // invite everybody says nothing about who needs recovery, so the
            // location keeps whatever triage boundary it had. The consequence,
            // stated because it looks like an inconsistency: a customer may be
            // both invited and triaged, which decision 375 already settled —
            // triage wins `status`, and the invite survives in
            // `routed_destinations`.
            // ⚠️ `answer` IS THE OWNER'S ONE NUMBER, AND IT IS NEW HERE BECAUSE
            // THE ACKNOWLEDGEMENT USED TO BE THE RECORD OF HAVING CHOSEN (2661).
            // `DestinationSettings::setThreshold()` audits each destination's
            // before/after separately and always did; what no row carried once
            // `gating_ack_at` went was *that a person answered this question at
            // all*, as opposed to a threshold moving for some other reason.
            $this->writeGatingRow(
                $location,
                null,
                $actor,
                'review_gating.everyone_invited',
                ['answer' => self::EVERYONE],
            );
        });
    }

    /**
     * Gate invitations at a rating the owner chose.
     *
     * @param  int  $threshold  The rating at or above which a customer is
     *                          invited. 2–5; `EVERYONE` is refused, because it
     *                          is `inviteEveryone()` and stores a 0.
     * @param  string  $disclosureVersion  The wording the owner actually saw.
     *                                     Stored on the audit row, never
     *                                     inferred later — the same discipline
     *                                     consent records use, and for the same
     *                                     reason: a disclosure that has since
     *                                     been reworded cannot be reconstructed
     *                                     from a timestamp.
     */
    public function gateAt(
        Location $location,
        int $threshold,
        string $actor,
        string $disclosureVersion,
    ): void {
        $this->assertBelongsToTenant($location);

        if ($threshold === self::EVERYONE) {
            throw new InvalidArgumentException(
                'Inviting every customer is not a threshold of 1, it is the other answer. '
                .'Call inviteEveryone(), which stores a 0 — a threshold of 1 would invite '
                .'everybody while isGating() reported this location as filtering, which is '
                .'the misreport that method exists to prevent (decision 1187).',
            );
        }

        if ($threshold < self::MIN_GATED_THRESHOLD || $threshold > self::MAX_THRESHOLD) {
            throw new InvalidArgumentException(
                'A gated threshold is a star rating from '.self::MIN_GATED_THRESHOLD.' to '
                .self::MAX_THRESHOLD.', and '.$threshold.' is outside it. Decision 1186 '
                .'fixes the owner-facing range at 1–5; its bottom end is inviteEveryone().',
            );
        }

        if (trim($disclosureVersion) === '') {
            throw new InvalidArgumentException(
                'A gating change records which disclosure the owner had on screen when they '
                .'chose. Without the version there is no evidence of what they were told, '
                .'which is the only thing the audit row can answer later.'
            );
        }

        DB::transaction(function () use ($location, $threshold, $actor, $disclosureVersion): void {
            // ⚠️ THE TRIAGE BOUNDARY AND THE INVITE THRESHOLDS RIDE ONE
            // TRANSACTION, and they did before the acknowledgement was removed
            // for a stricter reason than the one that survives. `29` §12.1 used
            // to forbid a threshold existing without an ack, so the window
            // between the two writes was itself the forbidden state. That window
            // is gone with the column (2074) — what remains is 1186's pairing,
            // which is reason enough on its own: a failure between the two
            // writes leaves a location inviting at 4 and triaging at whatever it
            // triaged at before, which is how a rating reaches neither path.
            //
            // ⚠️ TRIAGE IS THE THRESHOLD MINUS ONE, and that subtraction is the
            // whole of 1186's warning. `invited iff rating >= invite_threshold`
            // and `triaged iff rating <= triage_threshold` run in opposite
            // directions, so the two numbers that mean "4 and up are invited, 3
            // and down are triaged" are 4 and 3. Writing the same number into
            // both would invite and triage every 4★ and leave nobody in
            // between; leaving triage alone would strand every rating between
            // the old boundary and the new one.
            $this->writeGatingRow(
                $location,
                $threshold - 1,
                $actor,
                'review_gating.threshold_set',
                ['answer' => $threshold, 'disclosure_version' => $disclosureVersion],
            );

            foreach ($this->gateable($location) as $destination) {
                $this->destinations->setThreshold($location, $destination, $threshold, $actor);
            }
        });
    }

    /**
     * The rating this location currently invites at, or null when there is no
     * gateable destination to read one from.
     *
     * ⚠️ IT NOW REPORTS WHAT IS IN FORCE, NOT WHAT SOMEBODY ANSWERED, AND THAT
     * IS THE HONEST CHANGE RATHER THAN A LOSS. There used to be a third state:
     * a location whose owner had never been asked returned **null** even though
     * it had a seeded threshold of 4, because `gating_ack_at` was the record of
     * having answered and `ReviewRouter` applied nothing without it. So null
     * meant "nobody is being filtered" and the screens honestly pre-selected
     * nothing.
     *
     * With decision 2074 the seeded 4 applies from the moment a tenant is
     * provisioned. A screen that showed no selection would now be telling an
     * owner that no rule is in force while their 3★ customers are not being
     * invited — which is a worse failure than the one 520's
     * "pre-selecting-lets-them-tap-Continue" rule was guarding against, and it
     * would be invisible. So the radio reflects the live threshold, and the
     * disclosure above it is what makes the question unskippable.
     *
     * `EVERYONE` is still recognisable because `inviteEveryone()` is the only
     * thing that writes a 0 and `DestinationSettings::defaultThresholdFor()`
     * refuses to seed one.
     */
    public function chosenThreshold(Location $location): ?int
    {
        $thresholds = array_map(
            fn (ReviewDestination $destination): ?int => $this->destinations
                ->thresholdFor($location, $destination),
            $this->gateable($location),
        );

        $thresholds = array_values(array_filter($thresholds, is_int(...)));

        if ($thresholds === []) {
            return null;
        }

        return max($thresholds) === 0 ? self::EVERYONE : max($thresholds);
    }

    /**
     * The rating a location is gated at out of the box, for the screens to
     * pre-select once an owner has chosen to gate at all.
     *
     * Reads the registry through the destinations service, so the platform
     * default lives in exactly one place and an operator moving it in Ops moves
     * what both screens suggest (1142). Google is asked because every gateable
     * destination is written the same number by `gateAt()`.
     */
    public function defaultThreshold(): int
    {
        return $this->destinations->defaultThresholdFor(ReviewDestination::Google);
    }

    /**
     * Whether thresholds are currently being applied at this location.
     *
     * COMP-02's "persistent settings-screen indicator while gating is active".
     *
     * ⚠️ ONE HALF NOW, AND DROPPING THE OTHER IS WHAT MAKES THIS TRUE RATHER
     * THAN CONVENIENT. It used to require an acknowledgement *and* a threshold
     * above 0, because either alone misreported: an ack with every threshold at
     * 0 gated nobody, and a threshold above 0 with no ack was ignored by
     * `ReviewRouter`. The second of those is no longer a state that exists
     * (2074) — a threshold above 0 is applied, full stop — so requiring an ack
     * that no row can carry would report **every** location as not filtering,
     * which is the exact misreport in the opposite direction.
     *
     * ⚠️ `offeredFor()` RATHER THAN EVERY ROW, so a location with a threshold on
     * a destination it has disabled is not reported as filtering. It asks
     * nobody anywhere, so it filters nobody.
     */
    public function isGating(Location $location): bool
    {
        return $this->destinations->offeredFor($location)
            ->contains(fn ($setting): bool => $setting->invite_threshold > 0);
    }

    /**
     * The destinations whose threshold this wizard step may actually set.
     *
     * TWO CONDITIONS, AND THE SECOND WAS ADDED BY THE YELP REVERSAL (1160).
     *
     *   forcedThreshold() === null      the platform lets us choose at all.
     *                                   Trustpilot's 0 is its condition of use.
     *
     *   isSeededAtProvisioning()        the row exists for this location. Yelp's
     *                                   does not, and must not be created here.
     *
     * ⚠️ **WITHOUT THE SECOND, THIS METHOD IS A THIRD BIRTH PATH FOR A YELP ROW,
     * AND IT IS THE ONE NOBODY NAMED.** `setThreshold()` upserts through
     * `rowFor()`, so writing a Yelp threshold *creates* a Yelp row with a null
     * link — for every tenant who completes the setup wizard, whether or not
     * they have ever heard of Yelp. Decision 1161 forbids Yelp arriving "by
     * seed, default, or provisioning" and a wizard default is all three.
     * `ReviewDestination::isSeededAtProvisioning()` and
     * `DestinationSettings::seedDefaults()` both missed it; **the database CHECK
     * is what caught it**, which is decision 216's argument for enforcing a rule
     * in three places rather than one, doing real work.
     *
     * ⚠️ **BUT AN EXISTING ROW IS A DIFFERENT CASE FROM A NEW ONE, AND THAT IS
     * WHY THIS TAKES A LOCATION.** The version above this one filtered on
     * `isSeededAtProvisioning()` alone, which is correct about *creating* a row
     * and wrong about *updating* one: a tenant who has already enabled Yelp sees
     * it on their own picker, and an answer of "ask everyone" that moved Google
     * and Facebook while leaving Yelp gated at 5 would be a control that
     * silently does not reach one of the destinations it is shown next to.
     * Decision 1143 makes the number the tenant's; a number that only reaches
     * some of their destinations is not theirs.
     *
     * So a destination is gateable when the platform forces no value **and**
     * either provisioning may seed it or its row already exists. Nothing here
     * creates a Yelp row, so 1161's "never by seed, default, or provisioning"
     * holds unchanged — `rowFor()` is only ever reached for a row that is
     * already there.
     *
     * ⚠️ **What stays deferred, and it is smaller than it was**: a tenant who
     * answers and *later* enables Yelp still gets it at `defaultThreshold()`
     * (5) rather than at the number they chose, because `enable()` does not read
     * the location's policy. The remedy is now one they have — re-saving on
     * `/account` applies their answer to the row that now exists — where before
     * this screen existed there was none at all.
     *
     * @return list<ReviewDestination>
     */
    private function gateable(Location $location): array
    {
        return array_values(array_filter(
            ReviewDestination::cases(),
            fn (ReviewDestination $destination): bool => $destination->forcedThreshold() === null
                && (
                    $destination->isSeededAtProvisioning()
                    || $this->destinations->thresholdFor($location, $destination) !== null
                ),
        ));
    }

    /**
     * Move the triage boundary when the answer moves it, and record the change
     * either way — one row, one statement, one audit entry.
     *
     * ⚠️ IT WROTE `gating_ack_at` TOO UNTIL 2026-08-12, AND THE AUDIT ENTRY IS
     * WHAT SURVIVES THE COLUMN (2074, 2661). The `everyone_invited` branch now
     * touches no column at all — its whole effect is the thresholds its caller
     * writes through `DestinationSettings` — and it still comes through here,
     * because that branch is a decision an owner made and `29` §19.3 wants a
     * threshold change audited with old and new values by name. Skipping the
     * call on the branch that has nothing left to write would delete the only
     * record that "ask everyone" was ever chosen.
     *
     * ⚠️ THIS IS `autopilot_settings.triage_threshold`'S FIRST WRITER IN `app/`.
     * The column has existed since Stage 0 with a default of 3, a CHECK added by
     * decision 383 — whose own migration comment says "it has no writer service
     * at all today, which makes the database the only layer there can be" — and
     * `ReviewRouter` reading it. Decision 272's shape, and the reason it went
     * unnoticed for so long is the usual one: a column with a sensible default
     * behaves correctly until somebody needs to change it.
     *
     * ⚠️ THE TRIAGE BOUNDARY AND THE INVITE THRESHOLDS RIDE ONE AUDIT ENTRY
     * BECAUSE THEY ARE ONE DECISION. `29` §19.3 wants a threshold change to
     * carry old and new values by name; two entries would let a reader find one
     * half of a pair that only means anything together, and `recordChange()` is
     * already the shape for it.
     *
     * @param  ?int  $triageThreshold  null leaves the column alone — see
     *                                 `inviteEveryone()` for the one branch
     *                                 that deliberately does not move it.
     * @param  array<string, mixed>  $extra
     */
    private function writeGatingRow(
        Location $location,
        ?int $triageThreshold,
        string $actor,
        string $action,
        array $extra = [],
    ): void {
        $settings = AutopilotSettings::query()
            ->where('location_id', $location->id)
            ->lockForUpdate()
            ->first();

        if ($settings === null) {
            // `TenantProvisioner` seeds this row (377), so its absence means a
            // location predating that fix. Refused rather than created here:
            // this service owns one column, and a second creator of
            // autopilot_settings is how two defaults drift apart.
            throw new InvalidArgumentException(
                'This location has no autopilot settings row, so there is nowhere to record '
                .'the triage boundary. TenantProvisioner seeds one for every location it '
                .'creates; a location without one predates that and needs backfilling.'
            );
        }

        $before = [];
        $after = [];

        if ($triageThreshold !== null) {
            $before['triage_threshold'] = (int) $settings->triage_threshold;
            $after['triage_threshold'] = $triageThreshold;

            $settings->forceFill(['triage_threshold' => $triageThreshold])->save();
        }

        // recordChange() rather than record(): COMP-02's acceptance criterion is
        // "change is audited with old and new values", and `29` §19.3 says the
        // same for thresholds generally. An entry holding only the new value
        // cannot answer what this location was doing before.
        $this->audit->recordChange(
            $action,
            $actor,
            before: $before,
            after: $after + $extra,
            entity: $settings,
        );

        $this->recordSupportWrite();
    }

    /**
     * Add support's own audit row and owner-facing feed entry when this write
     * happened inside an act-as session — {@see Impersonation::recordWrite()}.
     *
     * ⚠️ **INERT FOR AN ORDINARY OWNER WRITE.** `current()` returns null the
     * moment nobody is impersonating, which is every call this class has ever
     * had until this one. A view-only session cannot reach here at all: the
     * read-only connection {@see Impersonating} engages
     * refuses the `AutopilotSettings` UPDATE above before this line runs, so a
     * non-null session here is always act-as.
     */
    private function recordSupportWrite(): void
    {
        $session = $this->impersonation->current();

        if ($session === null) {
            return;
        }

        $this->impersonation->recordWrite($session, SupportWriteSubject::ReviewInviteRules);
    }

    /**
     * The *wrong tenant* case RLS cannot catch — `location_id` comes from the
     * passed model while the tenant comes from context, and the two are checked
     * against each other here because both ids are in hand. The same guard, for
     * the same reason, as `DestinationSettings::assertBelongsToTenant()`.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. A gating acknowledgement is evidence '
            .'about one business\'s own compliance position and cannot be filed against '
            .'somebody else\'s location.'
        );
    }
}
