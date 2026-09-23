<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Enums\AutopilotActionType;
use App\Enums\DataClassification;
use App\Enums\ExportSource;
use App\Enums\ExportStatus;
use App\Enums\ImpersonationCapability;
use App\Enums\TenantDeletionOutcome;
use App\Exceptions\ImpersonationRefused;
use App\Jobs\BuildTenantExportJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Review;
use App\Models\TenantExport;
use App\Models\User;
use App\Notifications\TenantExportReady;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Consent\ConsentTrailEntry;
use App\Services\Crm\CustomerMerges;
use App\Services\Crm\CustomerTimeline;
use App\Services\Crm\TimelineEntry;
use App\Services\Feedback\ConsentDisclosure;
use App\Services\Impersonation\Impersonation;
use App\Services\Mail\PlatformMailer;
use App\Services\Messaging\MessageLog;
use App\Services\Reviews\ReviewReplies;
use App\Services\Tenant\TenantDeletion;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;
use ZipArchive;

/**
 * `28` §3.7's export engine, reused by `44` §10 — one builder, both callers.
 *
 * ⚠️ **THE ONLY WRITER OF `tenant_exports`**, held there by a chokepoint lint in
 * `tests/Feature/Architecture/CrmTest.php`, the same shape `CustomerMerges` and
 * `CrmTasks` hold their own tables to. `request()` is the entry point both
 * `Livewire\Account\Settings`'s "Download my data" button and the ops
 * data-request queue's approval call, so a screen that wants a third way to
 * start an export grows this class rather than a second writer.
 *
 * ## The rule this class exists to keep, restated because it is easy to erode
 *
 * *"Exporting is never delayed, gated on retention offers, or degraded"* (`28`
 * §3.7). `request()` therefore never checks {@see TenantPause}
 * or {@see TenantSuspension} — a paused or suspended tenant
 * can still get their data out, which is the one moment this promise is worth
 * anything. {@see BuildTenantExportJob} carries the same rule at the
 * job layer; see its docblock for why it does not extend `AutopilotJob`.
 *
 * ## What is refused, and why refusing beats a silent gap
 *
 * **A PHI-classified tenant's export refuses outright.** `29` §2 rule 24 routes
 * PHI to a separate schema, role and KMS key, and this application has none of
 * the three (`docs/STAGE-0-GAPS.md` — no PHI tenant can be onboarded until Stage
 * 3, so this branch is unreached in production today). Building a masking layer
 * this slice cannot verify would be decisions 314–316's mistake — a claim of
 * safety this class has not earned. Refusing outright, with the reason on the
 * row, is the honest answer until Stage 3 does the real work.
 *
 * **Published content and report PDFs are named in the manifest and never
 * shipped.** Neither has a writer anywhere in `app/` — `28` Part 7's seven
 * reports do not exist, and no HTML/MD content store exists either. An export
 * that silently omitted them would read as complete; the manifest says what it
 * could not include and why, every time.
 *
 * **The message log is outbound only, and the file name says so.** `44` §10 and
 * `28` §3.7 both call it "the message log", and `outreach_messages` has no
 * direction column — decision 941. `messages_outbound_only.csv` is not the file
 * name either document specifies; it is the honest one.
 *
 * ## Why `MessageLog::forCustomer()`, never `OutreachMessage::` directly
 *
 * `tests/Feature/Architecture/ConsentTest.php` holds `outreach_messages` to two
 * writers/readers plus two narrowly-reasoned exemptions. This class is not a
 * third: it never queries the table directly, so business-level rows with no
 * `customer_id` (owner alerts) are outside what this export can see. The
 * manifest says so — see `messages_outbound_only.csv`'s note.
 *
 * ## ⚠️ SUPPORT MAY NOT START ONE FROM INSIDE THE OWNER'S ACCOUNT (1904)
 *
 * `28` §9.4's blocklist has named {@see ImpersonationCapability::ExportTenantData}
 * since it was written, with the instruction *"use the ops export tool, which
 * needs a second approver"* — and until this class existed there was nothing to
 * guard, so the case sat in the enum answering `reachableToday() === false`.
 * {@see self::request()} is its call site now. An act-as agent pressing the
 * owner's own button is one person pulling a whole account with no second
 * person, which routes around the approval `DataRequests::approveTenantExport()`
 * implements; the ops path is unaffected because staff hold no impersonation
 * session while working the queue (`28` §9.1 — support belongs to no business).
 *
 * ## ⚠️ THE FILE IS DELETED, AND THIS CLASS IS WHAT DELETES IT (1902)
 *
 * A ZIP here is a complete unencrypted copy of every contact's name, email,
 * phone and consent state. {@see self::purgeExpired()} is the sweep behind
 * `exports:prune`, and {@see self::purgeAllFor()} is what
 * {@see TenantDeletion::execute()} calls **before** it destroys the account —
 * because `tenant_exports.business_id` cascades, and deleting the row that
 * names the object first would leave an unreferenced file nobody can find to
 * delete. `PrunePublicAudits`' rule, applied to a much worse artifact: a
 * retention column nothing acts on is not a retention policy, it is a claim.
 */
final class ExportBuilder
{
    /**
     * `28` §3.7: "link expires in 7 days". `44` §10 restates it for the
     * per-object and scheduled variants this slice does not build; this is the
     * one constant behind both.
     */
    public const int LINK_EXPIRY_DAYS = 7;

    /**
     * How long an owner's own click reuses the build it already has (1907).
     *
     * ⚠️ **THIS CAPS THE RATE AND NEVER THE CONTENTS**, and the distinction is
     * `28` §3.7's own rule rather than a preference: an export may not be
     * "delayed, gated on retention offers, or **degraded**", so a row cap or a
     * byte cap — the obvious reading of `29` §2 rule 43 — is the one thing this
     * class may not do. Twelve clicks used to mean twelve full assemblies,
     * twelve R2 objects and twelve emails; they now mean one build and a link.
     * Nothing an owner receives is smaller than it was.
     *
     * ⚠️ **THE OPS PATH IS DELIBERATELY EXEMPT.** A statutory request approved
     * by a second person must produce a build *of the account as it stands when
     * the request was approved* — handing it a ZIP assembled before the request
     * existed would close a subject-access ask with data from a different day.
     */
    public const int REQUEST_COOLDOWN_MINUTES = 15;

    /**
     * How long a build still in flight is handed back to a second click (1995).
     *
     * ⚠️ **THIS BOUND IS WHAT STOPS ONE STUCK ROW BLOCKING AN ACCOUNT FOREVER.**
     * The in-flight branch of {@see self::reusable()} reused a `queued` row
     * *however old it was*, on 1907's reasoning that a queue backed up behind a
     * large tenant is exactly when somebody clicks again — which is true, and
     * true for about ten minutes. A row that never completes is reachable
     * whenever {@see BuildTenantExportJob::failed()} does not fire: the worker
     * SIGKILLed on the last attempt, the job row lost, the queue cleared. On
     * the production box's **database** queue driver that is not exotic.
     *
     * Unbounded, the symptom was total and silent: sixty days later, twelve
     * clicks still produced one row, still `queued`, **zero objects**, and each
     * click toasted *"Building your download — we will email a link when it is
     * ready"*. `tenant_exports` has one writer and no method that resets a row,
     * so nothing in ops could clear it — the only escape was a two-person
     * ops-approved export, which is a support call to get a feature `28` §3.7
     * says is *"never delayed"*.
     *
     * ⚠️ **SIZED TO THE JOB'S OWN LADDER, NOT PICKED.** Three attempts with
     * `[60, 300]` backoff is six minutes of waiting plus three assemblies; ten
     * minutes covers the honest case with room, and past it a fresh row is the
     * right answer because the old one is not coming back. The stale row is
     * left where it is rather than failed from here: this class's `fail()` is
     * the exhausted-ladder path, and a reader inventing an outcome for a job it
     * cannot see is decision 823's shape.
     */
    public const int IN_FLIGHT_REUSE_MINUTES = 10;

    /**
     * Contacts and reviews per read while assembling (1908).
     *
     * The unchunked version loaded every contact and every review-with-replies
     * into memory and then `file_get_contents()`'d the finished ZIP on top —
     * three whole-dataset copies for a tenant large enough to matter, ending in
     * an OOM'd worker, three burnt retries and a `failed` row. Rule 43 asks for
     * graceful degradation, and a bounded working set is what that means here.
     */
    private const int CHUNK = 500;

    /**
     * What a per-contact export does **not** contain, named in every manifest.
     *
     * ⚠️ **THE CLASS DOCBLOCK'S RULE, APPLIED TO A NARROWER SUBJECT** — *"an
     * export that silently omitted them would read as complete"*. It matters
     * more here than it does for the account archive, because this file is
     * forwarded to the person it is about, and a data-subject answer that omits
     * a category without saying so is a worse document than one that is honest
     * about its edges.
     *
     * ⛔ **`44` §10 ASKS FOR FOUR THINGS AND THIS SHIPS THREE** (6568). *"files"*
     * is the missing one and it is missing for a reason rather than for room:
     * `inbound_media` hangs off `inbound_messages`, which is platform-scoped and
     * keyed on `identifier_type` + `value_hash` rather than on a contact — so
     * there is no reader anywhere in `app/` that turns a contact into their own
     * photographs, and writing one means hashing a phone number to find rows in
     * a table with no tenant on it. That is a slice, and doing it badly reaches
     * another tenant's messages.
     *
     * ⛔ **AND `calls` IS THE ONE OMISSION THAT IS REACHABLE TODAY** (6569).
     * That table *does* carry `customer_id`. It is left out because
     * {@see CustomerTimeline} has no case for it — voice has never been enabled
     * in production (`voice.enabled` seeds false and `VOICE_DRIVER` selects the
     * null driver), and adding a source to the **export** that the **profile
     * screen** does not show would make this file stop being "what the tenant
     * can already see", which is the property the whole design rests on. The
     * honest fix is a timeline case, and it belongs with whoever turns voice on.
     *
     * @var list<array{file: string, reason: string}>
     */
    private const array CONTACT_EXCLUSIONS = [
        [
            'file' => 'files/',
            'reason' => 'Pictures this contact sent by text, and any voicemail audio, are not in '
                .'this file. They are stored against the inbound message that carried them '
                .'rather than against a contact, and this application has no way yet to '
                .'resolve one to the other.',
        ],
        [
            'file' => 'calls.csv',
            'reason' => 'Phone calls are not in this file. Voice has never been switched on for '
                .'any account, so there are none to include; when it is, calls will appear '
                .'in the timeline first.',
        ],
    ];

    /**
     * The three ways this file can contain somebody who did not ask for it.
     *
     * ⛔ **THIS IS THE MOST IMPORTANT THING IN THE MANIFEST AND IT IS ADDRESSED
     * TO THE TENANT, NOT TO THE SUBJECT** (6571). `CLAUDE.md`'s ambiguity rule
     * ranks *less stored PII* above cost, and the version of that rule which
     * applies to a **disclosure** is that a file about one person must not hand
     * over another. Two of these three cannot be fixed by filtering — they are
     * judgements about identity that only the tenant is in a position to make —
     * so the answer is to name them where the person about to press send will
     * read them, rather than to assert a completeness this code has not earned
     * (314–316).
     *
     * ⚠️ **THE THIRD IS A REAL PROPERTY OF `ConsentService::proofFor()`**, not a
     * hypothetical: withdrawals are matched on the identifier a contact holds
     * **now**, so a STOP recorded against a phone number that later moved to
     * this contact appears in their trail. `identifier` is in `consent.csv` for
     * exactly that reason — it is what lets a reader see which number a row is
     * about instead of taking the join on trust.
     *
     * @var list<string>
     */
    private const array CONTACT_FORWARDING_NOTES = [
        'Merged records: every contact record folded into this one is included, on this '
        .'account\'s own judgement that they are the same person. If a merge was wrong, this '
        .'file contains somebody else — the merged ids are listed under "scope".',
        'Notes: notes are what this account wrote about this contact in their own words, and '
        .'they may name other people. Read them before you send this file on.',
        'Withdrawals: a request to stop messaging is recorded against a phone number or email '
        .'address rather than against a contact, so a withdrawal made by a previous holder of '
        .'this contact\'s number would appear here. The "identifier" column says which address '
        .'each row is about.',
    ];

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly AuditService $audit,
        private readonly ActivityService $activity,
        private readonly PlatformMailer $mailer,
        private readonly Impersonation $impersonation,
        private readonly ReviewReplies $replies,
        // ⚠️ **THE PER-CONTACT EXPORT COMPOSES THE THREE READERS THE PROFILE
        // SCREEN ALREADY USES, AND OWNS NO QUERY OF ITS OWN** (6561). Each of
        // them sits behind a chokepoint that `Architecture\ConsentTest` and
        // `Architecture\CrmTest` enforce — `outreach_messages`,
        // `consent_records`, `crm_notes`, `crm_tasks`, `customer_merges` and
        // `short_link_clicks` are six tables between them — and a CSV writer
        // reaching any of them directly would be six allowlist edits, each
        // reasonable on its own diff. Composing instead also buys the property
        // that makes this export defensible: **it contains exactly what
        // `Account\CustomerProfile` already shows that tenant**, so nothing new
        // is disclosed by building it.
        private readonly CustomerTimeline $timeline,
        private readonly ConsentService $consent,
        private readonly CustomerMerges $merges,
    ) {}

    /**
     * Open a new export and dispatch the job that builds it.
     *
     * ⚠️ **NEVER DELAYED, NEVER GATED** — see the class docblock. This writes
     * the row and dispatches in the same call, with nothing between them that
     * could refuse on the tenant's state.
     *
     * Wrapped in {@see Tenancy::actingAs()} rather than assuming the caller
     * already has this tenant established: the owner's own "Download my data"
     * button does (`ResolveTenant` runs on the whole web group), but the ops
     * queue's approval call does not — support has no tenant, `28` §9.1 — so
     * this is the one place that has to work from both.
     *
     * ⚠️ **REFUSED INSIDE AN IMPERSONATION SESSION, BEFORE ANY ROW IS WRITTEN**
     * — `28` §9.4's own blocklist entry, see the class docblock. Inert for every
     * ordinary caller, because {@see Impersonation::refuse()} returns
     * immediately when no session is open.
     *
     * ⚠️ **AN OWNER'S REPEATED CLICK REUSES THE BUILD IT ALREADY HAS**, and it
     * caps the rate rather than the contents — see
     * {@see $this->requestCooldownMinutes()}, which also says why the ops path is
     * exempt.
     *
     * @throws ImpersonationRefused when support tries to start
     *                              one from inside the account
     */
    public function request(
        Business $business,
        User $requester,
        ExportSource $source,
        ?int $dataRequestId = null,
    ): TenantExport {
        return $this->open($business, $requester, $source, null, $dataRequestId);
    }

    /**
     * `44` §10's per-contact export — one contact's own copy of everything this
     * account holds about them, which is the surface an individual data-subject
     * request is answered from.
     *
     * ⛔ **A SEPARATE METHOD RATHER THAN A NULLABLE ARGUMENT ON `request()`, AND
     * IT IS NOT A STYLE CHOICE** (6562). The two exports are the same engine and
     * *completely different disclosures*: one is an account's own archive going
     * to the person who owns the account, the other is a named individual's file
     * which the tenant will forward to that individual. A call site that could
     * reach either by passing or omitting one argument is a call site where a
     * dropped argument silently widens a disclosure — the whole-tenant archive
     * sent to a customer who asked what you hold about them. Naming the act
     * makes that impossible to do by omission.
     *
     * ⚠️ **NO `$dataRequestId`, DELIBERATELY.** `28` §9.5 puts a *"per-contact
     * consent audit export"* in the ops queue, and `DataRequestKind` has no case
     * for one; wiring this to a queue that cannot file the ask would be a
     * parameter with no caller. It is owed and it is written down (6577), not
     * half-built.
     *
     * ⚠️ **REFUSED INSIDE AN IMPERSONATION SESSION FOR THE SAME REASON THE
     * ACCOUNT EXPORT IS** — `28` §9.4's blocklist entry is about one agent
     * pulling a person's record with no second person, and narrowing the subject
     * from an account to a named individual does not make that a different act.
     *
     * @throws ImpersonationRefused when support tries to start
     *                              one from inside the account
     */
    public function requestForContact(
        Business $business,
        Customer $customer,
        User $requester,
        ExportSource $source,
    ): TenantExport {
        return $this->open($business, $requester, $source, $customer, null);
    }

    /**
     * The one writer of `tenant_exports`, reached by both public entry points.
     *
     * ⚠️ **THE SCOPE IS DECIDED HERE, BEFORE ANYTHING IS ASSEMBLED OR SIGNED.**
     * Everything downstream — which rows are read, what the object contains,
     * what the link is minted with, what the download path will accept — follows
     * from the `customer_id` written on this row and from nothing else.
     */
    private function open(
        Business $business,
        User $requester,
        ExportSource $source,
        ?Customer $customer,
        ?int $dataRequestId,
    ): TenantExport {
        $this->impersonation->refuse(ImpersonationCapability::ExportTenantData);

        return Tenancy::actingAs((int) $business->id, function () use ($business, $requester, $source, $customer, $dataRequestId): TenantExport {
            $customerId = $customer instanceof Customer ? (int) $customer->getKey() : null;

            $reusable = $dataRequestId === null ? $this->reusable($customerId) : null;

            if ($reusable instanceof TenantExport) {
                return $reusable;
            }

            $export = new TenantExport;
            $export->forceFill([
                'business_id' => $business->id,
                'requested_by' => $requester->id,
                'customer_id' => $customerId,
                'source' => $source,
                'status' => ExportStatus::Queued,
                'data_request_id' => $dataRequestId,
                'requested_at' => now(),
            ])->save();

            $this->audit->record(
                'export.requested',
                'user:'.$requester->id,
                $export,
                [
                    'source' => $source->value,
                    'data_request_id' => $dataRequestId,
                    // ⚠️ The id and never the name. `29` §2 rule 42's trail has
                    // to say *which* disclosure was made, and an append-only log
                    // is the last place to write a member of the public's name
                    // down a second time (1994's rule about `storage_path`, one
                    // table over).
                    'customer_id' => $customerId,
                ],
            );

            BuildTenantExportJob::dispatch((int) $business->id, (int) $export->id);

            return $export;
        });
    }

    /**
     * The build this tenant already has, when a second click should not start a
     * third assembly. Null when there is nothing worth reusing.
     *
     * Two shapes qualify and the first matters more than the second: a build
     * still **in flight** is reused while it can plausibly still be running,
     * because a queue backed up behind a large tenant is exactly when somebody
     * clicks again. A **ready** build is reused only inside the cool-down, so an
     * owner who genuinely wants today's data after this morning's export still
     * gets a fresh one.
     *
     * ⚠️ **BOTH BRANCHES ARE BOUNDED, AND THE FIRST ONE WAS NOT** (1995). See
     * {@see $this->inFlightReuseMinutes()} for what an unbounded one cost: a
     * `queued` row that never completes blocked every future export on that
     * account permanently, with no age cap, no reset method and no ops path to
     * clear it.
     *
     * ⛔ **BOTH BRANCHES ARE ALSO SCOPED, AND THAT WAS THE LIVE DEFECT THIS
     * SLICE FOUND RATHER THAN A PRECAUTION IT ADDED** (6560). Before the
     * `$customerId` argument existed this method answered *"has this tenant
     * built anything recently"*, and every caller took the answer as *"has this
     * tenant built *this*"*. Add one contact-scoped caller and the two
     * questions come apart in the worst direction: an owner who downloaded the
     * whole account at 10:00 and pressed **Export this customer** at 10:05 is
     * handed the ten-minute-old **whole-account archive**, correctly signed,
     * correctly expiring, audited as a contact export — and forwards it to the
     * customer who asked what is held about them. Every existing test stays
     * green, because the row is real, the link works and the tenant is right.
     * **The reuse cache is where a scoped export leaks an unscoped one**, one
     * layer above the download path where the same defect is easier to imagine.
     *
     * ⚠️ **`whereNull` IS AN ARM RATHER THAN AN OMITTED FILTER**, because
     * `where('customer_id', null)` is SQL `= NULL` and matches nothing — an
     * account export would then never reuse and would silently assemble on
     * every click, which is the opposite failure and an expensive one.
     */
    private function reusable(?int $customerId): ?TenantExport
    {
        $inFlight = $this->inScope($customerId)
            ->where('status', ExportStatus::Queued->value)
            // `requested_at` rather than `created_at`: it is NOT NULL on this
            // table and it is the moment the clock this bound belongs to
            // actually started, which `created_at` only happens to match.
            ->where('requested_at', '>=', now()->subMinutes($this->inFlightReuseMinutes()))
            ->orderByDesc('id')
            ->first();

        if ($inFlight instanceof TenantExport) {
            return $inFlight;
        }

        $recent = $this->inScope($customerId)
            ->where('status', ExportStatus::Ready->value)
            ->where('built_at', '>=', now()->subMinutes($this->requestCooldownMinutes()))
            ->orderByDesc('id')
            ->first();

        // ⚠️ Asked of the row rather than inferred from `built_at`, because a
        // row inside the cool-down whose link has somehow already lapsed is a
        // row this method must not hand back as "you already have one".
        return $recent instanceof TenantExport && $recent->isDownloadable() ? $recent : null;
    }

    /**
     * Rows built for one scope and no other — `null` meaning the whole account.
     *
     * One expression of the predicate, used by every question this class asks
     * about "an export like the one being asked for", so the reuse branch and
     * anything that follows it cannot disagree about what a scope is.
     *
     * @return Builder<TenantExport>
     */
    private function inScope(?int $customerId): Builder
    {
        return TenantExport::query()->when(
            $customerId === null,
            fn (Builder $query): Builder => $query->whereNull('customer_id'),
            fn (Builder $query): Builder => $query->where('customer_id', $customerId),
        );
    }

    /**
     * Assemble the ZIP, upload it, and mark the row ready. Called only by
     * {@see BuildTenantExportJob}, with the tenant already established.
     *
     * ⚠️ **THE READY TRANSITION IS AN ATOMIC CONDITIONAL UPDATE, NOT A PLAIN
     * `save()`.** A retried job — the queue redelivering after a crash between
     * "uploaded" and "marked ready" — rebuilds and re-uploads to the same stable
     * path, which overwrites harmlessly; what must not happen twice is the
     * notification. `WHERE status <> 'ready'` is what makes a second attempt
     * that lands after the first already succeeded a no-op rather than a second
     * email.
     */
    public function build(TenantExport $export): void
    {
        $business = Business::query()->whereKey($export->business_id)->first();

        if (! $business instanceof Business) {
            // The tenant is gone — an export mid-build against an account
            // deleted out from under it. Nothing to notify and nothing to fail
            // loudly about; there is no owner left to reach.
            return;
        }

        if ($business->data_classification === DataClassification::Phi) {
            $this->fail(
                $export,
                'Refused: this account is classified as protected health information. '
                .'`29` §2 rule 24 requires a separate schema, role and KMS key this '
                .'application does not yet have (docs/STAGE-0-GAPS.md), so this export '
                .'engine does not build a ZIP for it rather than assert a protection it '
                .'has not built.',
            );

            return;
        }

        $subject = $this->subjectOf($export);

        if ($export->customer_id !== null && ! $subject instanceof Customer) {
            // ⚠️ **A SCOPED BUILD WITH NO SUBJECT REFUSES RATHER THAN FALLING
            // BACK TO THE ACCOUNT** (6563). The composite foreign key makes this
            // unreachable through any writer in `app/` — the pair has to exist —
            // so what is left is the tenant desynchronised from the session, or
            // a row constructed by hand in a test. Assembling the whole account
            // for a row that asked for one contact is the exact disclosure this
            // slice exists to prevent, and `?? null` here would have been the
            // one line that did it.
            $this->fail(
                $export,
                'Refused: this export names a contact this account cannot read. Nothing was '
                .'assembled, because the alternative to refusing is building the whole account '
                .'for a request that asked for one person.',
            );

            return;
        }

        [$storagePath, $manifest, $byteSize] = $subject instanceof Customer
            ? $this->assembleForContact($business, $subject, $export)
            : $this->assemble($business, $export);

        $expiresAt = now()->addDays(self::LINK_EXPIRY_DAYS);

        $updated = TenantExport::query()
            ->whereKey($export->id)
            ->where('status', '!=', ExportStatus::Ready->value)
            ->update([
                'status' => ExportStatus::Ready->value,
                'storage_path' => $storagePath,
                'byte_size' => $byteSize,
                'manifest' => $manifest,
                'built_at' => now(),
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            // Already marked ready by another attempt. The file this attempt
            // just wrote is the same content at the same path; nothing else to
            // do, and nobody gets a second email.
            return;
        }

        $export->refresh();

        $this->announce($business, $export);
    }

    /**
     * Every retry exhausted, or a refusal this class chose deliberately (PHI).
     *
     * Called by {@see BuildTenantExportJob::failed()} as well as from inside
     * this class, so both paths land on the same row shape.
     */
    public function fail(TenantExport $export, string $reason): void
    {
        TenantExport::query()
            ->whereKey($export->id)
            ->where('status', '!=', ExportStatus::Ready->value)
            ->update([
                'status' => ExportStatus::Failed->value,
                'failed_at' => now(),
                'failure_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    /**
     * The link the owner clicks, or support pastes into a reply — a signed
     * route whose expiry is minted from the row's own `expires_at`, never a
     * fresh `now()->addDays()` computed a second time at link-render time.
     *
     * ⚠️ **A SECOND EXPIRY COMPUTED HERE WOULD DRIFT FROM THE ROW'S** — the row
     * is the record `TenantExport::isDownloadable()` checks server-side at
     * fetch time; this is only what makes the link *itself* stop resolving at
     * the same moment, which is Laravel's signature check rather than the
     * enforcement that actually matters.
     */
    public function downloadUrl(TenantExport $export): ?string
    {
        if (! $export->isDownloadable() || $export->expires_at === null) {
            return null;
        }

        $parameters = ['export' => $export->id];

        if ($export->customer_id !== null) {
            // ⚠️ **THE SUBJECT TRAVELS IN THE LINK *AS WELL AS* ON THE ROW, AND
            // THE POINT IS THE COMPARISON RATHER THAN THE PARAMETER** (6565).
            // Nothing downstream reads this to decide what to serve — the row's
            // `storage_path` does that, and it always will. What the controller
            // does with it is assert the two agree, which turns a *minting*
            // mistake here into a 404 instead of a disclosure: an id typed into
            // the wrong slot produces a link that says "customer A" over an
            // object that is the whole account, and that link now resolves to
            // nothing. Laravel's own signature covers the query string, so this
            // cannot be edited by whoever holds the URL.
            $parameters['subject'] = $export->customer_id;
        }

        return URL::temporarySignedRoute(
            'account.data-export.download',
            $export->expires_at,
            $parameters,
        );
    }

    /**
     * The most recent export this tenant has, for the two screens that show its
     * status — `Account\Settings` and the suspended-account page.
     *
     * Here rather than on either screen so that `CrmTest.php`'s chokepoint keeps
     * its meaning: every read of `tenant_exports` goes through this class, the
     * job, the download controller or the ops queue, and a third screen wanting
     * export status grows this method's caller list rather than the allowlist.
     *
     * ⚠️ **NOT `latest()`, AND `ConventionsTest`'s NULLS-FIRST LINT IS WHY.**
     * That name is Laravel's own ordering helper, so `$exports->latest()` at a
     * call site is indistinguishable from `->latest()` on a query builder — to a
     * reader and to the lint, which reddened on both screens. The lint was right
     * about the ambiguity even though it was wrong about this call: a service
     * method should not borrow the spelling of the thing it is not.
     */
    public function mostRecent(): ?TenantExport
    {
        // ⚠️ **STILL EVERY ROW, INCLUDING THE CONTACT-SCOPED ONES, AND THAT IS
        // NOT AN OVERSIGHT** (6566). Its two callers are the account screen and
        // the on-hold page, and both ask *"what is the state of my download"* —
        // an owner who exported one contact five minutes ago has a download, and
        // hiding it there would show *"you have no download"* beside a link they
        // were just emailed. What the screens must not do is offer a
        // contact-scoped row **as** the account archive, which is why the panel
        // reads the row's own scope rather than assuming.
        return TenantExport::query()->latest('id')->first();
    }

    /**
     * The most recent export built for one contact — the profile panel's row.
     *
     * ⚠️ **SCOPED, WHERE {@see self::mostRecent()} IS NOT**, and the asymmetry is
     * the disclosure asymmetry: an account panel showing a narrower build is a
     * cosmetic wrong, and a contact panel showing a *wider* one is a link to the
     * whole account offered under a named person's heading. The screens are the
     * last place that mistake can be made and the first place it is noticed.
     */
    public function mostRecentForContact(Customer $customer): ?TenantExport
    {
        return $this->inScope((int) $customer->getKey())->latest('id')->first();
    }

    /**
     * How many export objects this tenant has on the disk, and how big they are
     * — {@see App\Services\Storage\StorageFootprint}'s export line (4770).
     *
     * ⛔ **HERE, AND ANSWERING THREE INTEGERS RATHER THAN A MODEL OR A BUILDER,
     * BECAUSE THE FOOTPRINT REDDENED `CrmTest`'s CHOKEPOINT AND THE LINT WAS
     * RIGHT.** `StorageFootprint` originally read `TenantExport::query()`
     * directly, which is a sixth reader of `tenant_exports` and exactly what
     * that lint exists to refuse. Adding it to the allowlist was the wrong fix:
     * 1911 already moved this shape the other way, taking the list from six to
     * five by giving two screens `mostRecent()` — *"the lint got narrower while
     * the feature got wider, which is the direction this shape is supposed to
     * move in"*. **A builder would have been the same defect with a longer
     * reach**, since the model would still escape this class; three integers
     * cannot be turned back into a row.
     *
     * ⚠️ **`storage_path`, NOT `status`.** `ExportStatus::Ready` is what a screen
     * asks; what a footprint asks is whether an object exists, and those come
     * apart in both directions — a `failed` row can name an object that was
     * written before the failure, and `purgeExpired()` clears the path while the
     * row survives long enough to be counted.
     *
     * ⚠️ **A NULL `byte_size` IS COUNTED AND ITS BYTES ARE NOT.** Same rule as
     * every other kind: an unknown size is never a zero.
     *
     * Runs inside an established tenant; the global scope and RLS do the rest.
     *
     * @return array{objects: int, bytes: int, unmeasured: int}
     */
    public function storedObjectTotals(): array
    {
        return [
            'objects' => TenantExport::query()->whereNotNull('storage_path')->count(),
            'bytes' => (int) TenantExport::query()->whereNotNull('storage_path')->sum('byte_size'),
            'unmeasured' => TenantExport::query()
                ->whereNotNull('storage_path')
                ->whereNull('byte_size')
                ->count(),
        ];
    }

    /**
     * Delete every object whose row has passed its seven days, and the rows with
     * them. Runs inside an established tenant.
     *
     * ⚠️ **THE OBJECT GOES FIRST AND THE ROW ONLY IF IT WENT** (1902). The row
     * is the sole record of where the object is — `storage_path` is not derivable
     * from anything else once `APP_KEY` has rotated — so deleting the row first,
     * or deleting it anyway after a failed R2 call, produces precisely the
     * unreferenced plaintext copy of an account that this sweep exists to
     * prevent. A failure here leaves both in place for tomorrow's run.
     *
     * ⚠️ **IT RETURNS BOTH NUMBERS, AND THE SECOND IS THE ONE THAT WAS MISSING**
     * (1993). Returning only the count removed made an unreachable bucket
     * indistinguishable from an empty queue, and `exports:prune` printed *"No
     * expired exports to prune."* — not merely silent but **false**, on exactly
     * the run where a seven-day retention promise was failing for every tenant
     * at once. {@see self::deleteObject()} swallows its `Throwable` deliberately
     * (one bucket must not abandon the sweep), so this loop is the only place
     * the refusal can be counted at all.
     *
     * ⚠️ **THE ROW IS DELETED RATHER THAN BLANKED**, and the CHECK constraint
     * `tenant_exports_ready_is_whole` is why it cannot be blanked: a `ready` row
     * must carry a path.
     *
     * ⚠️ **AND THE DESTRUCTION IS AUDITED, WHICH `export.built` DID NOT COVER**
     * (1994). That entry proves *what left the building*, and it was offered as
     * the reason nothing was lost by deleting the row — but the question a
     * regulator asks about a retention policy is the other one, *prove the copy
     * was destroyed on day seven*, and no entry answered it. `export.purged`
     * does. ⚠️ **`storage_path` IS DELIBERATELY NOT IN THE METADATA**: the row
     * is being removed precisely so that nothing keeps naming where a plaintext
     * copy of an account used to sit, and an append-only log is the worst
     * possible place to write it back down.
     *
     * @return array{0: int, 1: int} how many were removed, and how many the
     *                               object store refused to give up
     */
    public function purgeExpired(): array
    {
        $purged = 0;
        $refused = 0;

        TenantExport::query()
            ->whereNotNull('storage_path')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(self::CHUNK, function (Collection $expired) use (&$purged, &$refused): void {
                foreach ($expired as $export) {
                    /** @var TenantExport $export */
                    if (! $this->deleteObject((string) $export->storage_path)) {
                        $refused++;

                        continue;
                    }

                    $exportId = (int) $export->id;
                    $byteSize = $export->byte_size;
                    $expiredAt = $export->expires_at?->toIso8601String();

                    $export->delete();
                    $purged++;

                    $this->audit->record('export.purged', 'system', null, [
                        'tenant_export_id' => $exportId,
                        'byte_size' => $byteSize,
                        'expired_at' => $expiredAt,
                        'retention_days' => self::LINK_EXPIRY_DAYS,
                    ]);
                }
            });

        [$orphaned, $stuck] = $this->purgeOrphans();

        return [$purged + $orphaned, $refused + $stuck];
    }

    /**
     * The objects no row names, swept on the same seven-day clock as the ones
     * that do.
     *
     * ⚠️ **THE SELECT ABOVE CANNOT SEE THESE, AND THAT IS THE WHOLE DEFECT**
     * (2042). `purgeExpired()` reads `whereNotNull('storage_path')` — but 1990
     * established that an object lands on R2 while the path stays null, twice
     * over: {@see self::upload()} throws after `writeStream()` succeeded and
     * before the row update (1903), and a worker SIGKILLed between the two
     * leaves the same state (1995, *"on the production box's database queue
     * driver that is not exotic"*). 1990 fixed the **erasure** path for that
     * state and left the **retention** path exactly as it was. So a plaintext
     * ZIP of every contact's name, email, phone and consent state sat on R2
     * **indefinitely** — not seven days, not seven months — for any account
     * that is never deleted, which is every account that stays a customer.
     *
     * ⚠️ **2007(b) SAID THESE ROWS WERE "INERT" AND THE ROWS ARE — THE OBJECTS
     * ARE NOT.** Decision 2002's own `1990 × 1995` paragraph says a `queued` row
     * is exactly the state where an object may have landed, and then does not
     * carry that sentence one method across. This is that sentence, executed.
     *
     * ⚠️ **THE PREFIX IS LISTED ONLY WHEN A PATHLESS ROW IS OLDER THAN THE
     * WINDOW**, so an account whose exports all succeeded never pays for an R2
     * LIST, and one that has never exported at all has no rows to match. The
     * live paths are subtracted rather than assumed absent: a tenant with one
     * good download inside its window and one failed build must keep the first.
     *
     * ⚠️ **THE STALE ROW IS LEFT ALONE.** Deleting it would remove the only
     * signal that brings this sweep back tomorrow if the bucket refused today,
     * and a `failed` row carrying a `data_request_id` is a record the §9.5 queue
     * still reads. The cost is one LIST per night per affected tenant, which is
     * written down here rather than optimised away on a guess (2043).
     *
     * ⚠️ **NO PATH REACHES THE AUDIT ENTRY** — 1994's rule, for the same reason:
     * the objects are being destroyed precisely so nothing keeps naming where a
     * plaintext copy of an account used to sit. The count is what proves the
     * destruction.
     *
     * @return array{0: int, 1: int} how many were removed, and how many the
     *                               object store refused to give up
     */
    private function purgeOrphans(): array
    {
        $pathless = TenantExport::query()
            ->whereNull('storage_path')
            ->where('requested_at', '<', now()->subDays(self::LINK_EXPIRY_DAYS))
            ->exists();

        if (! $pathless) {
            return [0, 0];
        }

        try {
            $files = Storage::disk('s3')->allFiles('exports/'.Tenancy::idOrFail());
        } catch (Throwable) {
            // Counted as a refusal for the same reason `deleteObject()` is: the
            // sweep walks every tenant, and one unreachable bucket must not
            // abandon the rest — but a run that could not look must not report
            // a clean one either.
            return [0, 1];
        }

        $named = TenantExport::query()
            ->whereNotNull('storage_path')
            ->pluck('storage_path')
            ->all();

        $purged = 0;
        $refused = 0;

        foreach (array_diff($files, $named) as $orphan) {
            if (! $this->deleteObject((string) $orphan)) {
                $refused++;

                continue;
            }

            $purged++;
        }

        if ($purged > 0) {
            $this->audit->record('export.orphans_purged', 'system', null, [
                'count' => $purged,
                'retention_days' => self::LINK_EXPIRY_DAYS,
            ]);
        }

        return [$purged, $refused];
    }

    /**
     * Everything this tenant has on R2, gone. Called by
     * {@see TenantDeletion::execute()} **before** the account is destroyed.
     *
     * ⚠️ **THE WHOLE PREFIX, NOT THE PATHS THE ROWS NAME.** An `APP_KEY` rotation
     * between two attempts of one build leaves an object no row points at (see
     * {@see self::storagePathFor()}), and an offboard is the last moment anything
     * will ever look. `deleteDirectory()` catches those; a row-driven sweep
     * cannot.
     *
     * ⚠️ **R2 IS NOT TOUCHED AT ALL WHEN THIS TENANT HAS NEVER ASKED FOR AN
     * EXPORT**, and that guard is load-bearing rather than an optimisation:
     * `TenantDeletion` refuses the whole deletion when this returns false, so
     * destroying an ordinary account that never pressed the button must not be
     * able to fail on an object-store call it had no reason to make.
     *
     * ⚠️ **THE GUARD ASKS THE AUDIT LOG, NOT `tenant_exports`** (2040), and the
     * difference is that one of those two is deletable and the other is not.
     * The guard has now been wrong twice in the same direction, each time by
     * resting on a signal something else removes:
     *
     *   - `whereNotNull('storage_path')` was the first spelling. It re-created,
     *     in the guard, exactly the row-driven sweep the paragraph above says
     *     cannot catch an orphan: {@see self::upload()} throws *before* the row
     *     update whenever `writeStream()` lands an object and `exists()` then
     *     answers false, so a failed build leaves the object on R2 with
     *     `storage_path` still null and no row naming anything (1990).
     *   - `TenantExport::query()->exists()` was the second, and it is the one
     *     this replaces. Rows are not permanent: {@see self::purgeExpired()}
     *     **deletes** them on day seven, by design and nightly. So an account
     *     whose last export row has been pruned reaches its own erasure with
     *     `exists()` false, this method returns true **without a single R2
     *     call**, the cascade reports `Destroyed`, and an orphan from an
     *     `APP_KEY` rotation survives with the row, the tenant and every trace
     *     of it gone. The guard was defeated by this class's own retention
     *     sweep.
     *
     * `audit_log` is the durable answer to *"did this account ever ask for an
     * export"*: `AuditLogEntry` refuses updates and deletes at the model layer
     * (DATA-MODEL §5.14), {@see self::purgeExpired()} removes the export row and
     * never the entry, and `export.requested` is written by {@see self::request()}
     * before anything is dispatched — so it exists for a build that failed, for
     * one that was pruned, and for one still queued. It is read under the
     * tenant's own scope, which {@see TenantDeletion::execute()} has already
     * established.
     *
     * The load-bearing half is unchanged: a tenant who never pressed the button
     * has no `export.requested` entry, so R2 is still never called on an
     * ordinary offboard.
     *
     * ⚠️ **THE RESIDUAL, STATED RATHER THAN CLAIMED AWAY.** A build whose
     * object lands *after* this method has run but before the cascade commits
     * still orphans: the sweep has finished, the row is about to go, and
     * nothing looks again. It needs a build in flight at the instant of an
     * offboard. Closing it means refusing the deletion while any export row is
     * `queued`, which trades a permanent orphan for a deletion queue an
     * unfinished build can block indefinitely (`28` §9.5's clock), and that
     * trade is not this slice's to make. ⚠️ **2007 called that "the residual"
     * and it was not the only one** — the guard defeat above needed no
     * concurrency at all, and a completeness claim is exactly the kind that
     * should not be made from the inside (2041).
     *
     * ⛔ **THE DISK IS RESOLVED OUTSIDE THE `try` AND THAT IS THE WHOLE OF
     * DECISION 9426 — A DISK THAT CANNOT BE BUILT IS NOT A BUCKET THAT
     * REFUSED.** It was inside until 2026-08-25, so every fault this method
     * could meet became one value, and the two have opposite remedies and
     * opposite blast radii:
     *
     *   - **the bucket refused** — one account's prefix is unreachable right
     *     now. `false`, {@see TenantDeletionOutcome::ObjectStoreRefused}, the
     *     next account in tonight's sweep gets its erasure. Correct, unchanged,
     *     and still what `allFiles()` throwing produces.
     *   - **the disk cannot be constructed** — `config/filesystems.php` names a
     *     driver that is not installed, or carries a value the S3 client
     *     rejects (`AWS_REQUEST_CHECKSUM_CALCULATION` is validated at
     *     construction and raises `InvalidArgumentException`). That is true for
     *     **every** account, for ever, and it is a deployment fault with a
     *     one-line fix.
     *
     * ⛔ **AND `ObjectStoreRefused` RINGS NOTHING.** `ExecuteTenantDeletions`
     * counts a deferral, prints one `warn()` line and rings only on a `catch` —
     * so a global fault reported as a refusal stops every statutory erasure on
     * the platform against `28` §9.5's clock, and the only trace is console
     * output from a scheduled command. Raising reaches that `catch`, which
     * stops at this request, logs the exception class and rings
     * [[\App\Enums\OperatorAlertKind::TenantErasureFailed]].
     *
     * ⚠️ **THIS IS NOT A REVERSAL OF 823 AND THE SENTENCE IN THE CATCH IS
     * UNTOUCHED.** 823's rule is that one unreachable *bucket* must not abandon
     * the queue, and it is exactly as true afterwards; what changed is that a
     * broken *deployment* no longer wears that answer.
     * [[\App\Services\Warehouse\ObjectStoreL0Archive::purgeFor]] and
     * [[\App\Services\Campaigns\CampaignMedia::purgeAllFor]] already had
     * this shape, the second with its own argument written down.
     *
     * ⛔ **{@see self::deleteObject()} IS DELIBERATELY LEFT ALONE AND IT IS THE
     * MORE INTERESTING HALF.** The same edit there would be wrong: its `false`
     * has a reader — `purgeExpired()` counts it into `$refused` and
     * `exports:prune` prints it, which is decision 1993, whose whole finding is
     * that a run which could not look must not report a clean one. **A
     * swallowed failure with a counter and a printed line is not the same
     * defect as a swallowed failure with neither**, and the symmetric-looking
     * repair would abandon a sweep its own comment argues must continue.
     *
     * @return bool false when anything may still be there, which refuses the
     *              deletion rather than completing it with the file surviving
     *
     * @throws Throwable when the disk itself cannot be constructed — see above
     */
    public function purgeAllFor(Business $business): bool
    {
        if (! $this->everAskedForAnExport()) {
            return true;
        }

        $prefix = "exports/{$business->id}";

        $disk = Storage::disk('s3');

        try {
            $disk->deleteDirectory($prefix);

            return $disk->allFiles($prefix) === [];
        } catch (Throwable) {
            // ⚠️ Refuses rather than throws, decision 823's rule: this is called
            // from a sweep that walks every due deletion, and one unreachable
            // bucket must not abandon the rest of the queue.
            return false;
        }
    }

    /**
     * Did this account ever ask for an export — answered from the one record
     * that outlives every other trace of one.
     *
     * ⚠️ **TWO SIGNALS, AND THE ANSWER IS EITHER OF THEM** (2040). Each covers
     * the case the other misses, and taking one alone is how this guard has now
     * been wrong twice:
     *
     *   - **`audit_log`** answers for an account whose rows have been pruned.
     *     It is append-only at the model layer (DATA-MODEL §5.14), carries no FK
     *     to `tenant_exports`, is written by {@see self::request()} before
     *     anything is dispatched, and nothing in this application deletes it.
     *   - **`tenant_exports`** answers for a row that exists without one. That
     *     is not reachable through `request()` — it writes the entry first — but
     *     it *is* reachable, and `TenantDeletionTest`'s 1990 case constructs
     *     exactly that row directly to drive the orphan sweep. ⚠️ **Dropping
     *     this half reddens that test**, which is how the narrower version was
     *     caught: a guard rewritten to fix one blind spot had quietly opened
     *     the one the previous wave closed.
     *
     * So the sweep is skipped only when **neither** says an export was ever
     * attempted, which is strictly narrower than either spelling that came
     * before it and keeps 1902's load-bearing half intact: an account that never
     * pressed the button still never calls R2.
     *
     * ⚠️ **The audit read goes through {@see AuditService::everRecorded()}
     * rather than the model** — `Architecture\ConsentTest` holds `AuditLogEntry`
     * to two services and caught this class reaching for it directly (2047).
     */
    private function everAskedForAnExport(): bool
    {
        return $this->audit->everRecorded('export.requested')
            || TenantExport::query()->exists();
    }

    /**
     * One object, gone — or false, said plainly rather than swallowed.
     *
     * `'throw' => false` on this disk means `delete()` answers with a boolean,
     * and {@see self::upload()}'s docblock records what ignoring one of those
     * costs. `exists()` is asked afterwards for the same reason it is asked
     * there.
     */
    private function deleteObject(string $path): bool
    {
        try {
            Storage::disk('s3')->delete($path);

            return ! Storage::disk('s3')->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * ⚠️ **NOTHING HERE HOLDS A WHOLE DATASET, AND NOTHING RETURNS UNTIL THE
     * OBJECT IS PROVABLY ON R2** (1903, 1908). Every CSV is written row by row
     * to its own temporary file, the ZIP is uploaded as a stream, and the
     * temporary files are removed in a `finally` so a throw on the way out does
     * not leave a plaintext copy of the account in `/tmp`.
     *
     * @return array{0: string, 1: array<string, mixed>, 2: int}
     */
    private function assemble(Business $business, TenantExport $export): array
    {
        $included = [];
        $excluded = [];
        $parts = [];

        $zipPath = $this->tempFile();

        try {
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $parts['contacts.csv'] = $this->contactsCsv();
            $parts['reviews.csv'] = $this->reviewsCsv();
            $parts['messages_outbound_only.csv'] = $this->messagesCsv();

            $included[] = [
                'file' => 'contacts.csv',
                'rows' => $parts['contacts.csv'][1],
                'note' => 'Every contact this account has, including archived and merged rows.',
            ];

            $included[] = [
                'file' => 'reviews.csv',
                'rows' => $parts['reviews.csv'][1],
                'note' => 'First-party and synced Google reviews, with the latest reply on each.',
            ];

            $included[] = [
                'file' => 'messages_outbound_only.csv',
                'rows' => $parts['messages_outbound_only.csv'][1],
                'note' => 'Messages sent to your contacts only. Outbound only — this application '
                    .'has no inbound message store yet (decision 941), and business-level alerts '
                    .'with no linked contact are not covered by this file.',
            ];

            foreach ($parts as $name => [$path]) {
                $zip->addFile($path, $name);
            }

            $excluded[] = [
                'file' => 'content/',
                'reason' => 'No published-content store exists in this application yet.',
            ];

            $excluded[] = [
                'file' => 'reports/',
                'reason' => '`28` Part 7\'s report suite is not built yet.',
            ];

            $manifest = [
                'account' => (string) $business->id,
                'generated_at' => now()->toIso8601String(),
                'included' => $included,
                'excluded' => $excluded,
                // ⚠️ Named in the manifest because `28` §3.7 forbids a degraded
                // export, so this file has to be able to say *"nothing was left
                // out for size"* rather than leave a reader to assume it.
                'limits' => 'No row or byte cap applies to this export — `28` §3.7 '
                    .'forbids a degraded one. Repeat requests inside '
                    .$this->requestCooldownMinutes().' minutes reuse this build rather than '
                    .'starting another.',
            ];

            $zip->addFromString('manifest.json', (string) json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ));

            $zip->close();

            $byteSize = (int) filesize($zipPath);
            $storagePath = $this->storagePathFor($business, $export);

            $this->upload($zipPath, $storagePath);

            return [$storagePath, $manifest, $byteSize];
        } finally {
            foreach ($parts as [$path]) {
                @unlink($path);
            }

            @unlink($zipPath);
        }
    }

    /**
     * `44` §10's per-contact ZIP: *"profile + custom fields CSV · full timeline
     * CSV · consent audit with verbatim notice snapshots · files"* — three of
     * those four, with the fourth refused by name in the manifest.
     *
     * ## ⚠️ IT CONTAINS NOTHING THE PROFILE SCREEN DOES NOT ALREADY SHOW
     *
     * Every row here comes from {@see CustomerTimeline},
     * {@see ConsentService::proofFor()} or the contact row itself — the three
     * things `Account\CustomerProfile` renders. That is the property that makes
     * this defensible rather than merely convenient: **building one discloses
     * nothing new to the tenant**, so the whole question is what leaves the
     * tenant's hands afterwards, and the manifest is written for the person who
     * has to answer it.
     *
     * ## ⚠️ THE MERGED ROWS ARE IN IT, AND THAT IS A DISCLOSURE DECISION (6567)
     *
     * A contact folded into this one by {@see CustomerMerges::merge()} keeps its
     * own row, its own name, email and phone, and its own consent records —
     * nothing is rewritten, because rewriting `consent_records.customer_id`
     * would restate *who* consented. Those rows are stored personal data about
     * the subject on the tenant's own assertion that the two are one person, so
     * leaving them out would answer *"everything you hold about me"* with a
     * file that omits a whole second record of them.
     *
     * ⛔ **AND IF THAT ASSERTION WAS WRONG, THIS FILE CONTAINS SOMEBODY ELSE.**
     * A merge is a judgement about two rows that looked alike; the manifest
     * names every folded id and says so in the tenant's own words, because the
     * only party who can check it is the one about to press send.
     *
     * @return array{0: string, 1: array<string, mixed>, 2: int}
     */
    private function assembleForContact(Business $business, Customer $customer, TenantExport $export): array
    {
        $folded = $this->merges->mergedInto($customer);

        // ⚠️ A SUPPORT COLLECTION AND NOT AN ELOQUENT ONE — `concat()` on a
        // plain `collect()` returns the base class, and the two are not
        // interchangeable in a parameter type. Larastan level 8 did not catch
        // the mismatch; the first run did.
        /** @var SupportCollection<int, Customer> $people */
        $people = collect([$customer])->concat($folded);

        $included = [];
        $parts = [];

        $zipPath = $this->tempFile();

        try {
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $parts['contact.csv'] = $this->contactCsv($people, (int) $customer->getKey());
            $parts['timeline.csv'] = $this->timelineCsv($customer);
            $parts['consent.csv'] = $this->consentCsv($business, $people);

            $included[] = [
                'file' => 'contact.csv',
                'rows' => $parts['contact.csv'][1],
                'note' => 'This contact\'s own record, including their custom fields, and a row for '
                    .'every other contact record folded into it by a merge.',
            ];

            $included[] = [
                'file' => 'timeline.csv',
                'rows' => $parts['timeline.csv'][1],
                'note' => 'Their history in one stream — reviews they left, messages sent to them, '
                    .'consent events, notes about them, follow-ups, and links they opened. '
                    .'Messages are outbound only: this application has no inbound message '
                    .'store (decision 941).',
            ];

            $included[] = [
                'file' => 'consent.csv',
                'rows' => $parts['consent.csv'][1],
                'note' => 'Every consent they gave and every withdrawal, with the wording version '
                    .'each was captured against and the words themselves where this '
                    .'application can still produce them.',
            ];

            foreach ($parts as $name => [$path]) {
                $zip->addFile($path, $name);
            }

            $manifest = [
                'account' => (string) $business->id,
                'generated_at' => now()->toIso8601String(),
                // The id, and never the name: a manifest is stored on the row as
                // well as shipped in the file, and `tenant_exports.manifest` is
                // not a place to put a member of the public's name a second time.
                'scope' => [
                    'contact_id' => (int) $customer->getKey(),
                    'merged_contact_ids' => $folded
                        ->map(fn (Customer $person): int => (int) $person->getKey())
                        ->values()
                        ->all(),
                ],
                'included' => $included,
                'excluded' => self::CONTACT_EXCLUSIONS,
                'limits' => 'No row or byte cap applies to this export — `28` §3.7 forbids a '
                    .'degraded one. Repeat requests for this contact inside '
                    .$this->requestCooldownMinutes().' minutes reuse this build rather than '
                    .'starting another; an export of the whole account is a separate build '
                    .'and is never handed back in its place.',
                'read_before_you_forward' => self::CONTACT_FORWARDING_NOTES,
            ];

            $zip->addFromString('manifest.json', (string) json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ));

            $zip->close();

            $byteSize = (int) filesize($zipPath);
            $storagePath = $this->storagePathFor($business, $export);

            $this->upload($zipPath, $storagePath);

            return [$storagePath, $manifest, $byteSize];
        } finally {
            foreach ($parts as [$path]) {
                @unlink($path);
            }

            @unlink($zipPath);
        }
    }

    /**
     * The contact's own stored record, and every record merged into it.
     *
     * ⚠️ **`custom_fields` GOES OUT AS JSON RATHER THAN AS COLUMNS**, because it
     * is a free-form `jsonb` bag with no schema — a column per key would differ
     * between two contacts in the same file and read as missing data on the one
     * with fewer keys.
     *
     * @param  SupportCollection<int, Customer>  $people
     * @return array{0: string, 1: int} the temporary file, and its row count
     */
    private function contactCsv(SupportCollection $people, int $subjectId): array
    {
        return $this->toCsvFile(
            ['id', 'record', 'name', 'email', 'phone', 'tags', 'custom_fields', 'sms_consent',
                'email_consent', 'messaging_lane', 'region_code', 'archived_at', 'deleted_at',
                'merged_into_id', 'first_seen_at', 'last_activity_at', 'created_at'],
            function (callable $write) use ($people, $subjectId): void {
                foreach ($people as $person) {
                    $write([
                        $person->id,
                        (int) $person->getKey() === $subjectId
                            ? 'this contact'
                            : 'merged into this contact',
                        $person->name,
                        $person->email,
                        $person->phone,
                        implode('|', $person->tags ?? []),
                        (string) json_encode($person->custom_fields ?? [], JSON_THROW_ON_ERROR),
                        $person->sms_consent ? 'yes' : 'no',
                        $person->email_consent ? 'yes' : 'no',
                        $person->messaging_lane->value,
                        $person->region_code,
                        $person->archived_at?->toIso8601String(),
                        $person->deleted_at?->toIso8601String(),
                        $person->merged_into_id,
                        $person->first_seen_at?->toIso8601String(),
                        $person->last_activity_at?->toIso8601String(),
                        $person->created_at?->toIso8601String(),
                    ]);
                }
            },
        );
    }

    /**
     * `44` §10's *"full timeline CSV"* — {@see CustomerTimeline::forCustomer()}
     * verbatim, which is the same union the profile screen renders and already
     * walks the merged ids itself.
     *
     * ⚠️ **NOT A SECOND UNION WRITTEN HERE** (624's rule). Six stores feed that
     * method, four of them behind chokepoint lints, and an export assembling its
     * own version of a contact's history would be a second answer to *"what
     * happened to this person"* — the two would disagree on the one document
     * where being wrong is expensive, and the export's copy is the one nobody
     * looks at until a regulator does.
     *
     * @return array{0: string, 1: int} the temporary file, and its row count
     */
    private function timelineCsv(Customer $customer): array
    {
        return $this->toCsvFile(
            ['occurred_at', 'what', 'summary', 'detail', 'recorded_by'],
            function (callable $write) use ($customer): void {
                foreach ($this->timeline->forCustomer($customer) as $entry) {
                    /** @var TimelineEntry $entry */
                    $write([
                        $entry->occurredAt?->toIso8601String(),
                        $entry->type->value,
                        $entry->headline,
                        $entry->detail,
                        $entry->actor,
                    ]);
                }
            },
        );
    }

    /**
     * `44` §10's consent audit *"with verbatim notice snapshots"* —
     * {@see ConsentService::proofFor()}, which is both tables and therefore both
     * directions: a trail of grants with no withdrawal in it is a document in
     * which every row is true and the whole is false.
     *
     * ⚠️ **`notice_text` IS RESOLVED THROUGH {@see ConsentDisclosure::textForVersion()}
     * AND IS OFTEN EMPTY, WHICH IS THE HONEST SHAPE** (6570). That method
     * answers for the two wordings it holds and null for everything else — an
     * import attestation's statement version, a superseded wording — and the
     * `notice_text` cell then carries a sentence naming the version rather than
     * a plausible substitute. Printing today's words against a version they do
     * not belong to would put a false statement in the one document that exists
     * to be evidence.
     *
     * ⚠️ **THE ROWS ARE WALKED PER PERSON RATHER THAN ONCE.** `proofFor()` is a
     * per-contact method, the merged records keep their own consent rows, and
     * `contact_id` is the column that says which record each grant was made
     * against — which is exactly the question a reader of a merged file has.
     *
     * @param  SupportCollection<int, Customer>  $people
     * @return array{0: string, 1: int} the temporary file, and its row count
     */
    private function consentCsv(Business $business, SupportCollection $people): array
    {
        return $this->toCsvFile(
            ['contact_id', 'event', 'channel', 'occurred_at', 'captured_by', 'capture_surface',
                'consent_type', 'method', 'disclosure_version', 'notice_text', 'proof',
                'identifier', 'withdrawal_reason', 'withdrawal_class'],
            function (callable $write) use ($business, $people): void {
                foreach ($people as $person) {
                    foreach ($this->consent->proofFor($person) as $entry) {
                        /** @var ConsentTrailEntry $entry */
                        $record = $entry->consentRecord;
                        $withdrawal = $entry->withdrawal;

                        $version = $record?->disclosure_version;

                        $write([
                            $person->id,
                            $entry->type->value,
                            $entry->channel->value,
                            $entry->occurredAt?->toIso8601String(),
                            $record?->captured_by->value,
                            $record?->capture_surface->value,
                            $record?->consent_type?->value,
                            $record?->method,
                            $version,
                            $version === null
                                ? null
                                : (ConsentDisclosure::textForVersion($version, (string) $business->name)
                                    ?? 'The wording for version "'.$version.'" is not held in this '
                                        .'application, so it is named here rather than reproduced.'),
                            $record === null
                                ? null
                                : (string) json_encode($record->proof ?? [], JSON_THROW_ON_ERROR),
                            $withdrawal?->identifier,
                            $withdrawal?->reason,
                            $withdrawal?->reason_class->value,
                        ]);
                    }
                }
            },
        );
    }

    /**
     * Where this export's object lives, stably across every retry of the same
     * build (N2, 1906).
     *
     * ⚠️ **THE TOKEN IS DERIVED, NEVER RANDOM PER CALL.** `exports/{id}/{id}.zip`
     * was guessable by anyone who could count, which made the object's safety a
     * property of a bucket setting no test in this repository can assert. A
     * random segment would fix that and break something load-bearing:
     * {@see BuildTenantExportJob}'s idempotency rests on a redelivered build
     * re-uploading to the **same** key (decision 1829), and `Str::random()` would
     * scatter an orphan per retry. An HMAC over the row id keyed on `APP_KEY` is
     * unguessable without the key and identical on every attempt.
     *
     * ⚠️ **Rotating `APP_KEY` between two attempts of one build orphans the
     * first attempt's object**, which is the honest limit of this: nothing else
     * would ever notice, so {@see self::purgeAllFor()} deletes the tenant's whole
     * prefix rather than the paths it can name.
     */
    private function storagePathFor(Business $business, TenantExport $export): string
    {
        // ⚠️ **A CONTACT EXPORT HMACs A DIFFERENT SUBJECT, AND THE ACCOUNT
        // EXPORT'S IS UNCHANGED** (6564). Row ids are unique across both kinds,
        // so no path could ever collide and this buys no uniqueness. What it
        // buys is that the two token spaces do not overlap: a token computed for
        // an account archive can never address a contact object or the reverse,
        // whatever a later caller passes. Touching the account spelling would
        // have orphaned every object already on R2 whose row names it.
        $subject = $export->customer_id === null
            ? 'tenant-export:'.$export->id
            : 'contact-export:'.$export->id.':'.$export->customer_id;

        $token = mb_substr(
            hash_hmac('sha256', $subject, (string) config('app.key')),
            0,
            32,
        );

        return "exports/{$business->id}/{$token}/{$export->id}.zip";
    }

    /**
     * Put the ZIP on R2, or throw.
     *
     * ⚠️ **`config/filesystems.php` SETS `'throw' => false` ON THIS DISK, SO A
     * FAILED UPLOAD IS A RETURN VALUE AND NOT AN EXCEPTION** (1903). Ignoring it
     * is how a build with no file marked itself `ready`, closed a statutory
     * subject-access request as `fulfilled`, and emailed the owner a link to a
     * 500 — `$byteSize` came from the local temp file, so even the size read
     * correctly. Throwing here hands the failure to {@see BuildTenantExportJob}'s
     * three-attempt ladder, which is what that ladder is for.
     *
     * Both signals are checked. `put()` returning false is the documented one;
     * `exists()` is the one that catches a driver reporting success on a write
     * that did not land, which is the shape a swallowed multipart failure takes.
     */
    private function upload(string $localPath, string $storagePath): void
    {
        $handle = fopen($localPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Could not read the assembled export back to upload it.');
        }

        try {
            // `writeStream()` rather than `put()`, so the whole ZIP is never a
            // PHP string — that `file_get_contents()` was the third
            // whole-dataset copy this method used to make.
            $written = Storage::disk('s3')->writeStream($storagePath, $handle);
        } finally {
            fclose($handle);
        }

        if ($written === false || ! Storage::disk('s3')->exists($storagePath)) {
            throw new \RuntimeException(
                'The export was assembled but could not be stored — nothing was uploaded to '
                .$storagePath.'. The row stays unbuilt rather than promising a file that is not there.'
            );
        }
    }

    /**
     * @return array{0: string, 1: int} the temporary file, and its row count
     */
    private function contactsCsv(): array
    {
        return $this->toCsvFile(
            ['id', 'name', 'email', 'phone', 'tags', 'sms_consent', 'email_consent',
                'messaging_lane', 'region_code', 'archived_at', 'deleted_at',
                'merged_into_id', 'first_seen_at', 'last_activity_at', 'created_at'],
            function (callable $write): void {
                Customer::query()->orderBy('id')->chunkById(self::CHUNK, function (Collection $contacts) use ($write): void {
                    foreach ($contacts as $customer) {
                        $write([
                            $customer->id,
                            $customer->name,
                            $customer->email,
                            $customer->phone,
                            implode('|', $customer->tags ?? []),
                            $customer->sms_consent ? 'yes' : 'no',
                            $customer->email_consent ? 'yes' : 'no',
                            $customer->messaging_lane->value,
                            $customer->region_code,
                            $customer->archived_at?->toIso8601String(),
                            $customer->deleted_at?->toIso8601String(),
                            $customer->merged_into_id,
                            $customer->first_seen_at?->toIso8601String(),
                            $customer->last_activity_at?->toIso8601String(),
                            $customer->created_at?->toIso8601String(),
                        ]);
                    }
                });
            },
        );
    }

    /**
     * @return array{0: string, 1: int} the temporary file, and its row count
     */
    private function reviewsCsv(): array
    {
        return $this->toCsvFile(
            ['id', 'source', 'status', 'rating', 'sentiment', 'reviewer_name', 'comment',
                'review_create_time', 'reply_status', 'reply_text', 'reply_posted_at'],
            function (callable $write): void {
                Review::query()->orderBy('id')->chunkById(self::CHUNK, function (Collection $reviews) use ($write): void {
                    // ⚠️ **THROUGH `ReviewReplies`, NOT AN EAGER LOAD** (2051).
                    // This read `->with('replies')` until row 3 slice J and this
                    // branch met on `main`: 1733 removed `Review::replies()`
                    // because `$review->replies()->create([...])` wrote a row
                    // around 1680's chokepoint — no tenant assertion, no
                    // guardrail pass, no audit entry. The relation is gone and
                    // `ReviewReplies` is the only reader of `replies` in `app/`,
                    // so the export asks it rather than re-opening the hole.
                    //
                    // ⚠️ The lint would NOT have caught this: it matches
                    // `->replies(` with the parenthesis, and neither the
                    // eager-load string nor `$review->replies` carries one.
                    // Larastan did.
                    $replies = $this->replies->latestForReviews(
                        $reviews->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                    );

                    foreach ($reviews as $review) {
                        // Latest attempt, not necessarily posted — a failed or
                        // drafted reply is still this tenant's data, not nothing.
                        $reply = $replies->get($review->id);

                        $write([
                            $review->id,
                            $review->source->value,
                            $review->status->value,
                            $review->rating,
                            $review->sentiment?->value,
                            $review->reviewer_name,
                            $review->comment,
                            $review->review_create_time?->toIso8601String(),
                            $reply?->status->value,
                            $reply?->text,
                            $reply?->posted_at?->toIso8601String(),
                        ]);
                    }
                });
            },
        );
    }

    /**
     * ⚠️ **ONE QUERY PER CONTACT, DELIBERATELY, AND THE CHUNKING IS WHY THAT IS
     * NOW ACCEPTABLE.** `MessageLog::forCustomer()` is the only door
     * `ConsentTest.php`'s chokepoint leaves open on `outreach_messages`
     * (decision 1825), so this cannot become one grouped read without becoming a
     * third writer/reader of that table. What the N+1 used to cost was memory as
     * well as queries — every contact and every message held at once; it now
     * costs queries alone, against a bounded working set.
     *
     * @return array{0: string, 1: int} the temporary file, and its row count
     */
    private function messagesCsv(): array
    {
        $log = app(MessageLog::class);

        return $this->toCsvFile(
            ['id', 'customer_id', 'customer_name', 'channel', 'purpose', 'status', 'lane', 'sent_at', 'created_at'],
            function (callable $write) use ($log): void {
                Customer::query()->orderBy('id')->chunkById(self::CHUNK, function (Collection $contacts) use ($write, $log): void {
                    foreach ($contacts as $contact) {
                        foreach ($log->forCustomer($contact) as $message) {
                            $write([
                                $message->id,
                                $contact->id,
                                $contact->name,
                                $message->channel->value,
                                $message->purpose,
                                $message->status->value,
                                $message->lane?->value,
                                $message->sent_at?->toIso8601String(),
                                $message->created_at?->toIso8601String(),
                            ]);
                        }
                    }
                });
            },
        );
    }

    /**
     * Write a CSV to its own temporary file, counting rows as they go past.
     *
     * A file rather than a `php://temp` string, because the string is the thing
     * that had to stop existing: `ZipArchive::addFromString()` needs the whole
     * member in memory and `addFile()` does not.
     *
     * @param  array<int, string>  $header
     * @param  callable(callable(array<int, mixed>): void): void  $rows
     * @return array{0: string, 1: int}
     */
    private function toCsvFile(array $header, callable $rows): array
    {
        $path = $this->tempFile();
        $stream = fopen($path, 'w');

        if ($stream === false) {
            throw new \RuntimeException('Could not open a temporary file to write a CSV.');
        }

        $count = 0;

        try {
            fputcsv($stream, $header);

            $rows(function (array $row) use ($stream, &$count): void {
                fputcsv($stream, $row);
                $count++;
            });
        } finally {
            fclose($stream);
        }

        return [$path, $count];
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'goaiez-export-');

        if ($path === false) {
            throw new \RuntimeException('Could not open a temporary file to build the export.');
        }

        return $path;
    }

    /**
     * Tell the owner it is ready — activity feed, audit log, and an attempted
     * email through the one sender this application uses.
     *
     * ⚠️ **THE EMAIL IS ATTEMPTED, NEVER PROMISED.** `PlatformMailer::send()`
     * cannot fail the caller (`DeliverPlatformMail` swallows an undeliverable
     * mailer and logs it), so this always runs — and the in-app link is what a
     * tenant actually has today, since the email vendor is not yet picked
     * (decisions 1191–1195).
     */
    private function announce(Business $business, TenantExport $export): void
    {
        $this->audit->record(
            'export.built',
            'system',
            $export,
            ['byte_size' => $export->byte_size, 'manifest' => $export->manifest],
        );

        $this->activity->record(AutopilotActionType::DataExported, null, [
            'export_id' => $export->id,
            // The id, never the contact's name — a feed row is a screen and a
            // stored bag at once, and the name is already one click away on the
            // profile this was started from.
            'customer_id' => $export->customer_id,
        ]);

        $owner = $business->owner;

        if ($owner instanceof User) {
            $url = $this->downloadUrl($export);

            if ($url !== null) {
                $this->mailer->send($owner->email, new TenantExportReady(
                    $url,
                    self::LINK_EXPIRY_DAYS,
                    $export->customer_id !== null,
                ));
            }
        }
    }

    /**
     * The contact this build is about, resolved under the tenant's own scope.
     *
     * ⚠️ **`Customer::query()` AND NOT A RELATION**, so the global scope and RLS
     * both apply and a row naming another tenant's contact — which the composite
     * foreign key already refuses at the schema — answers null here as well.
     * {@see self::build()} treats that null as a refusal rather than as an
     * account-wide export.
     */
    private function subjectOf(TenantExport $export): ?Customer
    {
        if ($export->customer_id === null) {
            return null;
        }

        return Customer::query()->whereKey($export->customer_id)->first();
    }

    public function requestCooldownMinutes(): int
    {
        return $this->defaults->int('export.request_cooldown_minutes');
    }

    public function inFlightReuseMinutes(): int
    {
        return $this->defaults->int('export.in_flight_reuse_minutes');
    }
}
