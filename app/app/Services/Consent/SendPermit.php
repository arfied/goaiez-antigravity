<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\CapturedBy;
use App\Enums\ConsentType;
use App\Enums\MessagingLane;
use App\Enums\OutreachChannel;
use App\Models\ConsentRecord;
use Carbon\CarbonImmutable;

/**
 * Authorisation to send one message on one channel to one contact.
 *
 * WHY THIS IS A TYPE AND NOT A BOOLEAN. COMP-01 requires that no message send
 * without a consent record, "enforced at the service layer" — but nothing in
 * this application sends anything yet. Row 4 owns messaging. So this slice has
 * to bind an author who has not read the ticket, the spec, or this docblock.
 *
 * A bool cannot do that. `if ($consent->has(...))` is a line somebody can forget
 * to write and no reviewer can see missing. A required parameter of a type only
 * this service can mint is a line somebody cannot forget, because the code does
 * not run without it. Decision 220 chose the same shape for the place resolver's
 * confirmation, "so the language enforces it instead of a line someone can
 * delete".
 *
 * WHY THE CONSTRUCTOR IS PRIVATE. A forgeable permit is decoration. `grant()` is
 * the only factory, and an ArchitectureTest lint confines callers of the
 * literal `SendPermit::grant` call form to app/Services/Consent/ — class
 * aliasing, a variable holding the class name (`$class::grant()`), and
 * reflection are deliberate evasions a textual lint cannot reach, by design.
 * That lint is the one that matters today: "senders must accept a permit"
 * matches zero files until row 4 exists, and a lint matching nothing passes
 * vacuously — the failure decision 256 recorded when PlacesSpendTest stayed
 * green through a wrong price.
 *
 * WHY IT CARRIES PROVENANCE. COMP-01's third acceptance criterion is that
 * consent proof is retrievable for any contact. When row 4 writes an
 * outreach_messages row it records the permit it sent under, so "why did we text
 * this person" is answered by a foreign key rather than reconstructed later from
 * timestamps.
 *
 * WHY IT NAMES ITS DESTINATION. A permit carrying only "consent exists" proves
 * half of what it claims: `send(Customer $c, SendPermit $p)` would type-check
 * with a permit minted for somebody else entirely, and nothing in the signature
 * could tell. So the permit carries the customer it was minted for and the
 * identifier the message goes to. Row 4 sends to `$permit->identifier` rather
 * than re-deriving one, which removes the second way to get this wrong — a
 * sender that reads the phone number off the model can read a *different* phone
 * number from the one consent was checked against.
 *
 * WHY IT CARRIES $consentType. `29` §2 rules 6-7 turn on express versus express
 * written, and a composer holding only a record id would have to re-query to
 * find out. This whole class exists because row 4's author will not do the thing
 * they have to remember to do. It is nullable because the column is: a record
 * with no stated consent type must not authorise marketing, and row 4 has to be
 * able to see that rather than infer it from an absence.
 *
 * WHY $grantedAt IS CarbonImmutable, NOT Carbon. `readonly` stops the property
 * from being reassigned, but a mutable Carbon instance is still mutable in
 * place — `$permit->grantedAt->addDay()` would silently rewrite it out from
 * under the object holding it. A permit exists to record *when* consent
 * authorised a send; a provenance value that can be quietly rewritten is not
 * provenance.
 */
final readonly class SendPermit
{
    private function __construct(
        public int $consentRecordId,
        public int $customerId,
        public string $identifier,
        public OutreachChannel $channel,
        public MessagingLane $lane,
        public CapturedBy $capturedBy,
        public ?ConsentType $consentType,
        public CarbonImmutable $grantedAt,
    ) {}

    /**
     * The only way a permit comes into existence.
     *
     * The identifier is passed in rather than read off the record, because the
     * record does not hold one — consent is per customer and channel, while the
     * phone number or address lives on the customer. `ConsentService::permit()`
     * has already resolved and trimmed it through `identifierFor()`, and that
     * resolved value is the one suppression was checked against, so it is the
     * only one safe to send to.
     *
     * @internal Callers outside App\Services\Consent are a build failure — see
     *           the "nothing outside the consent service mints a send permit"
     *           lint in tests/Feature/Architecture/ConsentTest.php.
     */
    public static function grant(ConsentRecord $record, string $identifier): self
    {
        return new self(
            consentRecordId: $record->id,
            customerId: $record->customer_id,
            identifier: $identifier,
            channel: $record->channel,
            lane: $record->captured_by->lane(),
            capturedBy: $record->captured_by,
            consentType: $record->consent_type,
            grantedAt: CarbonImmutable::now(),
        );
    }
}
