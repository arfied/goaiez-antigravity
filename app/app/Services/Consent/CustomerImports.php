<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\OutreachChannel;
use App\Enums\ProofHashDomain;
use App\Enums\UsState;
use App\Models\Customer;
use App\Models\CustomerImport;
use App\Services\AuditService;
use App\Services\Crm\CustomerEditor;
use App\Support\Identifier;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place a tenant's customer list enters this system.
 *
 * ⚠️ **READ DECISIONS 549 TO 551 FIRST.** The owner approved reactivation
 * messaging to a tenant's own customer list on an EBR attestation plus
 * indemnity. The argument recorded against that is in 549. This service does not
 * settle it either way — **it makes the claim evidenced rather than asserted**,
 * which is the half that helps whichever way it lands.
 *
 * ## Four things it deliberately does, and one it deliberately does not
 *
 * **It cannot be called without an attestation.** `import()` takes an
 * {@see ImportAttestation} by type, so there is no path that records a list
 * without recording who claimed it and in what words. Decision 285's `SendPermit`
 * pattern, one table over: a forgotten attestation is a type error rather than a
 * missing line.
 *
 * **It writes `ConsentType::TenantAttested`, never `ExpressWritten`.** The person
 * on the list gave us nothing. Writing the label the TCPA actually requires would
 * put a false record into the one table whose whole job is to be true — see that
 * enum case, which argues it at length because it is the detail most likely to be
 * "simplified" later by somebody who thinks three cases are two too many.
 *
 * **It writes consent per channel, from the resolved customer's own stored
 * identifier.** Decisions 334–337 caught the inverse: a submission carrying
 * somebody else's identifiers wrote consent for a channel the customer had never
 * proved themselves reachable on. Here the same rule means an imported row with
 * only an email never produces SMS consent, however the file was laid out.
 *
 * **It refuses a contact with no usable identifier**, rather than creating a
 * customer nobody can ever reach. `App\Support\Identifier` normalises before
 * anything is stored, because a hash matches only exactly (decisions 424–427).
 *
 * ⚠️ **AND IT IS THE SECOND PATH `customers.region_code` ARRIVES ON** (1594) — a
 * `state` column in an uploaded file, which is a value a human typed about their
 * own customer: imported, never inferred.
 *
 * ⛔ **THAT COLUMN IS REQUIRED AS OF 2026-08-12 (2682), AND IT IS THE ONE
 * BEHAVIOUR OF THIS SERVICE THAT CHANGED SINCE 1612.** Until then an
 * unrecognisable cell landed as null, the row still imported, and the contact
 * kept `StateUnknown` — honest, and it burned whole audiences: a list with no
 * state column produced thousands of contacts every marketing send would refuse,
 * with nothing on any screen saying why. **Now the file is refused instead**,
 * with a message naming how many rows are short. See
 * `refuseContactsWithNoState()`, which argues the conflict this settles, and
 * `fillRegion()`. ⚠️ **No statute is ever guessed either way** — 1595 stands
 * untouched and no area code is read anywhere.
 *
 * ✅ **IT IS NO LONGER A *WRITER* OF THAT COLUMN, AND IT WAS ONE UNTIL 1612.**
 * The write goes through `CustomerEditor::setRegion()` now, so the chokepoint
 * allowlist is one file rather than two and an imported jurisdiction reaches
 * `audit_log` — which it did not before, while `setRegion()`'s docblock was
 * claiming its entry was the only record of why a send was allowed.
 *
 * ⚠️ **IT DOES NOT CHECK SUPPRESSION OR OPT-OUTS, AND THAT IS DELIBERATE.**
 * `ConsentService::permit()` is the single chokepoint that decides whether
 * anybody may be messaged, and it asks the platform-scoped `opt_outs` list
 * before the tenant-owned one (424–427). Adding a second opt-out check here
 * would make two places decide, which is precisely the shape 285 and 424–427
 * were built to avoid — and the two would eventually disagree, with whichever
 * ran first silently winning. **An import records a claim; it never grants
 * permission.**
 */
final class CustomerImports
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly AuditService $audit,
        private readonly ImportStatement $statement,
        private readonly CustomerEditor $editor,
    ) {}

    /**
     * Whether this tenant has ever imported a customer list.
     *
     * The one read `App\Services\Trust\FirstWeekPath` needs — "has the owner
     * already brought their past customers in" — added here rather than
     * queried directly, on 624's rule: a second reader belongs behind the
     * chokepoint, not on its allowlist, because `CustomerImport::` is held to
     * exactly two files by an `ArchitectureTest` lint and this file is already
     * one of them.
     */
    public function hasAnyImport(): bool
    {
        return CustomerImport::query()->exists();
    }

    /**
     * Record an attested list, creating customers and their consent records.
     *
     * @param  list<array{name?: ?string, email?: ?string, phone?: ?string, region?: ?string}>  $contacts
     *
     * @throws InvalidArgumentException when no contact carries a usable identifier
     * @throws ImportStatementUnavailable when the attested wording is not published
     */
    public function import(
        ImportAttestation $attestation,
        array $contacts,
        string $source,
        ?int $locationId = null,
    ): CustomerImport {
        $businessId = Tenancy::idOrFail();

        // ⚠️ FIRST, AND ON THE WRITE PATH RATHER THAN ONLY ON THE SCREEN.
        // `ImportAttestation` validates that a version string is present and
        // unpadded; it cannot know whether the string names anything, because a
        // value object with a database lookup in its constructor is untestable
        // and would couple every caller to a connection. So until this line, an
        // attestation could name `'v1'` with no `v1` anywhere — a record that
        // reads as evidence and resolves to nothing, which is worse than no
        // record at all, because it looks answered.
        //
        // It runs before `resolve()` deliberately: a tenant whose wording is not
        // published should be told that, not told their file is fine and then
        // refused. And it is here rather than only in `ImportCustomers` because
        // decision 398 caught exactly this — a guard in the controller is a
        // guard the next caller skips, and row 4's senders hydrating a list from
        // a queue payload are that caller.
        $this->statement->refuseUnusableVersion($attestation->statementVersion);

        // Resolved before the transaction opens so that a file of unreachable
        // rows fails without leaving an attestation covering nobody — which the
        // row_count CHECK would reject anyway, as a SQLSTATE nobody can act on
        // rather than a message naming the problem.
        $resolved = $this->resolve($contacts);

        if ($resolved === []) {
            throw new InvalidArgumentException(
                'No contact in this list carries a usable email or phone number, so there is '
                .'nobody to import. An attestation covering nobody is not evidence of anything.',
            );
        }

        $this->refuseContactsWithNoState($resolved);

        return DB::transaction(function () use ($attestation, $resolved, $source, $locationId, $businessId): CustomerImport {
            $import = CustomerImport::create([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'statement_version' => $attestation->statementVersion,
                'attested_at' => now(),
                'attested_by' => $attestation->attestedBy,
                // ⚠️ **SCOPED HERE RATHER THAN IN `ImportAttestation`.** That
                // object is the tenant's statement and its `proof` is what they
                // supplied; the domain is a fact about the table the statement
                // lands in, and this is the only writer of it. The four consent
                // and contract records reach the same construction through
                // `ConsentProof`, which this attestation is deliberately not one
                // of — see {@see ProofHashDomain} for the join it breaks.
                'proof' => ProofHash::scope(ProofHashDomain::CustomerImports, $attestation->proof),
                'row_count' => count($resolved),
                'source' => $source,
            ]);

            foreach ($resolved as $contact) {
                $customer = $this->upsertCustomer($contact, $locationId, $attestation->attestedBy);

                foreach ($contact['channels'] as $channel) {
                    $this->consent->record(
                        $customer,
                        $channel,
                        new ConsentCapture(
                            capturedBy: CapturedBy::Tenant,
                            captureSurface: CaptureSurface::Import,
                            consentType: ConsentType::TenantAttested,
                            disclosureVersion: $attestation->statementVersion,
                            method: 'tenant_attested_import',
                            // The import id rather than the attestation's own
                            // proof blob: one lookup away, and it keeps the
                            // tenant's IP hash out of every one of ten thousand
                            // consent rows.
                            proof: ['customer_import_id' => $import->id],
                        ),
                        $attestation->attestedBy,
                    );
                }
            }

            $this->audit->record('customer_list.attested', $attestation->attestedBy, $import, [
                'row_count' => $import->row_count,
                'source' => $source,
                'statement_version' => $attestation->statementVersion,
                'location_id' => $locationId,
            ]);

            return $import;
        });
    }

    /**
     * Refuse the whole list unless every contact on it names a state.
     *
     * ⛔ **DECISION 2682 — AND IT SETTLES A CONFLICT BY TAKING NEITHER SIDE.**
     * T137 §1 `SL-2` asks for the quiet-hours timezone to be resolved from the
     * contact's area code; locked decision **1595 refuses every derivation of
     * `region_code`**, the NPA first among them, because an area code follows
     * the number rather than the person and porting is universal. The owner's
     * answer is that the tenant supplies it: **the jurisdiction is asserted by
     * the party who actually knows it.** ⚠️ **1595 IS NOT REVERSED — IT IS MADE
     * UNNECESSARY ON THIS PATH.** Nothing here or anywhere else may look at an
     * area code, and a file with no state column is now refused rather than
     * quietly producing contacts nobody may ever market to.
     *
     * **The whole file is refused rather than the offending rows skipped**, and
     * that is the same trade 2570 made when it stopped `RunCampaignJob`
     * destroying an audience: a partial import is silent data loss, and the
     * tenant would find out by counting. A refusal naming the number is a thing
     * somebody fixes in their spreadsheet and re-uploads, with nothing lost —
     * 2687's rule in this file, one ruling over.
     *
     * ⚠️ **THIS IS THE ONLY CREATION PATH IT REACHES, AND 2683 SAYS SO IN
     * TERMS.** `App\Services\Feedback\FeedbackSubmission` creates a contact from
     * a review submission with no state and always will — nobody is going to ask
     * a customer leaving a review which state they live in — and any future CRM
     * or integration path may do the same. Those contacts keep a null
     * `region_code`, `ConsentService::stateRefusal()` goes on refusing them
     * marketing with `SendRefusalReason::StateUnknown`, and that is correct
     * rather than a gap this ruling forgot. **A required column on one path is
     * not a guarantee across all of them**, and reading it as one is how
     * `StateUnknown` would get quietly deleted as dead code.
     *
     * @param  list<array{name: ?string, email: ?string, phone: ?string, region: ?UsState, channels: list<OutreachChannel>}>  $resolved
     *
     * @throws InvalidArgumentException when any contact has no recognisable state
     */
    private function refuseContactsWithNoState(array $resolved): void
    {
        $missing = count(array_filter(
            $resolved,
            static fn (array $contact): bool => $contact['region'] === null,
        ));

        if ($missing === 0) {
            return;
        }

        $total = count($resolved);

        // ⚠️ The two sentences are different because the two fixes are: a file
        // with no state column at all is a different job from a file with
        // eleven blank cells and a `Fla.` in it, and "some of your rows are
        // wrong" sends the first tenant looking through 5,000 rows for them.
        $problem = $missing === $total
            ? ($total === 1
                ? 'The one contact in that file has no state'
                : 'None of the '.number_format($total).' contacts in that file has a state')
            : number_format($missing).' of the '.number_format($total)
                .' contacts in that file '.($missing === 1 ? 'has' : 'have').' no state';

        throw new InvalidArgumentException(
            $problem.'. Add a column headed "state" with the two-letter code for where each '
            .'customer lives — that is what decides when it is legal to text them, and it is '
            .'not something we can work out from a phone number. Nothing was imported.',
        );
    }

    /**
     * Normalise the file into contacts we can actually reach.
     *
     * @param  list<array{name?: ?string, email?: ?string, phone?: ?string, region?: ?string}>  $contacts
     * @return list<array{name: ?string, email: ?string, phone: ?string, region: ?UsState, channels: list<OutreachChannel>}>
     */
    private function resolve(array $contacts): array
    {
        $resolved = [];

        foreach ($contacts as $contact) {
            $email = Identifier::email($contact['email'] ?? null);
            $phone = Identifier::phone($contact['phone'] ?? null);

            if ($email === null && $phone === null) {
                continue;
            }

            // Email and SMS only. WhatsApp is a separate opt-in with its own
            // template rules, and inferring it from a phone number in a
            // spreadsheet is exactly the over-claim this service exists not to
            // make.
            $channels = [];

            if ($email !== null) {
                $channels[] = OutreachChannel::Email;
            }

            if ($phone !== null) {
                $channels[] = OutreachChannel::Sms;
            }

            $resolved[] = [
                'name' => $this->trimmed($contact['name'] ?? null),
                'email' => $email,
                'phone' => $phone,
                // ⚠️ AN UNRECOGNISABLE CELL STILL BECOMES NULL HERE, AND
                // `refuseContactsWithNoState()` IS WHAT REFUSES IT (1594, 2682).
                // `Ontario`, `Fla.` or an empty column leaves the contact with
                // no jurisdiction; until 2682 that imported and the contact kept
                // `StateUnknown`, and now the whole file is refused with a count.
                // The null is deliberately still produced rather than thrown
                // from inside the loop, so the message can say "1,200 of 5,000"
                // rather than naming whichever row happened to be first.
                // ⛔ Never a guessed statute either way. `UsState::normalise()`
                // uppercases and trims first, because a stored lowercase `fl`
                // matches no rule while looking perfectly populated.
                'region' => UsState::normalise($contact['region'] ?? null),
                'channels' => $channels,
            ];
        }

        return $resolved;
    }

    /**
     * Find this tenant's existing customer, or create one.
     *
     * Matched on a normalised identifier rather than on the raw column, because
     * `Bob@Example.COM ` and `bob@example.com` are one person and a second row
     * for them means two consent histories, one of which a send path will miss.
     *
     * @param  array{name: ?string, email: ?string, phone: ?string, region: ?UsState, channels: list<OutreachChannel>}  $contact
     */
    private function upsertCustomer(array $contact, ?int $locationId, string $actor): Customer
    {
        $customer = Customer::query()
            ->where(function ($query) use ($contact): void {
                if ($contact['email'] !== null) {
                    $query->orWhere('email', $contact['email']);
                }

                if ($contact['phone'] !== null) {
                    $query->orWhere('phone', $contact['phone']);
                }
            })
            ->first();

        if ($customer === null) {
            $customer = new Customer([
                'location_id' => $locationId,
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
            ]);

            $customer->save();

            return $this->fillRegion($customer, $contact['region'], $actor);
        }

        // An existing customer is filled in, never overwritten. A list upload is
        // the least authoritative source of a name in this system, and letting
        // it win would let a stale spreadsheet rename somebody who has since
        // told us themselves.
        $customer->fill([
            'name' => $customer->name ?? $contact['name'],
            'email' => $customer->email ?? $contact['email'],
            'phone' => $customer->phone ?? $contact['phone'],
        ]);

        $customer->save();

        return $this->fillRegion($customer, $contact['region'], $actor);
    }

    /**
     * Write the imported state, if the contact has no answer of its own.
     *
     * ⚠️ **THROUGH `CustomerEditor::setRegion()`, WHICH IS WHY THIS SERVICE IS
     * NO LONGER ON THE CHOKEPOINT ALLOWLIST** (1612). It wrote the column
     * directly until this fix wave, which meant an imported jurisdiction reached
     * `audit_log` **nowhere** — while `setRegion()`'s own docblock claimed its
     * entry was *"the only thing that can later answer why a send was allowed"*.
     * For every contact that arrived on a list, that claim resolved to nothing:
     * 314–316's shape, inside the slice that quotes 314–316. 624's rule settles
     * which way to fix it — ask whether this caller belongs behind the service
     * rather than widen the allowlist, and it does.
     *
     * ⚠️ **THE GAP-FILL RULE IS THIS GUARD AND IT MATTERS MORE THAN THE NAME'S**
     * (1594). A stale export overwriting a state the owner typed on the profile
     * — or that an earlier, better file supplied — would move a named person
     * into another jurisdiction's quiet hours, with nothing on any screen saying
     * so and the send path reading the new one on the next campaign. It is the
     * guard rather than `??=` now, because `setRegion()` sets rather than fills.
     *
     * ⚠️ **A CLEARED STATE IS RE-FILLABLE BY THE NEXT IMPORT, AND NOTHING HERE
     * CAN TELL THE TWO APART** (1613). An owner who empties the field on the
     * profile is saying *"I do not know"*, and a contact nobody ever answered
     * for says the same thing — both are a null column, and `customers` has no
     * "answered and then unanswered" state to read. So a later file carrying a
     * `state` cell fills it again. That is stated here rather than modelled: the
     * alternative is a second column whose only reader would be this method, and
     * the direction of the mistake is a *refusal* becoming a real jurisdiction
     * the owner's own list supplied, not a guess. Whoever needs the distinction
     * adds the column and the audit read together.
     */
    private function fillRegion(Customer $customer, ?UsState $region, string $actor): Customer
    {
        if ($customer->region_code !== null || $region === null) {
            return $customer;
        }

        return $this->editor->setRegion($customer, $region->value, $actor);
    }

    private function trimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
