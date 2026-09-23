<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\ExportSource;
use App\Enums\UsState;
use App\Exceptions\ImpersonationRefused;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Services\Consent\ConsentService;
use App\Services\Crm\CrmNotes;
use App\Services\Crm\CrmTasks;
use App\Services\Crm\CustomerDirectory;
use App\Services\Crm\CustomerEditor;
use App\Services\Crm\CustomerMerges;
use App\Services\Crm\CustomerTimeline;
use App\Services\Crm\MergeDuplicateDetector;
use App\Services\Crm\NeverContact;
use App\Services\Export\ExportBuilder;
use App\Support\Identifier;
use App\Support\SqlState;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use RuntimeException;

/**
 * One contact — *"a timeline, not a form"* (`34` §1.2).
 *
 * ⚠️ **THE ID IS `#[Locked]`, ON DECISION 805'S FINDING.** Account 360 shipped a
 * writable `$businessId` on a staff screen and the audit row it filed would have
 * been a documented bypass. This is the tenant-facing twin of that screen: the
 * property is set once in `mount()` from a route the tenant boundary already
 * refused, and a browser that could edit it would be asking the directory for
 * somebody else's contact on every subsequent request.
 *
 * ## What is here and what is not
 *
 * §1.2's quick-actions row is **not built**, and none of it is a cut: Text needs
 * row 4's sending layer (551), Book needs a calendar integration, Send bill
 * needs `31` §5.3, and Request review is doc `43`, which is blocked on `42`
 * never having been delivered. A row of five disabled buttons would be worse
 * than none — `22`'s rule is that a disabled control explains itself in one
 * sentence, and the honest sentence for all five is "this product cannot do
 * that yet", which belongs in a roadmap rather than on every contact.
 *
 * What ships is the read path (`CustomerTimeline`), the consent panel §1.2 asks
 * for — which is `ConsentService::proofFor()` and needed no new reader — notes,
 * and the Never-contact control, which is the one action on this screen with a
 * service behind it.
 */
#[Layout('components.account.layout')]
final class CustomerProfile extends Component
{
    #[Locked]
    public int $customerId;

    /** The note being typed. Cleared on save, never persisted half-written. */
    public string $note = '';

    /** The header's inline-editable name (`34` §1.2). */
    public string $contactName = '';

    /** The header's tags, edited as comma-separated text. */
    public string $contactTags = '';

    /**
     * Which state this contact is in — `customers.region_code` (1594).
     *
     * ⚠️ **THE STATE ALONE, NOT AN ADDRESS.** `BUILD-PLAN` §2.10.3 says
     * "structured address capture"; the only reader consumes a two-letter USPS
     * code and `customers` has no address columns, so asking for a street is
     * more personal data with no reader — CLAUDE.md's ambiguity rule decides it
     * the other way. See `CustomerEditor::setRegion()`.
     */
    public string $contactRegion = '';

    /** "Remind me" (`44` §2): the one line saying what to do. */
    public string $reminderTitle = '';

    /** §2's four choices: today · tomorrow · next_week · date. */
    public string $reminderWhen = 'today';

    /** The picked date, only read when $reminderWhen is 'date'. */
    public ?string $reminderDate = null;

    /**
     * Whether the Never-contact confirm is showing.
     *
     * ⚠️ **THE CONFIRM IS ON TURNING IT ON, WHICH IS THE OPPOSITE WAY ROUND FROM
     * `TenantPause`** (826). There, the emergency stop is unconfirmed and
     * *resuming* asks, because a confirm step is in the way at the moment
     * somebody needs it most. Here the dangerous direction is the other one:
     * marking a contact Never-contact silently stops every future message to a
     * real person, and §1.2 asks for a dialog that "states consequences
     * plainly". Clearing it restores the ordinary state and asks nothing.
     */
    public bool $confirmingNeverContact = false;

    /**
     * The delete confirm (1546). See `confirmDelete()` for why this state exists
     * where archive's does not.
     */
    public bool $confirmingDelete = false;

    /**
     * The duplicate whose side-by-side picker is open, if any (`34` §1.2).
     *
     * ⚠️ **DELIBERATELY NOT `#[Locked]`, UNLIKE `$customerId` ABOVE.** It is set
     * by the browser, because picking which duplicate to fold in is the one
     * thing on this screen the owner chooses from a list. What makes that safe
     * is not the attribute: `CustomerDirectory::find()` refuses another tenant's
     * id, and `CustomerMerges::merge()` refuses a pair that is not actually a
     * detected duplicate — both in the service, so a guessed id gets the same
     * refusal a guessed URL would (391's rule, 398's placement).
     */
    public ?int $mergeCandidateId = null;

    /** §1.2's picker: which side the surviving contact's name comes from. */
    public string $chooseName = 'survivor';

    /** And its tags. Contact details are never a choice — see `CustomerMerges`. */
    public string $chooseTags = 'survivor';

    /**
     * ⚠️ **`$customer` IS AN `int` AND IS DECLARED FIRST, BOTH DELIBERATELY.**
     * Resolving it through the directory rather than by implicit route binding
     * is what makes the tenant check falsifiable in a component test, where no
     * middleware runs (decision 809) — implicit binding would do the refusing
     * outside the code this screen is responsible for. And the order matters
     * because Laravel splices container-resolved arguments into the *positional*
     * parameter list (396): a route parameter declared after a service receives
     * the service's slot and dies with a `TypeError` naming the wrong thing.
     */
    public function mount(int $customer, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $resolved = $directory->find($customer);

        $this->customerId = $resolved->getKey();
        $this->contactName = $resolved->name ?? '';
        $this->contactTags = implode(', ', $resolved->tags ?? []);
        $this->contactRegion = $resolved->region_code ?? '';
    }

    /**
     * §1.2's inline-editable name and tags, saved together — they are one
     * header and one Save is one decision. Tags are typed comma-separated;
     * `CustomerEditor` owns the parsing rules and refuses independently of
     * this screen (398's rule).
     *
     * ⚠️ **THE STATE SAVES HERE TOO, AND IT WRITES NOTHING ITSELF** (1594).
     * `CustomerEditor::setRegion()` is the only writer of
     * `customers.region_code` — a lint holds the column there — and it refuses a
     * code that is not a real USPS one whether or not this select offered it,
     * which is what keeps that refusal falsifiable without the screen (398).
     */
    public function saveDetails(CustomerEditor $editor, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->validate([
            'contactName' => ['nullable', 'string', 'max:'.app(CustomerEditor::class)->maxNameLength()],
            'contactTags' => ['nullable', 'string', 'max:2000'],
            // `Rule::enum` rather than a hand-written `in`: the list of USPS
            // codes lives in exactly one place (`UsState`), and a second copy
            // here is the drift CLAUDE.md's no-database-enum argument is about.
            'contactRegion' => ['nullable', 'string', Rule::enum(UsState::class)],
        ], attributes: [
            'contactName' => 'name',
            'contactTags' => 'tags',
            'contactRegion' => 'state',
        ]);

        $customer = $directory->find($this->customerId);

        try {
            $editor->rename($customer, $this->contactName);
            $customer = $editor->retag($customer, explode(',', $this->contactTags));
            $customer = $editor->setRegion($customer, $this->contactRegion, $this->currentUser());
        } catch (InvalidArgumentException $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        $this->contactName = $customer->name ?? '';
        $this->contactTags = implode(', ', $customer->tags ?? []);
        $this->contactRegion = $customer->region_code ?? '';

        Toaster::success('Saved.');
    }

    /**
     * Append a note (§1.2 — *"append, edit-own, no rich text"*).
     *
     * ⚠️ Edit and delete are deliberately absent rather than deferred. §1.2
     * grants edit-own, and the row has no `updated_at` and no author-change
     * record — so an edited note would silently rewrite what somebody wrote
     * about a named person with nothing recording that it changed. Adding the
     * column is a migration this slice does not have the room to make; shipping
     * the edit without it would be the wrong half.
     */
    public function addNote(CrmNotes $notes, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->validate([
            'note' => ['required', 'string', 'max:'.app(CrmNotes::class)->maxLength()],
        ], attributes: ['note' => 'note']);

        $notes->add($directory->find($this->customerId), $this->note, $this->currentUser());

        $this->note = '';

        Toaster::success('Note added.');
    }

    /**
     * "Remind me" (`44` §2) — a follow-up on this contact, with §2's four date
     * choices: Today, Tomorrow, Next week, or a picked date.
     *
     * The due moment is the end of the chosen day, so a reminder made at 9am
     * for "today" does not read as overdue by lunch. The cap refusal comes
     * from the service as a sentence naming its registry key, and is shown
     * rather than swallowed.
     */
    public function remind(CrmTasks $tasks, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->validate([
            'reminderTitle' => ['required', 'string', 'max:'.CrmTasks::MAX_TITLE_LENGTH],
            'reminderWhen' => ['required', 'string', Rule::in(['today', 'tomorrow', 'next_week', 'date'])],
            'reminderDate' => ['nullable', 'required_if:reminderWhen,date', 'date'],
        ], attributes: [
            'reminderTitle' => 'reminder',
            'reminderDate' => 'date',
        ]);

        if ($this->reminderWhen === 'today') {
            $dueAt = CarbonImmutable::now()->endOfDay();
        } elseif ($this->reminderWhen === 'tomorrow') {
            $dueAt = CarbonImmutable::tomorrow()->endOfDay();
        } elseif ($this->reminderWhen === 'next_week') {
            $dueAt = CarbonImmutable::now()->addWeek()->endOfDay();
        } else {
            $dueAt = CarbonImmutable::parse((string) $this->reminderDate)->endOfDay();
        }

        try {
            $tasks->remind(
                $directory->find($this->customerId),
                $this->reminderTitle,
                $dueAt,
                $this->currentUser(),
            );
        } catch (RuntimeException $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        $this->reset('reminderTitle', 'reminderWhen', 'reminderDate');

        Toaster::success('We’ll remind you.');
    }

    /**
     * Archive — the owner's tidying, not a suppression (`44` §8, 1327). No
     * confirm in either direction, unlike Never-contact: both moves are fully
     * reversible, the contact visibly leaves or rejoins the list, and the
     * timeline is untouched either way. Decision 826's rule — confirm the
     * direction that is hard to notice you took — finds neither direction hard
     * to notice here.
     */
    public function archive(CustomerEditor $editor, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $editor->archive($directory->find($this->customerId), $this->currentUser());

        Toaster::success('Archived. You can restore them any time.');
    }

    public function restore(CustomerEditor $editor, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $editor->restore($directory->find($this->customerId), $this->currentUser());

        Toaster::success('Restored.');
    }

    /**
     * Delete — D-205's third state (1540).
     *
     * ⚠️ **CONFIRMED, WHERE ARCHIVE IS NOT, AND THE DIFFERENCE IS THE CLOCK.**
     * Decision 826's rule is to confirm the direction that is hard to notice
     * you took, and it has now been answered four different ways in this
     * product: Never-contact confirms ON (1228), the pause confirms RESUME
     * (826), archive confirms NEITHER (1503) — and delete confirms the delete,
     * because it is the only one of the four whose reversibility expires. An
     * owner who archives and changes their mind in a month is fine; an owner who
     * deletes and changes their mind in a month is not, and nothing later in the
     * flow would have told them so.
     *
     * Bringing them back is deliberately not confirmed: undoing needs no
     * ceremony, and a confirm on the recovery path is friction at the moment
     * somebody has already realised their mistake.
     */
    public function confirmDelete(): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function delete(CustomerEditor $editor, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        try {
            $editor->delete($directory->find($this->customerId), $this->currentUser());
        } catch (InvalidArgumentException $refusal) {
            $this->confirmingDelete = false;

            Toaster::error($refusal->getMessage());

            return;
        }

        $this->confirmingDelete = false;

        Toaster::success('Deleted. You can bring them back for '.CustomerEditor::RESTORE_WINDOW_DAYS.' days.');
    }

    /**
     * Bring a deleted contact back, inside the window.
     *
     * ⚠️ **THE REFUSAL IS THE SERVICE'S AND IS SURFACED VERBATIM.** The button
     * stops rendering at day seven, but a kept tab renders yesterday's markup
     * and a `wire:click` from it still arrives — 391's rule, that a control
     * which is not rendered must not be honoured through a side door.
     */
    public function undelete(CustomerEditor $editor, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        try {
            $editor->undelete($directory->find($this->customerId), $this->currentUser());
        } catch (InvalidArgumentException $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        Toaster::success('Brought back.');
    }

    /**
     * Open the side-by-side picker for one detected duplicate (`34` §1.2).
     *
     * The name and tags default to *this* contact's — the one the owner is
     * already looking at — so pressing Merge without touching anything keeps
     * what is on screen. A picker whose default silently replaced the header
     * would be the destructive choice made by not choosing.
     */
    public function startMerge(int $candidateId): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->mergeCandidateId = $candidateId;
        $this->chooseName = 'survivor';
        $this->chooseTags = 'survivor';
    }

    public function cancelMerge(): void
    {
        $this->reset('mergeCandidateId', 'chooseName', 'chooseTags');
    }

    /**
     * Fold the picked duplicate into this contact.
     *
     * ⚠️ Every refusal comes from the service and is shown rather than
     * swallowed: not a duplicate, already merged, another tenant's contact. The
     * screen adds no guard of its own, which is what keeps each of them
     * falsifiable without it (398).
     */
    public function merge(CustomerMerges $merges, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->validate([
            'mergeCandidateId' => ['required', 'integer'],
            'chooseName' => ['required', 'string', Rule::in(['survivor', 'merged'])],
            'chooseTags' => ['required', 'string', Rule::in(['survivor', 'merged'])],
        ], attributes: ['mergeCandidateId' => 'customer']);

        try {
            $merges->merge(
                $directory->find($this->customerId),
                $directory->find((int) $this->mergeCandidateId),
                ['name' => $this->chooseName, 'tags' => $this->chooseTags],
                $this->currentUser(),
            );
        } catch (InvalidArgumentException $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        $this->cancelMerge();

        $customer = $directory->find($this->customerId);
        $this->contactName = $customer->name ?? '';
        $this->contactTags = implode(', ', $customer->tags ?? []);
        // A merge fills a gap in the survivor's state (1599), so the header has
        // to be re-read or the next Save would write back the value this screen
        // was rendered with and undo it.
        $this->contactRegion = $customer->region_code ?? '';

        Toaster::success('Merged. You can undo this for '.CustomerMerges::UNDO_WINDOW_DAYS.' days.');
    }

    /**
     * Put a merge back (`34` §7's build-failing *"undoable 30 days"*).
     *
     * The id comes from the browser and is resolved through `CustomerMerges::
     * find()`, which is tenant-scoped the same way the directory is — so a
     * guessed id gets a 404 rather than another tenant's history.
     */
    public function undoMerge(int $mergeId, CustomerMerges $merges, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        try {
            $merges->undo($merges->find($mergeId), $this->currentUser());
        } catch (RuntimeException $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        $customer = $directory->find($this->customerId);
        $this->contactName = $customer->name ?? '';
        $this->contactTags = implode(', ', $customer->tags ?? []);
        $this->contactRegion = $customer->region_code ?? '';

        Toaster::success('Undone. They are two customers again.');
    }

    /**
     * Record the owner's instruction not to contact this person.
     */
    public function markNeverContact(NeverContact $neverContact, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $neverContact->apply($directory->find($this->customerId), $this->currentUser());

        $this->confirmingNeverContact = false;

        Toaster::success('We won’t contact them again.');
    }

    /**
     * Withdraw it — when it was ours to withdraw.
     *
     * ⚠️ The service refuses independently, and this catch is the *message*
     * rather than the guard. Decision 398: a control that is not rendered but is
     * still honoured is a gate with a side door, and the button below is hidden
     * for exactly the case this catches.
     */
    public function clearNeverContact(NeverContact $neverContact, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        try {
            $neverContact->release($directory->find($this->customerId), $this->currentUser());
        } catch (RuntimeException $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        Toaster::success('You can contact them again.');
    }

    /**
     * `44` §10's *"Export this customer"* — the surface an individual
     * data-subject request is answered from.
     *
     * ⚠️ **A LIVEWIRE ACTION, WHERE THE ACCOUNT EXPORT IS A NAMED POST, AND THE
     * DIFFERENCE IS THE SUSPENSION** (6573). 1900 moved *"Download my data"* to
     * `account.data-export.request` because `SuspendedTenantStatus` exempts by
     * route name and `28` §3.7 forbids gating an export — a suspended owner is
     * redirected off `/account` and had to keep the control. **This screen is
     * behind that redirect and cannot be rescued by any exemption**: there is no
     * contact list on the on-hold page to press this from. What keeps §3.7's
     * promise for a suspended tenant is that the account export is still on the
     * on-hold page and **contains this contact's rows already** — the narrower
     * file is a convenience over the wider one, never the only way to get the
     * data out. ⛔ **A second named POST route was the other option and it is
     * not this lane's to add.**
     *
     * ⚠️ **NOTHING IS GATED HERE.** No credit balance, no cap, no plan check —
     * `28` §3.7 again, and 4761's third leg is the reason an aggregate storage
     * ceiling was refused on this very kind of object.
     *
     * The refusal caught is support's: {@see ExportBuilder::requestForContact()}
     * throws inside an impersonation session (`28` §9.4), and 398's rule puts
     * the guard in the service — this catch is the sentence, not the gate.
     */
    public function exportContact(ExportBuilder $exports, CustomerDirectory $directory): void
    {
        abort_if(Tenancy::id() === null, 403);

        $customer = $directory->find($this->customerId);

        try {
            $exports->requestForContact(
                $this->business(),
                $customer,
                $this->currentUser(),
                ExportSource::Owner,
            );
        } catch (ImpersonationRefused $refusal) {
            Toaster::error($refusal->getMessage());

            return;
        }

        Toaster::success('Building the download — we’ll email you a link when it’s ready.');
    }

    public function render(
        CustomerDirectory $directory,
        CustomerTimeline $timeline,
        ConsentService $consent,
        NeverContact $neverContact,
        MergeDuplicateDetector $duplicates,
        CustomerMerges $merges,
        ExportBuilder $exports,
    ): View {
        abort_if(Tenancy::id() === null, 403);

        $customer = $directory->find($this->customerId);

        // ⚠️ **CAUGHT NARROWLY, AROUND ONE READ EACH, AND `QueryException` ONLY**
        // (1547). `34` §1.2 asks for an error state that says what happened and
        // offers a retry, and the version of that which does harm is a catch
        // around the whole render: it swallows programming errors alongside
        // transient ones, so a `TypeError` becomes a retry button that can never
        // work, and it takes the parts of the page that loaded fine down with
        // it. These two reads are the ones where a retry is a real answer —
        // the timeline unions four stores and the detector runs its own scan —
        // and a failure in either leaves the rest of the profile usable.
        //
        // The header is deliberately NOT guarded: if `find()` above cannot read
        // the contact there is no page to render a section error into, and
        // pretending otherwise would show a profile with nobody in it.
        try {
            $entries = $timeline->forCustomer($customer);
            $timelineFailed = false;
        } catch (QueryException $failure) {
            $this->recordSectionFailure('timeline', $customer, $failure);

            $entries = collect();
            $timelineFailed = true;
        }

        try {
            $duplicateCandidates = $duplicates->candidatesFor($customer);
            $duplicatesFailed = false;
        } catch (QueryException $failure) {
            $this->recordSectionFailure('duplicates', $customer, $failure);

            $duplicateCandidates = collect();
            $duplicatesFailed = true;
        }

        $contactExport = $exports->mostRecentForContact($customer);

        return view('livewire.account.customer-profile', [
            'customer' => $customer,
            'entries' => $entries,
            'timelineFailed' => $timelineFailed,
            // §1.2's banner, on both profiles — the detector is symmetric, so
            // each of a pair names the other with no second query shape.
            'duplicates' => $duplicateCandidates,
            'duplicatesFailed' => $duplicatesFailed,
            // The one whose picker is open, resolved through the directory so a
            // stale id from a previous render refuses rather than 500s.
            'mergeCandidate' => $this->mergeCandidateId === null
                ? null
                : $directory->find($this->mergeCandidateId),
            'undoableMerges' => $merges->undoableFor($customer),
            // Set only on the profile of a contact that has been folded away —
            // the bookmark case `CustomerDirectory::find()` deliberately still
            // resolves.
            'mergedAway' => $merges->mergeAwayOf($customer),
            'consentTrail' => $consent->proofFor($customer),
            // ⚠️ **`mostRecentForContact()` AND NEVER `mostRecent()`** (6566).
            // The unscoped method is what the account screen reads, and reading
            // it here would put a link to the **whole-account archive** under
            // this contact's heading the moment an owner had built one — a
            // one-word slip on a screen whose whole subject is one person.
            'contactExport' => $contactExport,
            'contactExportUrl' => $contactExport === null ? null : $exports->downloadUrl($contactExport),
            // The same method the list renders from, so the two screens cannot
            // disagree — and standing rather than trail, so this agrees with
            // the Never-contact control beside it. See ConsentBadge.
            'consentBadge' => $consent->badgesFor(collect([$customer]))[$customer->getKey()],
            'neverContact' => $neverContact->state($customer),
        ]);
    }

    /**
     * What may be written about a section of this page that could not be read.
     *
     * ⛔ **THE MESSAGE IS NOT LOGGED, AND `report()` IS NOT USED, BECAUSE
     * `QueryException`'s MESSAGE IS THE CUSTOMER'S OWN EMAIL ADDRESS AND PHONE
     * NUMBER** (11490). `Illuminate\Database\QueryException::__construct()` is
     * `$this->message = $this->formatMessage(…)` and `formatMessage()` ends
     * `', SQL: '.Str::replaceArray('?', $bindings, $sql)` — **it interpolates
     * the bindings** — and both reads above bind identifiers rather than ids:
     *
     *   duplicates  {@see MergeDuplicateDetector::candidatesFor()} binds
     *               `lower(coalesce(email,'')) = ?` and the ten trailing digits
     *               of the phone, which for every NANP number
     *               {@see Identifier::phone()} builds is the whole
     *               dialable number rather than a tail.
     *
     *   timeline    {@see ConsentService::proofFor()} —
     *               reached through {@see CustomerTimeline::forCustomer()} —
     *               binds `whereIn('identifier', …)` over the SMS and email
     *               identifiers, so this arm carries **the same two strings**.
     *               ⚠️ It was read as *"ids, lower risk"* while it was being
     *               repaired, and it is not (11491).
     *
     * ⛔ **AND `report()` WOULD HAVE WRITTEN THAT MESSAGE VERBATIM.**
     * `bootstrap/app.php`'s `withExceptions()` registers no `reportable()`
     * callback and no reporting integration, so `report()` is
     * `Handler::reportThrowable()`'s default: `$logger->error($e->getMessage(),
     * ['exception' => $e])`. `TenantDeletion::execute()` already refuses to log
     * that class's message for this reason, and `PlatformMailer::deliverNow()`
     * wrote the same hazard down for the queue insert.
     *
     * ⚠️ **`report()` IS ALSO A NO-OP INSIDE A LIVEWIRE COMPONENT TEST**
     * (11492, 11501). `Livewire\Features\SupportTesting\RequestBroker`'s
     * `temporarilyDisableExceptionHandlingAndMiddleware()` calls
     * `withoutExceptionHandling()`, which swaps `ExceptionHandler` for the
     * harness stub whose `report()` has an EMPTY BODY
     * (`InteractsWithExceptionHandling:107-110`) — so both arms were
     * unobservable in the harness this screen is tested with, and a test
     * asserting *"the address does not reach the log"* through `Livewire::test()`
     * would have passed on the leaking code. Measured: `Log::error()` from
     * inside the same catch fires a `MessageLogged` event and `report()` fires
     * none.
     *
     * ⚠️ **THE SECTION IS THE FIELD THAT VARIES, AND THAT IS THE POINT.** The
     * two arms were textually identical, so the log could not say which read
     * failed — and the remedies differ: the timeline unions six stores and the
     * detector is one scan over `customers`.
     *
     * ⚠️ **`warning` RATHER THAN `error`, AND IT IS A CHOICE RATHER THAN AN
     * INHERITANCE.** `error` was the framework's default for anything it has not
     * been told about; this fault is handled — the rest of the profile renders
     * and the section offers a retry — which is the state
     * `TenantDeletion::execute()` logs at `warning` for the same reason.
     *
     * ⚠️ **THE SQLSTATE IS THE HALF AN OPERATOR CAN ACT ON**, through
     * {@see SqlState} rather than `getCode()`, whose value is an int on some
     * drivers and failures. The two ids are surrogate keys this tenant already
     * owns; neither is an identifier of a person outside this system.
     */
    private function recordSectionFailure(string $section, Customer $customer, QueryException $failure): void
    {
        Log::warning('a contact profile section could not be read', [
            'section' => $section,
            'business_id' => Tenancy::id(),
            'customer_id' => $customer->getKey(),
            'sqlstate' => SqlState::of($failure),
        ]);
    }

    /**
     * The tenant this screen is acting on.
     *
     * ⚠️ **403 RATHER THAN LETTING `Tenancy::idOrFail()` THROW**, which is
     * `Account\Settings::business()`' reasoning and it applies harder here:
     * internal staff belong to no business (`28` §9.1), so a signed-in support
     * agent typing a contact URL is the ordinary way to arrive with nothing
     * resolved, and a `TenantNotResolved` reaching the renderer is a 500 that
     * reads as our page being broken.
     */
    private function business(): Business
    {
        $id = Tenancy::id();

        abort_if($id === null, 403);

        return Business::query()->findOrFail($id);
    }

    /**
     * ⚠️ Typed rather than trusted. `Auth::user()` is `Authenticatable`, and
     * `CrmNotes::add()` refuses a note with no author — the failure this avoids
     * is a null slipping through to a row that says nobody wrote it.
     */
    private function currentUser(): User
    {
        $user = Auth::user();

        abort_if(! $user instanceof User, 403);

        return $user;
    }
}
