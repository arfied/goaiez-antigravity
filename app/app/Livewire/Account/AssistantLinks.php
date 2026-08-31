<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\TenantLinkKind;
use App\Models\TenantLinkRecord;
use App\Services\Links\TenantLink;
use App\Services\Links\TenantLinks;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Teach your assistant — where it sends people (T176 §2.4, P6).
 *
 * §2.4's step is bigger than this screen: the price list, the urgent terms and
 * the quote disclaimer belong to P5, and the toggles to P4. **What lands here is
 * the links half** — booking URL, payment URL with its call-out fee and
 * what-it-covers line, and the shared documents picker.
 *
 * ⛔ **EVERY SECTION SAYS WHAT STAYS SWITCHED OFF WHILE IT IS EMPTY, AND THAT IS
 * R13 RATHER THAN COPY.** The capability-gating law makes a missing link mean
 * *the skill is absent* — the assistant takes preferred times and hands them to
 * the owner instead of booking. An owner who cannot see that is an owner who
 * believes their assistant is booking appointments, so
 * {@see TenantLinkKind::missingCapability()} is rendered beside each empty
 * section in the words it already carries.
 *
 * ⚠️ **NOT A WIZARD STEP, ON DECISION 2308's FINDING** — `Account\Knowledge` and
 * `Account\Calls` both record it. Inserting a step before `Done` means editing an
 * applied migration whose integer→step `CASE` is a record of what those integers
 * meant in rows already written. A booking URL also changes whenever a business
 * changes scheduler, which a one-shot step cannot offer.
 *
 * ⚠️ **IT WRITES NOTHING ITSELF.** {@see TenantLinks} is the only reader and
 * writer of `tenant_links`, held there by a lint, and this component validates an
 * answer and calls it. That is what stops a second screen becoming a second idea
 * of what a link is — and it is what keeps the raw destination out of every file
 * but one.
 *
 * AUTHORIZATION IS A POLICY (`CLAUDE.md`). A `staff` user may see where the
 * assistant sends people and may not move it: a payment URL is where a customer
 * will be asked for their card in the business's name.
 */
#[Layout('components.account.layout')]
final class AssistantLinks extends Component
{
    public string $bookingUrl = '';

    public string $paymentUrl = '';

    /**
     * The call-out fee, in whole currency units as typed.
     *
     * ⚠️ **A STRING RATHER THAN A `?float`, AND FOR TWO REASONS.** Livewire
     * hydrates a public property to its declared type before validation runs, so
     * a hand-posted `fee=abc` on a typed property is a TypeError rather than a
     * message anybody can act on — `Account\ReviewRules` records the same trap.
     * And `18` §Money handling forbids money as a float anywhere: this is parsed
     * to integer cents by string arithmetic in {@see feeCents()}, never by
     * `(float) $fee * 100`, which is `28` for `0.29` in IEEE-754.
     *
     * ⛔ **EMPTY AND `0` ARE TWO DIFFERENT ANSWERS.** Empty is *I have not said*,
     * which switches skill 6 off under R13; `0` is *we come out free*, which
     * grounds it. Nothing on this path may collapse the two with a truthiness
     * check.
     */
    public string $fee = '';

    public string $feeCovers = '';

    public string $documentName = '';

    public string $documentUrl = '';

    public function mount(TenantLinks $links): void
    {
        abort_if(Tenancy::id() === null, 403);

        $booking = $links->booking();

        if ($booking instanceof TenantLink) {
            $this->bookingUrl = $booking->destination();
        }

        $payment = $links->payment();

        if ($payment instanceof TenantLink) {
            $this->paymentUrl = $payment->destination();
            $this->fee = $payment->feeCents === null ? '' : $this->asAmount($payment->feeCents);
            $this->feeCovers = $payment->feeCovers ?? '';
        }
    }

    public function saveBooking(TenantLinks $links): void
    {
        // Authorization before validation, on `Account\Knowledge`'s reasoning:
        // telling somebody their URL is malformed and then refusing them for
        // their role is two errors for one action, and the second is the one
        // that mattered.
        Gate::authorize('create', TenantLinkRecord::class);

        $this->validate(
            ['bookingUrl' => $this->urlRules()],
            ['bookingUrl.required' => 'Paste the web address where customers book with you.'] + $this->urlMessages('bookingUrl'),
        );

        if (! $this->attempt(fn (): mixed => $links->setBooking($this->bookingUrl), 'bookingUrl')) {
            return;
        }

        Toaster::success('Saved — your assistant can book people in now');
    }

    public function removeBooking(TenantLinks $links): void
    {
        Gate::authorize('create', TenantLinkRecord::class);

        $links->remove(TenantLinkKind::Booking);

        $this->bookingUrl = '';

        Toaster::success('Removed — your assistant will take preferred times instead');
    }

    public function savePayment(TenantLinks $links): void
    {
        Gate::authorize('create', TenantLinkRecord::class);

        $this->validate([
            'paymentUrl' => $this->urlRules(),

            // `regex` rather than `numeric`, because `numeric` accepts `1e3`
            // and `0x1A`, and both would reach the parser as an amount nobody
            // typed. `nullable` is what keeps "no fee" reachable — see the
            // property's own docblock for why that is not the same as zero.
            'fee' => ['nullable', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],

            'feeCovers' => ['nullable', 'string', 'max:280'],
        ], [
            'paymentUrl.required' => 'Paste the web address where customers pay you.',
            'fee.regex' => 'Write the fee as a plain amount, like 85 or 85.50. Leave it blank if you do not charge to come out.',
            'feeCovers.max' => 'Keep this to a sentence — it is read out in a text message.',
        ] + $this->urlMessages('paymentUrl'));

        $cents = $this->feeCents();
        $covers = trim($this->feeCovers);

        if ($covers !== '' && $cents === null) {
            // ⚠️ REFUSED RATHER THAN SAVED WITHOUT THE FIGURE. The sentence
            // explains the scope of a charge, so keeping it while dropping the
            // number leaves the assistant able to say what a fee covers and
            // unable to say what it is.
            $this->addError('fee', 'Set the fee as well, or clear the line about what it covers.');

            return;
        }

        $saved = $this->attempt(
            fn (): mixed => $links->setPayment($this->paymentUrl, $cents, $covers === '' ? null : $covers),
            'paymentUrl',
        );

        if (! $saved) {
            return;
        }

        Toaster::success($cents === null
            ? 'Saved — your assistant can send people to pay'
            : 'Saved — your assistant can name your call-out fee');
    }

    public function removePayment(TenantLinks $links): void
    {
        Gate::authorize('create', TenantLinkRecord::class);

        $links->remove(TenantLinkKind::Payment);

        $this->paymentUrl = '';
        $this->fee = '';
        $this->feeCovers = '';

        Toaster::success('Removed — your assistant will not ask anyone to pay');
    }

    public function addDocument(TenantLinks $links): void
    {
        Gate::authorize('create', TenantLinkRecord::class);

        $this->validate([
            'documentName' => ['required', 'string', 'max:120'],
            'documentUrl' => $this->urlRules(),
        ], [
            'documentName.required' => 'Give it the name a customer would ask for, like "Price sheet".',
            'documentName.max' => 'Keep the name short enough to say in a text message.',
            'documentUrl.required' => 'Paste the web address the document lives at.',
        ] + $this->urlMessages('documentUrl'));

        $added = $this->attempt(
            fn (): mixed => $links->addDocument($this->documentName, $this->documentUrl),
            'documentUrl',
        );

        if (! $added) {
            // ⚠️ THE FIELDS KEEP WHAT WAS TYPED ON A REFUSAL. Clearing them
            // alongside an error message asks somebody to retype the thing we
            // are complaining about, which is how a correctable mistake becomes
            // a support ticket.
            return;
        }

        $this->reset('documentName', 'documentUrl');

        Toaster::success('Added — your assistant can send this when somebody asks');
    }

    public function removeDocument(TenantLinks $links, string $slug): void
    {
        Gate::authorize('create', TenantLinkRecord::class);

        $links->remove(TenantLinkKind::Document, $slug);

        Toaster::success('Removed — your assistant will stop offering it');
    }

    public function render(TenantLinks $links): View
    {
        // Refused rather than resolved when there is no tenant, on
        // `Account\Knowledge`'s reasoning: internal staff belong to no business
        // by design, so a signed-in support agent typing this URL is the ordinary
        // way to arrive with nothing resolved, and letting `Tenancy::idOrFail()`
        // reach the renderer is a 500 that reads as our page being broken.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.assistant-links', [
            'booking' => $links->booking(),
            'payment' => $links->payment(),
            'documents' => $links->documents(),
            'grounded' => $links->grounded(),
            'mayEdit' => Gate::allows('create', TenantLinkRecord::class),
        ]);
    }

    /**
     * Run a write and turn the service's refusal into a message on the field.
     *
     * ⚠️ **THE SERVICE'S CHECKS ARE NOT A DUPLICATE OF THE RULES ABOVE.** The
     * rules here are about what somebody typed; {@see TenantLinks} refuses what
     * the store cannot mean — our own short-link domain pasted back in, a scheme
     * a phone will not open, a name with no letters in it. Those have to hold for
     * every caller, so they live there, and this is how they reach the screen
     * instead of becoming a 500.
     *
     * ⛔ **IT RETURNS WHETHER THE WRITE HAPPENED, AND THE FIRST DRAFT RETURNED
     * NOTHING.** Every caller went on to toast *"Saved"* whether or not the
     * service had refused, so the screen showed a success message and an error
     * message about the same press — and the toast is the half a person reads.
     *
     * @param  callable(): mixed  $write
     */
    private function attempt(callable $write, string $field): bool
    {
        try {
            $write();
        } catch (InvalidArgumentException $refusal) {
            $this->addError($field, $refusal->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function urlRules(): array
    {
        // `url:http,https` rather than a bare `url`, which accepts `javascript:`
        // and `mailto:` — neither is something a phone opens, and the first is
        // what a link nobody checked would carry into a message.
        return ['required', 'string', 'max:2048', 'url:http,https'];
    }

    /**
     * @return array<string, string>
     */
    private function urlMessages(string $field): array
    {
        return [
            $field.'.url' => 'That does not look like a web address. It should start with https:// and name a website.',
            $field.'.max' => 'That address is too long to fit in a text message.',
        ];
    }

    /**
     * The typed fee as integer cents, or null when the field is empty.
     *
     * ⛔ **STRING ARITHMETIC, NEVER `(int) ($fee * 100)`.** `(int) (0.29 * 100)`
     * is `28` in IEEE-754, and `PurchaseReconciliation` and `AuthorizeNetWebhooks`
     * both carry the same note over the same one-line temptation.
     */
    private function feeCents(): ?int
    {
        $typed = trim($this->fee);

        if ($typed === '') {
            return null;
        }

        if (preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $typed, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    /**
     * Cents back into the field's own vocabulary, for `mount()`.
     */
    private function asAmount(int $cents): string
    {
        return $cents % 100 === 0
            ? (string) intdiv($cents, 100)
            : intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
