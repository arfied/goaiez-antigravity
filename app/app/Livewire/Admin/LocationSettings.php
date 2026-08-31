<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\BrandVoice;
use App\Enums\IndexingEngine;
use App\Livewire\Admin\Concerns\AdminDetail;
use App\Livewire\Admin\Concerns\AdminForm;
use App\Models\AuditLogEntry;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\Indexing\Indexing;
use App\Services\Reviews\ReviewGating;
use App\Services\Reviews\ReviewReplies;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\Column;
use App\Support\Admin\DetailSection;
use App\Support\Admin\Field;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

/**
 * Autopilot settings for one location — form and detail on one screen.
 *
 * Exists to prove the two halves of the shell compose, the same way
 * AutomationRuns proves the table does. It declares fields(), sections(),
 * record() and an audit action, and writes no rules array, no fill loop and no
 * field markup.
 *
 * ⚠️ **IT DID ALSO WRITE "NO SAVE METHOD" UNTIL 2026-08-24, AND NOW WRITES
 * ONE** (9237) — four lines that establish the named account's tenancy and hand
 * off to `AdminForm`'s, which is where the validation, the fill and the audit
 * entry still live. See the section on where the tenancy goes, below.
 *
 * NO REVIEW THRESHOLD LIVES HERE ANY MORE (decision 311). It moved to
 * `review_destinations`, per destination, because Google's 5 and Trustpilot's
 * mandatory 0 cannot both be one number. ✅ **COMP-02's wizard screen now
 * exists** — `Livewire\Setup\ReviewRules`, through `ReviewGating` — and is the
 * only writer of a threshold or an acknowledgement. This docblock said it "does
 * not exist" until that shipped. CLAUDE.md still forbids a tenant-facing toggle
 * here, so do not re-add a threshold field: the wizard asks the question once,
 * with the disclosure COMP-02 requires, and this screen only reports the answer.
 *
 * ⛔ **IT IS ALSO THE ONLY PLACE A REVIEW HUB CAN BE TAKEN DOWN** (6860). The
 * `update_review_hub` field below is 6623's owed writer, and until it landed
 * this application could publish a location's approved reviews at a public
 * address and had no way at all to stop. It is not a tenant-facing toggle —
 * this screen is `super_admin` alone — which is exactly why **a tenant who
 * wants their hub down still has to ring somebody**, and why 6862 raises the
 * tenant-facing half as the owner's to rule on rather than assuming it.
 *
 * ⛔ **AND FOR AS LONG AS IT HAS EXISTED, `super_admin` ALONE MEANT NOBODY —
 * THIS SCREEN ANSWERED A 500 TO ITS ENTIRE AUDIENCE** (9236). Platform staff own
 * no business, `ResolveTenant` therefore establishes no tenant for them, and
 * every one of this screen's reads is tenant-scoped: the settings row, the
 * location, the indexing lines, the gating answer, the audited save. So the only
 * way to take a review hub down was to be the one person decision 621 describes
 * — a `super_admin` who also owns a business — and that reader was shown **their
 * own location's settings** under this heading. The 500 was the loud half of a
 * defect whose quiet half was a wrong answer.
 *
 * ## Named, not listed, and read inside that account's tenancy
 *
 * {@see AutomationRuns}'s pattern and 5730–5734's reasoning. `businesses`
 * carries two RLS policies and neither admits platform staff, so the runtime
 * role cannot enumerate accounts at all (569); an operator names one,
 * `Tenancy::actingAs()` sets `app.business_id` to it, and nothing else becomes
 * visible. A wrong number and a number belonging to nobody are deliberately
 * indistinguishable.
 *
 * ⚠️ **THE ROUTE NAMES A LOCATION AND A LOCATION CANNOT NAME ITS ACCOUNT HERE.**
 * `locations` is `ENABLE`+`FORCE` row-level security, so no read turns a path
 * segment into the tenant it belongs to without already being inside one. The
 * account is asked for rather than derived, and the location is checked *inside*
 * the named tenancy.
 *
 * ⛔ **WHERE THE TENANCY GOES, WHICH IS THE ONE THING NO OTHER SCREEN HAD TO
 * DECIDE** (9237). `AutomationRuns` wraps `AdminTable::rows()` — a method it
 * calls itself. This screen composes `AdminForm` and `AdminDetail`, whose entry
 * points were called from the Blade view (`$this->detailSections()`,
 * `$this->visibleFields()`) and from the client (`save()`), so there was nowhere
 * outside them for a wrap to sit. **The traits are untouched**: the view is
 * handed data this component built, the three trait entry points are aliased
 * down to `protected` so nothing but this class may call them, and the two
 * methods this class does own — {@see self::render()} and {@see self::save()} —
 * are where the tenancy is established. `Tenancy::idOrFail()` inside the traits
 * is now a backstop that cannot fire in normal operation rather than the line
 * that 500s.
 *
 * ⚠️ **AND THE ALIASES ARE NOT TIDINESS.** A public method on a Livewire
 * component is callable from `/livewire/update` with whatever snapshot the
 * client sends, and the tenantless census cannot see that door (its own header
 * says so). `visibleFields()` and `detailSections()` open with
 * `Tenancy::idOrFail()`, so left public they were the same 500 by a second
 * route, on a component that now has a state in which no account is named.
 *
 * ⛔ **AND IT WAS THE ONLY WRITER OF A COLUMN NOTHING READS — REMOVED 8582.**
 * `automation_mode` had a field here labelled *"How much runs on its own"*, and
 * nothing in `app/` has ever read the column, so an operator choosing Confirm
 * changed a stored value and an audit row and **not one automation's
 * behaviour**. See {@see self::fields()} for the whole argument. ⚠️ **The
 * general shape is worth more than the instance**: this screen is where 272's
 * missing writers get built, so it is also the place a missing *reader* hides
 * best — every `Field::make()` below renders identically, and the one with no
 * consumer was distinguishable only by grepping for it. Every other field on
 * this form was traced to a reader while `automation_mode` was being removed,
 * and each has one; **the count is deliberately not written here**, because a
 * number in this docblock goes stale the next time a field is added and the
 * check is one grep per field.
 */
final class LocationSettings extends Component
{
    /** @use AdminDetail<AutopilotSettings> */
    use AdminDetail {
        detailSections as protected;
    }

    /** @use AdminForm<AutopilotSettings> */
    use AdminForm {
        visibleFields as protected;
        save as protected saveDeclaredFields;
    }

    /**
     * The location this screen was opened on, straight from the path.
     *
     * ⚠️ **`#[Locked]` BECAUSE IT NAMES A RECORD AND `mount()` IS THE ONLY THING
     * THAT MAY SET IT** — `Admin\TenantLocations`' reasoning. Unlocked it
     * arrives in the update payload, so a crafted request could move an
     * already-named account's form onto a different location of that account
     * while the `business.viewed_by_staff` row filed at lookup still names the
     * one the operator opened, and `$state` — read from the first record —
     * would be saved onto the second.
     */
    #[Locked]
    public int $locationId;

    /** What the operator typed. An account number; see {@see self::resolve()}. */
    public string $reference = '';

    /**
     * The account they have named, or null.
     *
     * ⚠️ **`#[Locked]` BECAUSE {@see self::resolve()} IS THE ONLY THING THAT MAY
     * SET IT, AND THE AUDIT ROW IS WRITTEN THERE.** Without the attribute this
     * is an ordinary public property that arrives in the update payload, so
     * anybody who could reach this component could point it at any account and
     * let the form read — and then **write** — that tenant's autopilot settings
     * with `resolve()` never called and nothing recorded anywhere.
     */
    #[Locked]
    public ?int $businessId = null;

    /**
     * The account's name, for the operator's own screen.
     *
     * ⚠️ **DELIBERATELY NOT `#[Locked]`, WHICH IS 5793's LINE RATHER THAN AN
     * OVERSIGHT.** `AccountAudit` locks its copy because that screen is an
     * attestation about who read what; `AutomationRuns` and `TenantLocations`
     * leave theirs open because they are not. This one is not either — the save
     * is audited on the settings row in the tenant's own log, keyed on ids this
     * string cannot move.
     */
    public string $businessName = '';

    public function mount(int $location): void
    {
        // Repeated on the component rather than left to the route's `can:`
        // middleware — decision 630: a route-gate test passes while `mount()` is
        // wide open, because `can:` refuses during route matching and the
        // component never runs.
        $this->authorize(AdminAccess::GATE);

        // ⛔ **NO QUERY, AND THAT IS THE FIX** (5733). This used to call
        // `mountAdminForm()`, which reads the settings row through the tenant
        // scope — so the first line of work this screen did was the line that
        // threw for everybody it was built for.
        $this->locationId = $location;
    }

    /**
     * Name the account this location belongs to, and file the fact that we did.
     *
     * A miss says so plainly rather than 404ing the screen: the operator has
     * typed a number, and "no account with that number" is the answer to that,
     * where a 404 reads as the screen itself being broken.
     *
     * ⚠️ One entry per resolved lookup, never per render (5734) — Livewire
     * re-renders on every property update, and a row per keystroke is a log
     * nobody can read, which is its own kind of unaudited. A miss writes
     * nothing: there is no tenant to file it under.
     *
     * ⚠️ **THE ROW IS FILED BEFORE THE LOCATION IS CHECKED, ON PURPOSE.** An
     * operator who names a real account has opened it and this application has
     * read inside it, whether or not the location in the path turns out to be
     * theirs. Filing only on the happy path would leave the probe — name an
     * account, learn whether it owns location N — as the one read this screen
     * does not record.
     *
     * ⚠️ **AND IT CAN THROW.** The audit write is not wrapped and `$businessId`
     * is set after it, so an unwritable `audit_log` refuses the whole lookup
     * instead of showing the account unrecorded.
     */
    public function resolve(AuditService $audit): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->reset(['businessId', 'businessName', 'state', 'saved']);
        $this->resetErrorBag();

        $reference = trim($this->reference);

        if ($reference === '' || ! ctype_digit($reference)) {
            $this->addError('reference', 'Enter the account number from the ticket.');

            return;
        }

        $id = (int) $reference;

        $business = Tenancy::actingAs(
            $id,
            // Its own tenant context, because `businesses` is FORCE ROW LEVEL
            // SECURITY on its own id: outside it this returns nothing whatever
            // the id is.
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );

        if (! $business instanceof Business) {
            $this->addError('reference', 'No account with that number.');

            return;
        }

        Tenancy::actingAs(
            $id,
            fn (): AuditLogEntry => $audit->record(
                'business.viewed_by_staff',
                $this->actor(),
                $business,
                ['surface' => 'admin.location-settings', 'location_id' => $this->locationId],
            ),
        );

        /** @var array{location: bool, settings: bool} $found */
        $found = Tenancy::actingAs($id, fn (): array => [
            'location' => Location::query()->whereKey($this->locationId)->exists(),
            // ⚠️ **TWO PROBES RATHER THAN ONE, BECAUSE THEY ARE TWO STATES AN
            // OPERATOR CAN ACT ON DIFFERENTLY.** A location belonging to
            // somebody else and a location of this account whose settings row
            // was never created look identical to a single `sole()` — which is
            // what this screen used to do, and it turned both into an exception.
            'settings' => AutopilotSettings::query()
                ->where('location_id', $this->locationId)
                ->exists(),
        ]);

        if (! $found['location']) {
            $this->addError('reference', 'That account has no location '.$this->locationId.'.');

            return;
        }

        if (! $found['settings']) {
            $this->addError('reference', 'Location '.$this->locationId.' is in this account and has no autopilot settings yet.');

            return;
        }

        $this->businessId = $id;
        $this->businessName = (string) $business->name;

        // The form's opening values, read inside the account that owns them.
        Tenancy::actingAs($id, function (): void {
            $this->loadDeclaredState();
        });
    }

    public function clearAccount(): void
    {
        $this->reset(['businessId', 'businessName', 'reference', 'state', 'saved']);
        $this->resetErrorBag();
    }

    /**
     * Save the declared fields, inside the named account's tenancy.
     *
     * ⛔ **REACHED WITH NO ACCOUNT NAMED IT THROWS, AND LOUD IS THE POINT** —
     * `AutomationRuns::query()`'s reasoning. The form only exists on a screen
     * that has named an account, so this state is a hand-posted update rather
     * than something a person can click, and writing a tenant's autopilot
     * settings under whatever tenant happened to be in context is the quiet
     * wrong answer rather than the loud one.
     */
    public function save(): void
    {
        $this->authorize(AdminAccess::GATE);

        $businessId = $this->businessId ?? throw new LogicException(
            'LocationSettings::save() was reached with no account named. Nothing '
            .'here may write a tenant\'s autopilot settings without saying whose.'
        );

        Tenancy::actingAs($businessId, function (): void {
            // The trait's own save(), validation, audit entry and all. Every
            // read and write it makes — including `AuditService::record()`,
            // which calls `Tenancy::idOrFail()` itself — happens inside this
            // account, so the entry lands in the tenant's own log rather than
            // in whichever tenant the operator was last viewing (419).
            $this->saveDeclaredFields();
        });
    }

    /**
     * @return array<int, Field>
     */
    protected function fields(): array
    {
        return [
            // ⛔ **`automation_mode` WAS THE FIRST FIELD ON THIS FORM AND IT IS
            // REMOVED — 8582.** It was labelled *"How much runs on its own"*
            // and helped *"Auto runs without asking. Confirm waits for your
            // reply."*, and **it waited for nothing**: the column has a cast on
            // `AutopilotSettings`, two mentions in other docblocks, and **no
            // reader anywhere in `app/`**. `AutopilotJob::isEnabled()` returns
            // `true` unconditionally and no subclass consults it, so every
            // automation ran whatever an operator chose here. Decision 4467
            // found this and struck the claim from `DraftRecoveryOutreachJob`'s
            // docblock — **and left the screen still making it to an operator's
            // face**, which is the more expensive half: a docblock misleads a
            // reader, a form field invites an action whose promised effect never
            // happens.
            //
            // ⚠️ **REMOVED RATHER THAN WIRED, ON 4467's OWN REASONING.** Wiring
            // a reader changes the behaviour of every automation in the product
            // at once and is not a screen slice's to make. Removed rather than
            // relabelled, because a control that visibly does nothing is still
            // the support surface `CLAUDE.md`'s first tiebreaker refuses — it
            // only moves the ticket from *"I set Confirm and it still posted"*
            // to *"when does Confirm start working?"*.
            //
            // ⚠️ **THE WORKING VERSION OF THIS CONTROL IS ALREADY ON THIS FORM,
            // WHICH IS WHY THE REMOVAL COSTS AN OPERATOR NOTHING.** *"Wait for
            // my reply"* is `full_auto_post_replies` with `reply_auto_post_min`
            // beneath it, both read by `ReviewReplies::wantsAutoPost()`. An
            // operator had two controls that read as the same promise, one live
            // and one inert, and the inert one carried the more general label —
            // so it is the one they reached for.
            //
            // ⛔ **THE COLUMN IS NOT DROPPED, AND NOT BY THIS LANE** (8583).
            // Removing this field leaves `automation_mode` with neither reader
            // nor writer, which makes it *reportable* as dead rather than
            // droppable on a lane's judgement: 8390's drop took an explicit
            // owner ruling, and `29` §2 rule 37 names this setting by name.
            //
            // ⚠️ A STAR RATING, NOT MINUTES (1725). This field shipped as
            // "Wait before posting a reply", `min:0, max:120`, described as
            // minutes to hold a reply — while `ReviewReplies::wantsAutoPost()`
            // read the same column as `rating >= reply_auto_post_min`, which is
            // what `29` §210, `17` GBP-04 and `14` §2.3 all say it is. The two
            // readings disagreed and **the disagreement failed open**: an ops
            // admin setting "post immediately = 0" made `rating >= 0` true for
            // every review ever left, so every one-star Google review
            // auto-published an AI reply with nobody in the loop — defeating
            // `29` §12.1's build-failing "low-star reviews never auto-post"
            // through the product's own settings screen.
            //
            // ⚠️ THE VALIDATION IS NOT THE PROTECTION AND MUST NOT BE READ AS
            // IT. `min:1` refuses `0`, but `1` is a legal rating and would
            // auto-post everything. What actually holds the compliance line is
            // `ReviewReplies::AUTO_POST_RATING_FLOOR`, which no setting can
            // loosen; this rule only keeps the stored number meaningful.
            Field::make('reply_auto_post_min', 'Auto-post replies at this star rating or above')
                ->rules(['required', 'integer', 'min:1', 'max:5'])
                ->help('Ratings below this go to the owner for approval. Reviews under '
                    .ReviewReplies::AUTO_POST_RATING_FLOOR.' stars are never auto-posted, whatever this says.'),

            Field::make('brand_voice', 'How replies sound')
                ->enum(BrandVoice::class)
                ->required(),

            Field::make('auto_reply', 'Reply to reviews automatically')
                ->boolean(),

            // ⚠️ THE WRITER THIS COLUMN NEVER HAD (1734, decision 272's shape).
            // `full_auto_post_replies` defaults `true` and is the switch between
            // "draft for approval" and "publish to a public Google listing", and
            // until now **nothing in `app/` could set it** — an isolation test
            // over a column no screen writes is green and inert. Platform staff,
            // not the tenant: this is the admin panel behind `AdminAccess::GATE`,
            // so it is not the tenant-facing toggle CLAUDE.md refuses.
            Field::make('full_auto_post_replies', 'Post replies at that rating without asking')
                ->boolean(),

            Field::make('send_review_requests', 'Ask customers for reviews')
                ->boolean(),

            // ⛔ **THE OFF SWITCH FOR A PUBLIC PAGE, WHICH HAD NO WRITER AT ALL
            // UNTIL NOW** (6623, 272's shape). `update_review_hub` defaults
            // `true`, `ReviewHubPages::publishedFor()` has read it on every
            // request since the hub shipped, and **nothing in `app/` could set
            // it** — so 6547's own closing sentence, *"turning it back on
            // serves the same address again, which is what makes the ruling
            // cheap to reverse"*, described a control that did not exist. A
            // tenant who wanted their customers' words off the public internet
            // had no way to ask for it but a support ticket support had no
            // screen to answer.
            //
            // ⚠️ **OFF TAKES THE PAGE DOWN; IT DELETES NOTHING** (6547). The
            // hub row survives, every review survives, and the same address
            // serves again the moment it goes back on. That is the reading the
            // reader already implements — this field decides nothing about the
            // meaning, it only makes the deployed meaning reachable.
            //
            // ⚠️ **PLATFORM STAFF, NOT THE TENANT**, on `full_auto_post_replies`'
            // reasoning above: this screen is behind `AdminAccess::GATE`, which
            // is `super_admin` alone, so it is not the tenant-facing toggle
            // `CLAUDE.md` refuses. **A tenant-facing one is a separate question
            // and it is the owner's** — 1143 is the only time that rule has
            // been overridden and it took an explicit ruling. See 6862.
            Field::make('update_review_hub', 'Publish this location\'s reviews page')
                ->boolean()
                ->help('On, anybody holding the link reads the reviews this location '
                    .'approved for display. Off takes the page down straight away — '
                    .'the page and the reviews are kept, and turning it back on '
                    .'serves the same address again.'),
        ];
    }

    /**
     * @return array<int, DetailSection>
     */
    protected function sections(): array
    {
        return [
            DetailSection::make('This location', [
                Column::make('location.name', 'Name'),
                Column::make('location.primary_phone', 'Phone'),
                Column::make('location.current_rating', 'Rating')->numeric(),
                Column::make('location.review_count', 'Reviews')->numeric(),
            ]),

            DetailSection::make(
                'Consent',
                [
                    // COMP-02's "persistent settings-screen indicator while
                    // gating is active".
                    //
                    // ⚠️ THIS ROW USED TO SIT BESIDE A `gating_ack_at` ONE, AND
                    // REMOVING THAT COLUMN MADE THIS THE ONLY HONEST ANSWER
                    // (2074, 2660). The pair existed because they could
                    // disagree: an acknowledgement with every threshold at 0
                    // gated nobody, and a threshold above 0 with no
                    // acknowledgement was ignored by `ReviewRouter`. The second
                    // state no longer exists — a threshold above 0 is applied —
                    // so an operator reading "Not yet" beside "Yes, filtering"
                    // would be the misreading, in the opposite direction.
                    //
                    // A location with no destination enabled still asks nobody
                    // anywhere and is reported as filtering nothing, which is
                    // what `offeredFor()` gives this.
                    //
                    // Never colour alone (`22`): the words carry the meaning.
                    Column::make('location_id', 'Filtering who gets asked')
                        ->format(fn (mixed $v, Model $record): string => $this->describeGating($record)),
                ],
                'Whether this location currently invites only higher ratings to review publicly.',
            ),

            DetailSection::make(
                'Search engines',
                // ⚠️ **ONE ROW PER ENGINE, DERIVED FROM THE ENUM.** A hand-written
                // pair would go stale the day a participant joins or leaves —
                // IndexNow gained two between decision 48 and this slice (5681) —
                // and the label is the enum's own `audience()` so the words and
                // the lookup cannot disagree.
                array_map(
                    fn (IndexingEngine $engine): Column => Column::make('location_id', $engine->audience())
                        ->format(fn (mixed $v, Model $record): string => $this->describeIndexing($record, $engine)),
                    IndexingEngine::cases(),
                ),
                'The last time we told each search engine about a page on this website, and what came back.',
            ),
        ];
    }

    /**
     * What happened the last time this location's website was announced to one
     * search engine.
     *
     * ⛔ **THIS IS THE READER `indexing_submissions` DID NOT HAVE** (272, 1222).
     * The table shipped with Stage 0, gained its first writer in row 9 slice E,
     * and almost every row it holds today is a recorded refusal naming machinery
     * of ours that is not built. **A refusal nobody can see is a silent skip
     * with extra storage**, so the reader lands in the same slice as the writer
     * rather than being owed to a screen nobody has scheduled.
     *
     * ⚠️ **STAFF, NOT THE OWNER.** These sentences name our own unbuilt plugin;
     * an owner-facing version would be a support ticket about something they
     * cannot act on, which `CLAUDE.md`'s first tiebreaker settles.
     */
    private function describeIndexing(Model $record, IndexingEngine $engine): string
    {
        $location = $record instanceof AutopilotSettings ? $record->location : null;

        if ($location === null) {
            return 'No location.';
        }

        foreach (app(Indexing::class)->latestPerEngine($location) as $line) {
            if ($line->engine === $engine) {
                return $line->staff();
            }
        }

        // ⚠️ NOT "nothing to report" (229). Nothing has been *announced*, which
        // on this deployment usually means nothing has been published — a
        // different sentence from a route that is failing.
        return 'Nothing announced yet.';
    }

    /**
     * Whether this location currently filters who is asked for a review.
     *
     * Extracted from the column's formatter because the null-safety matters:
     * `AutopilotSettings::location()` is a nullable relation, and a location
     * that has gone means there is nothing being filtered — which is the safe
     * reading as well as the true one.
     */
    private function describeGating(Model $record): string
    {
        $location = $record instanceof AutopilotSettings ? $record->location : null;

        if ($location === null) {
            return 'No — everyone who leaves feedback is invited';
        }

        return app(ReviewGating::class)->isGating($location)
            ? 'Yes — only higher ratings are invited'
            : 'No — everyone who leaves feedback is invited';
    }

    protected function record(): Model
    {
        // Through the tenant-scoped relation, so a location id from another
        // tenant does not resolve at all rather than resolving and being hidden.
        return AutopilotSettings::query()
            ->where('location_id', $this->locationId)
            ->sole();
    }

    protected function auditAction(): string
    {
        return 'autopilot_settings.updated';
    }

    /**
     * The screen, read inside the named account's tenancy.
     *
     * ⚠️ **EVERYTHING IS MATERIALISED BEFORE THE CLOSURE ENDS**, which is the
     * trap `AutomationRuns::runs()` names in its own `finally`: a builder or an
     * unloaded relation handed to the view outlives the tenancy it was built in.
     * `detailSections()` renders every entry to a scalar as it goes — including
     * the `location.*` columns, which touch the relation, and `describeGating()`
     * and `describeIndexing()`, which each run their own queries — and
     * `visibleFields()` returns declarations that read nothing.
     *
     * ⚠️ **A LOCATION THAT NO LONGER RESOLVES FALLS BACK TO THE INVITATION**
     * rather than throwing — `TenantLocations::render()`'s shape. It is reachable
     * by an account being erased between the lookup and this render.
     */
    public function render(): View
    {
        if ($this->businessId === null) {
            return view('livewire.admin.location-settings', [
                'location' => null,
                'sections' => null,
                'fields' => null,
            ]);
        }

        /** @var array{location: ?Location, sections: ?Collection<int, array{title: string, description: ?string, entries: array<int, array{label: string, value: mixed, numeric: bool}>}>, fields: ?Collection<int, Field>} $state */
        $state = Tenancy::actingAs($this->businessId, function (): array {
            $location = Location::query()->whereKey($this->locationId)->first();

            if (! $location instanceof Location) {
                return ['location' => null, 'sections' => null, 'fields' => null];
            }

            return [
                'location' => $location,
                'sections' => $this->detailSections(),
                'fields' => $this->visibleFields(),
            ];
        });

        return view('livewire.admin.location-settings', $state);
    }

    /**
     * The same actor format the rest of the console writes — `user:{id}`.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.(string) $id;
    }
}
