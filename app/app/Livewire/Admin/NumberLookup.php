<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\ComplianceList;
use App\Enums\LiftSource;
use App\Enums\MessagingLane;
use App\Enums\NumberRole;
use App\Models\Business;
use App\Models\PhoneNumber;
use App\Services\AuditService;
use App\Services\Consent\NumberDossier;
use App\Services\Consent\OwnerConsentService;
use App\Services\Consent\SuppressionRegistry;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Who a phone number is, starting from the phone number — wave 40 lane C,
 * decision 10880.
 *
 * ⛔ **A CARRIER COMPLAINT NAMES A NUMBER AND NOTHING ELSE, AND EVERY SCREEN IN
 * THIS CONSOLE WAS KEYED ON A BUSINESS.** {@see OwnerNotifyConsents} described
 * itself as *"the answer to a carrier's 'this number never agreed to be
 * texted'"* and resolved its input with `(int) trim($this->lookup)` — so typing
 * the one thing the carrier actually gave you returned *"Enter a business
 * number."* That screen is now correctly described as what it is (the record
 * for a business you can already name) and this is the index by number that
 * reaches it.
 *
 * ⚠️ **IT ANSWERS A LIST, NOT A BUSINESS.**
 * {@see OwnerConsentService::businessesFor()} returns a list deliberately: one
 * person owning two businesses and reusing one mobile for both is a real shape,
 * because `businesses.owner_user_id` carries no uniqueness constraint. A screen
 * rendering *"the"* business would be wrong on exactly the case that method
 * exists for.
 *
 * ## ⛔ It shows, and it does nothing else
 *
 * No action anywhere: no stop, no start, no lift, no correction. Releasing
 * somebody from a suppression is `ConsentService::lift()` on a named authority
 * with an audit row, and putting a button for it on a page whose whole subject
 * is a stranger's phone number would put the one control that *unblocks* a
 * person one click from a text box.
 *
 * ## ⚠️ What it does with the number an operator typed
 *
 * ⛔ **NOTHING. IT IS NEVER STORED, ANYWHERE.** A search box that files every
 * number staff ever type is a new store of other people's personal data with no
 * retention rule and no erasure path, which is the opposite of what
 * `CLAUDE.md`'s tiebreaker (2) asks for. What IS recorded is the ordinary
 * `business.viewed_by_staff` entry in each resolved tenant's own `audit_log` —
 * the same entry {@see OwnerNotifyConsents::lookUp()} and
 * {@see TermsAcceptances} write, whose subject is the business rather than the
 * number.
 *
 * ⚠️ **SO A LOOKUP THAT RESOLVES TO NO TENANT IS RECORDED NOWHERE, AND THAT IS
 * ARGUED RATHER THAN OVERLOOKED.** `AuditService::record()` calls
 * `Tenancy::idOrFail()` because `audit_log` is tenant-owned with RLS on
 * `business_id`; a platform-scoped read belongs to no tenant, and forcing one
 * would file a stranger's phone number under an arbitrary business.
 * {@see SuppressionRegistry}'s own docblock reaches the
 * same conclusion for a federal register import, in the same words.
 *
 * ⚠️ **NO PERSONAL DATA REACHES A TOAST** (decision 104). The only toast here
 * says the typed text is not a phone number, and it does not repeat it.
 */
final class NumberLookup extends Component
{
    /**
     * What an operator typed, in whatever form the carrier's email used.
     *
     * A string rather than anything narrower, because an unreadable one has to
     * be answerable rather than fatal — `numbers:history`'s own argument.
     */
    public string $lookup = '';

    /**
     * The number in view, in the one stored form, once it has resolved.
     *
     * ⚠️ `#[Locked]` FOR {@see OwnerNotifyConsents::$businessId}'s REASON,
     * arrived at from a different direction. Without it this is an ordinary
     * public property arriving in the update payload, so a client could set it
     * directly and have `render()` resolve tenants from it with `lookUp()`
     * never called — which means with **nothing written to any tenant's audit
     * log**. The lock is what makes the audit unskippable rather than
     * customary.
     */
    #[Locked]
    public ?string $e164 = null;

    public function mount(): void
    {
        // Repeated on the component rather than left to the route's `can:`
        // middleware — `TermsAcceptances`' reason: a route-gate test passes
        // while `mount()` is wide open, because `can:` refuses during route
        // matching and the component never runs.
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Put a number in view, and record the read against every tenant it names.
     *
     * ⚠️ IT CAN THROW, WHICH IS CORRECT — the audit writes are not wrapped and
     * `$this->e164` is set after them, so an unwritable `audit_log` fails the
     * whole action instead of showing the record.
     */
    public function lookUp(AuditService $audit, NumberDossier $dossier, OwnerConsentService $owner): void
    {
        $this->authorize(AdminAccess::GATE);

        $e164 = $dossier->normalise($this->lookup);

        if ($e164 === null) {
            $this->e164 = null;
            // ⚠️ THE TYPED TEXT IS DELIBERATELY NOT REPEATED BACK (104). It is
            // whatever somebody pasted out of a carrier email, and a toast is
            // the one surface here with no gate around it once rendered.
            Toaster::error('That is not a phone number this application can read.');

            return;
        }

        foreach ($this->tenantsNaming($e164, $owner) as $id) {
            Tenancy::actingAs($id, function () use ($audit, $id): void {
                $business = Business::query()->whereKey($id)->first();

                if ($business instanceof Business) {
                    $audit->record('business.viewed_by_staff', $this->actor(), $business);
                }
            });
        }

        $this->e164 = $e164;
    }

    public function render(NumberDossier $dossier, OwnerConsentService $owner): View
    {
        $this->authorize(AdminAccess::GATE);

        $e164 = $this->e164;

        if ($e164 === null) {
            return view('livewire.admin.number-lookup', [
                'e164' => null,
                'readable' => $dossier->readable(),
                'record' => [],
                'blocksEverything' => [],
                'blocksMarketing' => [],
                'neverLoaded' => null,
                'owners' => [],
                'ours' => [],
            ]);
        }

        $refusals = $dossier->registerRefusals($e164);

        return view('livewire.admin.number-lookup', [
            'e164' => $e164,
            'readable' => $dossier->readable(),
            'record' => $this->timeline($dossier, $e164),
            // ⚠️ SPLIT HERE RATHER THAN IN THE VIEW, and not for tidiness: a
            // register that blocks BOTH purposes and one that blocks marketing
            // only are two different sentences to send a carrier, and
            // `array_diff()` cannot separate them — it string-casts, and a
            // backed enum is not a string.
            'blocksEverything' => array_map($this->registerSentence(...), $refusals['transactional']),
            'blocksMarketing' => array_map(
                $this->registerSentence(...),
                array_values(array_filter(
                    $refusals['marketing'],
                    static fn (ComplianceList $list): bool => ! in_array($list, $refusals['transactional'], true),
                )),
            ),
            // ⚠️ **JOINED HERE RATHER THAN LOOPED IN THE TEMPLATE**, because an
            // inline `@foreach` inside a sentence renders "…changed hands ."
            // with a space before the stop — measured in a real browser, not
            // read. A list read as prose is prose.
            'neverLoaded' => $this->sentenceList(
                array_map($this->registerSentence(...), $dossier->registersNeverLoaded()),
            ),
            'owners' => $this->ownerRows($e164, $owner),
            'ours' => $this->inventoryRows($e164),
        ]);
    }

    /**
     * Every business this number names, once, in a stable order.
     *
     * ⚠️ **THE UNION OF BOTH PANELS, DEDUPLICATED, SO THE AUDIT MATCHES WHAT
     * THE PAGE ACTUALLY SHOWS.** A number can be an account holder's mobile
     * *and* — for a tenant who brought their own — a row in the sending
     * inventory. Auditing one panel and rendering two would leave a read with
     * no trace in the tenant whose data it was.
     *
     * @return list<int>
     */
    private function tenantsNaming(string $e164, OwnerConsentService $owner): array
    {
        $ids = array_merge(
            $owner->businessesFor($e164),
            $this->inventoryBusinessIds($e164),
        );

        $unique = array_values(array_unique($ids));
        sort($unique);

        return $unique;
    }

    /**
     * The account-holder side: one row per business that has registered this
     * number as its owner-notify mobile.
     *
     * ⚠️ **THROUGH {@see OwnerConsentService}, NEVER THE MODEL** — that table
     * carries no RLS predicate of its own (`USING (true)`, so that this
     * tenant-less reverse lookup is possible at all), and the "one file touches
     * it" lint in `OwnerChannelTest.php` is what stands in for the predicate.
     *
     * @return list<array{id: int, name: string, stopped: bool}>
     */
    private function ownerRows(string $e164, OwnerConsentService $owner): array
    {
        $rows = [];

        foreach ($owner->businessesFor($e164) as $id) {
            $row = Tenancy::actingAs($id, function () use ($id, $owner): ?array {
                $business = Business::query()->whereKey($id)->first();

                if (! $business instanceof Business) {
                    return null;
                }

                $number = $owner->currentNumberFor($business);

                return [
                    'id' => $id,
                    'name' => (string) $business->name,
                    'stopped' => $number !== null && $number->stopped_at !== null,
                ];
            });

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * The other side of a carrier complaint: a number that is one of OURS.
     *
     * ⛔ **WITHOUT THIS PANEL THE SCREEN GIVES A DANGEROUSLY WRONG ANSWER.** A
     * carrier forwarding a complaint quotes two numbers — the subscriber's and
     * the one that texted them — and an operator who pasted the second would
     * have read *"nothing on record"* about a number in this platform's own
     * sending inventory, and said so to the carrier. `numbers:history` has
     * answered this since wave 38 and only from a shell.
     *
     * ⚠️ **EVERY ROW THE E.164 HAS EVER HELD**, `NumberHistory`'s own rule: a
     * number can be retired and re-provisioned, leaving two `phone_numbers`
     * rows sharing one E.164, and the operator is asking about the number
     * rather than about whichever row happens to be live.
     *
     * ⚠️ **THE KEY IS `numberRole` AND NOT `role`, WHICH IS NOT FUSSINESS**:
     * `StaffTest`'s users.role chokepoint scans every file in `app/` for a
     * write to a key called `role`, because two tables in this schema carry
     * that column and the one it guards decides who may open this console. An
     * array key on a view payload is invisible to that distinction, and the
     * lint is right to refuse rather than be widened — its own message says so.
     *
     * @return list<array{state: string, numberRole: string, lane: string, business: ?string, businessId: ?int}>
     */
    private function inventoryRows(string $e164): array
    {
        $rows = [];

        foreach ($this->inventory($e164) as $number) {
            $businessId = $number->business_id;

            $name = $businessId === null ? null : Tenancy::actingAs(
                $businessId,
                static fn (): ?string => Business::query()->whereKey($businessId)->value('name'),
            );

            $rows[] = [
                'state' => $number->state->sentence(),
                'numberRole' => $this->roleSentence($number->role),
                'lane' => $this->laneSentence($number->lane),
                'business' => $name === null ? null : (string) $name,
                'businessId' => $businessId,
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function inventoryBusinessIds(string $e164): array
    {
        $ids = [];

        foreach ($this->inventory($e164) as $number) {
            if ($number->business_id !== null) {
                $ids[] = $number->business_id;
            }
        }

        return $ids;
    }

    /**
     * @return Collection<int, PhoneNumber>
     */
    private function inventory(string $e164): Collection
    {
        // `phone_numbers`' policy is `platform_and_tenant_readable` — see its
        // creating migration — precisely so that an inbound event arriving with
        // no tenant can be attributed. This is that read, from a screen.
        return PhoneNumber::query()
            ->where('e164', $e164)
            ->orderBy('id')
            ->get();
    }

    private function roleSentence(NumberRole $role): string
    {
        return match ($role) {
            NumberRole::Primary => "One business\u{2019}s own number",
            NumberRole::Extension => 'An extra number for one business',
            NumberRole::SharedPool => 'A number shared across businesses',
        };
    }

    private function laneSentence(?MessagingLane $lane): string
    {
        return match ($lane) {
            MessagingLane::Platform => 'Sends under our own carrier registration',
            MessagingLane::Tenant => "Sends under the business\u{2019}s own carrier registration",
            MessagingLane::None => 'Sends nothing',
            null => 'Not recorded',
        };
    }

    /**
     * Every refusal and every release against this number, as one list,
     * newest first.
     *
     * ⛔ **ONE TIMELINE AND NOT TWO LISTS, WHICH IS THE ANSWER TO THE QUESTION
     * RATHER THAN A LAYOUT CHOICE.** `opt_outs` is append-only and a release is
     * a `suppression_lifts` row beside it, so two separate lists put "they
     * asked us to stop" and "and then they asked us to start again" in
     * different places on the page and let an operator read either alone. The
     * order — refusal, release, refusal — IS the answer a carrier wants.
     *
     * ⚠️ **FLATTENED TO ROWS, SO NO MODEL REACHES THE TEMPLATE.** Both tables
     * refuse `updating` and `deleting` in the model; handing a Blade file a
     * live instance of either is one `->save()` away from an exception in a
     * view.
     *
     * @return list<array{key: string, kind: string, what: string, who: ?string, note: ?string, when: ?string, sortId: int}>
     */
    private function timeline(NumberDossier $dossier, string $e164): array
    {
        $rows = [];

        foreach ($dossier->stops($e164) as $stop) {
            $rows[] = [
                'key' => 'stop-'.$stop->id,
                'kind' => 'refused',
                'what' => $stop->reason_class->sentence(),
                'who' => null,
                'note' => null,
                'when' => $stop->created_at?->format('j F Y, H:i'),
                'sortId' => $stop->id,
            ];
        }

        foreach ($dossier->lifts($e164) as $lift) {
            $rows[] = [
                'key' => 'lift-'.$lift->id,
                'kind' => 'released',
                'what' => $this->releaseSentence($lift->source),
                'who' => $lift->actor,
                'note' => $lift->note,
                'when' => $lift->created_at?->format('j F Y, H:i'),
                'sortId' => $lift->id,
            ];
        }

        // ⚠️ **BY ID, NOT BY `created_at`.** A carrier STOP and the START that
        // follows it can land in the same second — a redelivery, a webhook
        // retry — and a timestamp sort would then render them in whichever
        // order the database happened to return, which is the one ordering that
        // reverses the meaning of the page. Both tables are append-only with a
        // monotonic key, so the key is the order things happened.
        usort($rows, static fn (array $a, array $b): int => $b['sortId'] <=> $a['sortId']);

        return $rows;
    }

    /**
     * Two or more phrases as one English list, or null when there are none.
     *
     * @param  list<string>  $phrases
     */
    private function sentenceList(array $phrases): ?string
    {
        if ($phrases === []) {
            return null;
        }

        if (count($phrases) === 1) {
            return $phrases[0];
        }

        $last = array_pop($phrases);

        return implode(', ', $phrases).' and '.$last;
    }

    /**
     * What a register is, in words rather than in its column value.
     *
     * ⚠️ **A `match` WITH NO DEFAULT**, like the three below it. A fifth
     * `ComplianceList` case must be given a sentence rather than inheriting a
     * plausible wrong one — and the wrong inherited answer here is a register
     * described to a carrier as something it is not.
     */
    private function registerSentence(ComplianceList $list): string
    {
        return match ($list) {
            ComplianceList::FederalDnc => 'the federal Do Not Call registry',
            ComplianceList::StateDnc => 'a state Do Not Call registry',
            ComplianceList::Litigator => 'the list of people who sue over messages',
            ComplianceList::ReassignedNumber => 'the register of numbers that have changed hands',
        };
    }

    /**
     * On whose authority a refusal was released.
     */
    private function releaseSentence(LiftSource $source): string
    {
        return match ($source) {
            // ⚠️ NOT "they texted START" — `LiftSource` is shared with email,
            // where the same case covers a resubscribe, and a sentence naming
            // the wrong channel on a carrier's own question is worse than a
            // vaguer one.
            LiftSource::CarrierStart => 'They asked us to start again.',
            LiftSource::OperatorAction => 'Released by a member of staff.',
        };
    }

    /**
     * Who is reading this, for the audit entry — `TermsAcceptances`' and
     * `OwnerNotifyConsents`' convention: automation is a first-class actor in
     * this log, so a user foreign key would have nothing to point at for most
     * entries.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
