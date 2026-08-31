<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\OutreachChannel;
use App\Exceptions\MailNotDeliverable;
use App\Services\Config\DefaultsRegistry;

/**
 * Builds the CAN-SPAM footer, and refuses to build one it cannot complete —
 * T176 P21.
 *
 * ⛔ **THE REFUSAL IS THE FEATURE.** A footer with `[your address here]` in it,
 * or with the address line silently missing, is worse than no footer: it looks
 * like the obligation was met, so nobody looks again. `mail.postal_address`
 * carries no seed in `DefaultsManifest` (decision 4019) precisely because every
 * possible seed for an address is a false statement, so an unconfigured platform
 * reaches {@see self::postalAddress()} and stops there.
 *
 * ⚠️ **THIS CLASS IS WHERE "NO ADDRESS" BECOMES "NO SEND", AND THAT IS THE WHOLE
 * FAIL-CLOSED PROPERTY.** The registry answers `null`, which on its own is a
 * value a caller could shrug at — the manifest's own note calls out that this
 * key's absence is *not* a correct resting state, unlike its two neighbours.
 * Nothing else enforces it.
 */
final readonly class CanSpamFooters
{
    public function __construct(
        private DefaultsRegistry $defaults,
        private UnsubscribeLinks $links,
    ) {}

    /**
     * The sender's physical mailing address.
     *
     * ⚠️ **CALLED FOR ITS THROW AS WELL AS FOR ITS VALUE**, which is why it is
     * public. `PlatformMailer::sendToCustomer()` asks before it writes anything,
     * so a commercial send with no address fails *inside the caller's
     * transaction* and rolls the `outreach_messages` row back — rather than
     * committing a row that says `Queued` for a message the delivery job will
     * refuse for ever.
     *
     * @throws MailNotDeliverable when no address is set, or the row says nothing
     */
    public function postalAddress(): string
    {
        $address = $this->defaults->value('mail.postal_address');

        if (! is_string($address) || trim($address) === '') {
            throw MailNotDeliverable::noPostalAddress();
        }

        return trim($address);
    }

    /**
     * The footer for one commercial message to one address.
     *
     * ⚠️ **THE TOKEN IS MINTED HERE, AT DELIVERY, FROM THE ADDRESS THE MESSAGE
     * IS ACTUALLY GOING TO.** Not from a contact row and not from the queue
     * payload — {@see UnsubscribeClaim} for why the address consent was decided
     * against is the only one an opt-out may suppress.
     *
     * @throws MailNotDeliverable when there is no tenant to attribute the
     *                            opt-out to, which is a commercial message this
     *                            application has no way to let anybody leave
     */
    public function for(string $address, PlatformMailIdentity $identity, string $platformName): CanSpamFooter
    {
        if ($identity->businessId === null) {
            throw MailNotDeliverable::commercialWithoutOptOut();
        }

        return new CanSpamFooter(
            senderName: $platformName,
            businessName: $identity->businessName,
            postalAddress: $this->postalAddress(),
            unsubscribeUrl: route('mail.unsubscribe', [
                'token' => $this->links->mint(
                    $identity->businessId,
                    $address,
                    OutreachChannel::Email,
                ),
            ]),
        );
    }
}
