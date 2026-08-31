<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\LegalDocumentType;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\TermsAcceptance;
use App\Services\AuditService;
use App\Services\Legal\SignupTerms;
use App\Services\Legal\TermsAcceptances as Acceptances;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * What one business agreed to at signup, and the proof of it (T176 P22, 3995).
 *
 * ⚠️ **THIS IS THE READER THE RECORD WAS WRITTEN FOR.** P22's write path landed
 * with a note in `Services\Legal\TermsAcceptances` saying there was deliberately
 * no reader — *"its readers are a person answering a carrier's question and
 * whatever screen a later slice builds for them"*. This is that screen. Until it
 * existed, `terms_acceptances` was a table with a writer and no way to get an
 * answer out of it, which is the near-mirror of decision 272's shape: not a
 * control nothing writes, but evidence nobody can produce.
 *
 * ⛔ **IT SHOWS AND IT DOES NOTHING ELSE.** There is no action on this screen —
 * no form beyond the lookup, no button, no correction. An acceptance is
 * append-only in the model and the only thing an edit control could add is
 * agreement nobody gave. A staff member who believes a row is wrong has one
 * honest remedy, which is to say so; there is no second one for this screen to
 * offer.
 *
 * ⚠️ **THREE DOCUMENTS, ANSWERED SEPARATELY, BECAUSE COUNSEL VERSIONS THEM
 * SEPARATELY.** The standing panel asks {@see Acceptances::latestFor()} once per
 * document rather than reporting one verdict, and a document with no row says so
 * in words. A carrier reviewer asks about the SMS & Communications Terms
 * specifically; "they accepted the terms" is an answer about a different
 * document, and the whole reason this record holds three rows is that a single
 * one could only be honest about one of them.
 *
 * ⚠️ **IT LOOKS A BUSINESS UP BY NUMBER, LIKE `PhiTenants`, AND FOR THE SAME
 * REASON.** `businesses` carries two RLS policies and neither admits platform
 * staff, so the runtime role cannot enumerate businesses at all. A list would
 * need a third policy and a session flag saying "I am staff", which is a
 * platform-wide authorization surface on the table the isolation gate rests on
 * — not this screen's to invent.
 *
 * ⚠️ **A RESOLVED LOOKUP IS AUDITED IN THE LOOKED-UP TENANT'S OWN LOG**, the
 * same entry and the same argument as `PhiTenants::lookUp()`. What this screen
 * renders is the account holder's own proof blob — the page they were on, a
 * hashed address, their browser's user agent and the actor string naming them —
 * so a read that left no trace would let staff walk the business id space with
 * nothing anywhere to say they did.
 *
 * ⚠️ **NO PERSONAL DATA REACHES A TOAST** (decision 104). The only toasts here
 * are "enter a number" and "there is no business N", and the second discloses
 * nothing about a business that does not exist.
 *
 * ⚠️ **ONE STATE IS DELIBERATELY UNEXPLAINED AND SAYING SO IS CHEAPER THAN
 * GUESSING AT IT.** If the business in view is deleted between the lookup and
 * the next render, `find()` answers null and the page falls back to the lookup
 * form with nothing under it — the same shape a fresh visit has. Telling the two
 * apart means holding a "was resolved, is now gone" flag, and a business row is
 * deleted about as often as this screen is opened. `PhiTenants` behaves the same
 * way. It is recorded rather than papered over, because a reader who assumes
 * this case is handled would not go looking for it.
 */
final class TermsAcceptances extends Component
{
    /**
     * The business number an admin typed. A string, because it comes from a text
     * input and an unparseable one has to be answerable rather than fatal.
     */
    public string $lookup = '';

    /**
     * The business in view, once one has resolved.
     *
     * ⚠️ `#[Locked]` BECAUSE `lookUp()` IS THE ONLY THING THAT MAY SET IT, AND
     * THE AUDIT ROW IS WRITTEN THERE. `PhiTenants` records the same reasoning at
     * length: without the attribute this is an ordinary public property that
     * arrives in the update payload, so anybody who can reach this component
     * could set it to any integer and let `render()` read that tenant's
     * acceptance proof with `lookUp()` never called and nothing recorded
     * anywhere. That is the exact harm the `business.viewed_by_staff` entry
     * exists to close, walked around rather than through.
     */
    #[Locked]
    public ?int $businessId = null;

    public function mount(): void
    {
        // Repeated on the component rather than left to the route's `can:`
        // middleware — decision 630: a route-gate test passes while `mount()` is
        // wide open, because `can:` refuses during route matching and the
        // component never runs.
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Put a business in view.
     *
     * A miss says so plainly rather than 404ing the screen: the admin has typed
     * a number, and "there is no business 4180" is the answer to that, where a
     * 404 reads as the screen itself being broken.
     *
     * ⚠️ IT CAN THROW, WHICH IS CORRECT RATHER THAN OVERSIGHT. The audit write
     * is not wrapped and `$this->businessId` is set after it, so an unwritable
     * `audit_log` fails the whole action instead of showing the record. Catching
     * it would put a tenant's acceptance proof on screen with nothing anywhere
     * recording the read, which is the one thing the entry exists to prevent.
     */
    public function lookUp(AuditService $audit): void
    {
        $this->authorize(AdminAccess::GATE);

        $id = (int) trim($this->lookup);

        if ($id <= 0) {
            $this->businessId = null;
            Toaster::error('Enter a business number.');

            return;
        }

        $business = $this->resolve($id);

        if (! $business instanceof Business) {
            $this->businessId = null;
            Toaster::error("There is no business {$id}.");

            return;
        }

        Tenancy::actingAs(
            $id,
            fn (): AuditLogEntry => $audit->record('business.viewed_by_staff', $this->actor(), $business),
        );

        $this->businessId = $id;
    }

    public function render(Acceptances $acceptances): View
    {
        $this->authorize(AdminAccess::GATE);

        $business = $this->businessId === null ? null : $this->viewing(
            fn (): ?Business => Business::query()->find($this->businessId),
        );

        return view('livewire.admin.terms-acceptances', [
            'business' => $business,
            // ⚠️ ONE `Tenancy::actingAs()` PER READ RATHER THAN ONE AROUND ALL
            // THREE, matching `PhiTenants::render()`. The nesting is safe —
            // actingAs() restores in a finally block — and keeping each read in
            // its own call means no read here can be reached with the tenant
            // established by a different one.
            'standing' => $business instanceof Business
                ? $this->viewing(fn (): array => $this->standing($acceptances, $business))
                : [],
            'history' => $business instanceof Business
                ? $this->viewing(fn (): array => $acceptances->historyFor($business))
                : [],
        ]);
    }

    /**
     * One answer per signup document, with a null where there is no row.
     *
     * ⚠️ DRIVEN BY `SignupTerms::DOCUMENTS` RATHER THAN BY WHAT THE TENANT
     * HAPPENS TO HAVE. Looping the rows would render a tidy panel that silently
     * omits the document nobody accepted — which is the one a reviewer is asking
     * about. A fourth document therefore appears here the day it is added to the
     * constant, unaccepted and saying so, rather than being invisible.
     *
     * @return list<array{type: LegalDocumentType, acceptance: ?TermsAcceptance}>
     */
    private function standing(Acceptances $acceptances, Business $business): array
    {
        return array_map(
            fn (LegalDocumentType $type): array => [
                'type' => $type,
                'acceptance' => $acceptances->latestFor($business, $type),
            ],
            SignupTerms::DOCUMENTS,
        );
    }

    /**
     * Run a callback as the business in view.
     *
     * ⚠️ THIS IS THE CROSS-TENANT STEP, IN ONE PLACE. `Tenancy::actingAs()`
     * restores the previous tenant in a finally block, so a throwing callback
     * cannot leave the admin's session pointed at somebody else's business.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function viewing(callable $callback): mixed
    {
        $id = $this->businessId ?? throw new InvalidArgumentException(
            'Look up a business first.'
        );

        return Tenancy::actingAs($id, $callback);
    }

    /**
     * The business a typed number resolves to, or null.
     *
     * Its own tenant context, because `businesses` is FORCE ROW LEVEL SECURITY
     * and a lookup with the admin's own tenant established would answer "no" for
     * every business but their own.
     */
    private function resolve(int $id): ?Business
    {
        return Tenancy::actingAs(
            $id,
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );
    }

    /**
     * Who is reading this, for the audit entry.
     *
     * An actor label, matching `PhiTenants` and `ReviewQueue`: automation is a
     * first-class actor in this log, so a user foreign key would have nothing to
     * point at for most entries.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
