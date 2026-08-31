<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The links a business gives its assistant to hand out (T176 P6, R12–R14).
 *
 * ⛔ **R12 IS WHY THIS IS A LIST OF LINKS AND NOT A LIST OF INTEGRATIONS.** The
 * links-and-conversation law: *"the agent has no hooks into any tenant system —
 * no calendar API, no payment gateway, no job tracker. Booking = send the
 * tenant's own booking URL. Payment = send the tenant's own payment URL."* Every
 * case here is a URL the business already owns, and adding a case that implies
 * the platform *doing* something (taking a payment, holding a slot) is the line
 * R12 draws. The platform never asserts a payment happened.
 */
enum TenantLinkKind: string
{
    /**
     * Where a customer books — the business's own scheduler.
     *
     * Grounds agent skill 5. Absent, the skill is absent too (R13): the agent
     * captures preferred times and hands off, rather than inventing an
     * appointment.
     */
    case Booking = 'booking';

    /**
     * Where a customer pays — the business's own payment page.
     *
     * Grounds skill 6, the call-out fee. ⚠️ A reply saying they have paid is
     * recorded **unverified** and the owner is notified to check their own
     * system (R12). Nothing on this link tells us an amount arrived.
     */
    case Payment = 'payment';

    /**
     * A named document the business shares — price sheet, brochure, intake
     * form, licence, warranty, menu.
     *
     * Grounds skill 7, and it is the one kind a business may hold many of, so
     * it is the only case addressed by slug as well as kind.
     */
    case Document = 'document';

    /**
     * Whether a business may hold more than one link of this kind.
     */
    public function isMultiple(): bool
    {
        return match ($this) {
            self::Document => true,
            self::Booking, self::Payment => false,
        };
    }

    /**
     * What the agent skill grounded on this link cannot do without it (R13).
     *
     * Used in the "Teach your assistant" step (§2.4) to say what stays switched
     * off, in outcome language rather than feature language.
     */
    public function missingCapability(): string
    {
        return match ($this) {
            self::Booking => 'Your assistant will take preferred times and pass them to you, rather than booking.',
            self::Payment => 'Your assistant will not ask anyone to pay before a visit.',
            self::Document => 'Your assistant will offer to have you send anything a customer asks for.',
        };
    }
}
