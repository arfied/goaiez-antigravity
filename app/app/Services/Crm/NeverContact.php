<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\LiftSource;
use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;
use App\Models\Customer;
use App\Models\SuppressionListEntry;
use App\Models\User;
use App\Services\Consent\ConsentService;
use App\Support\Identifier;
use App\Support\Tenancy;
use RuntimeException;

/**
 * The owner's *"never contact this person"* instruction (`34` §1.2).
 *
 * ⚠️ **THE CONTROL LOOKS LIKE A TOGGLE AND IS NOT SYMMETRICAL, WHICH IS THE
 * WHOLE OF THIS FILE.** Turning it on records an instruction the owner is
 * entitled to give. Turning it off means clearing a standing suppression — and
 * if the standing suppression is a STOP the *customer* sent, an owner clearing
 * it from a CRM screen is a one-tap path to texting somebody who refused. That
 * is decision 566's shape: a capability that creates authority, reachable by a
 * person acting on somebody else's behalf.
 *
 * So the state is three-valued (`NeverContactState`) and `release()` refuses
 * anything it did not write. The refusal is shown rather than hidden, because a
 * control that silently does nothing is worse than one that explains itself.
 *
 * ## It writes through the consent service and decides nothing itself
 *
 * `permit()` is the single chokepoint (285), and the mistake this class is
 * shaped to avoid is becoming a second one: no query here reads
 * `suppression_list` or `opt_outs`, no rule here decides whether a send is
 * allowed, and nothing here writes a consent row. It asks `ConsentService` what
 * is standing and tells it what the owner said. Decision 554's rule — an import
 * records a claim, it never grants permission — pointed the other way round.
 *
 * ⚠️ **EVERY CHANNEL, NOT THE TWO WE CAN SEND ON.** Suppression keys on
 * `(channel, identifier)`, and the third channel rides the same phone number as
 * SMS — so suppressing only the channels with a sender today would leave an
 * instruction that silently fails to hold the day one is built. `withdraw()`
 * returns without writing when the contact has no identifier for a channel, so
 * iterating the enum costs nothing and closes that door in advance. It is the
 * inverse of decision 286's lesson: one flag could not say *"suppressed on SMS,
 * reachable on email"*, and here the owner means both.
 */
final class NeverContact
{
    /**
     * The stored reason on every row this class writes.
     *
     * Free text on the row and a `SuppressionReason` beside it: the class is
     * what decides reversibility, and this sentence is what an operator reads
     * three months later when somebody asks why a number is suppressed.
     */
    public const string REASON = 'The business owner marked this contact Never contact.';

    public function __construct(private readonly ConsentService $consent) {}

    /**
     * Whether we are refusing to message this contact, and whether the owner
     * may change that.
     */
    public function state(Customer $customer): NeverContactState
    {
        Tenancy::idOrFail();

        $standing = $this->standing($customer);

        if ($standing === []) {
            return new NeverContactState(on: false, releasable: true);
        }

        $foreign = array_filter(
            $standing,
            fn (SuppressionListEntry $entry): bool => $entry->reason_class !== SuppressionReason::NeverContact,
        );

        if ($foreign === []) {
            return new NeverContactState(on: true, releasable: true);
        }

        // ⚠️ THE FIRST FOREIGN CLASS DECIDES THE WORDING AND THE ANSWER IS THE
        // SAME EITHER WAY — a STOP, a complaint or a bounce is not the owner's
        // to reverse. Only the sentence differs, and it differs because "they
        // asked us to stop" and "that address does not accept mail" send an
        // owner to two different places.
        return new NeverContactState(
            on: true,
            releasable: false,
            refusal: self::refusalFor(reset($foreign)->reason_class),
        );
    }

    /**
     * Record the instruction.
     *
     * Idempotent by `suppress()`'s own construction — an owner tapping twice is
     * the ordinary case, not an error to guard against.
     */
    public function apply(Customer $customer, User $actor): void
    {
        Tenancy::idOrFail();

        foreach (OutreachChannel::cases() as $channel) {
            $this->consent->withdraw(
                customer: $customer,
                channel: $channel,
                class: SuppressionReason::NeverContact,
                reason: self::REASON,
                actor: self::actorFor($actor),
                // ⚠️ TENANT, NEVER PLATFORM. The owner speaks for their own
                // business; a dentist saying "never text this one" says nothing
                // about the mechanic. See `SuppressionReason::NeverContact`.
                scope: OptOutScope::Tenant,
            );
        }
    }

    /**
     * Resolves an existing customer by their phone number and never creates one.
     */
    public function applyToNumber(string $phone, User $actor): ?Customer
    {
        Tenancy::idOrFail();

        $normalised = Identifier::normalise($phone, OutreachChannel::Sms);

        if ($normalised === null) {
            return null;
        }

        $customer = Customer::query()->where('phone', $normalised)->first();

        if (! $customer instanceof Customer) {
            return null;
        }

        $this->apply($customer, $actor);

        return $customer;
    }

    /**
     * Withdraw the instruction — and only ever the instruction.
     *
     * ⚠️ **IT LIFTS AT `Tenant` SCOPE, MATCHING WHAT `apply()` WROTE.** A
     * platform-scoped lift releases the person for every tenant, which is a
     * capability no owner-facing screen may reach: the row it would clear could
     * be a STOP that arrived on the shared number from somebody else's customer.
     *
     * @throws RuntimeException when something other than this instruction is standing
     */
    public function release(Customer $customer, User $actor): void
    {
        Tenancy::idOrFail();

        $state = $this->state($customer);

        if (! $state->releasable) {
            // Refused here as well as in the screen, on decision 391's rule: a
            // control that is not rendered but is still honoured is a gate with
            // a side door, and this service's first caller outside a browser
            // will be whatever builds the bulk actions doc `44` §4 describes.
            throw new RuntimeException(
                $state->refusal ?? 'This suppression was not written by the owner and is not theirs to clear.',
            );
        }

        foreach ($this->standing($customer) as $channel => $entry) {
            $this->consent->lift(
                identifier: $entry->identifier,
                channel: OutreachChannel::from((string) $channel),
                source: LiftSource::OperatorAction,
                actor: self::actorFor($actor),
                scope: OptOutScope::Tenant,
                note: 'The business owner cleared Never contact.',
            );
        }
    }

    /**
     * The standing suppression per channel, keyed by channel value.
     *
     * ⚠️ A null from `standingSuppression()` does not mean the contact is
     * reachable — a platform-scoped opt-out refuses without appearing here, by
     * that method's own design. Nothing in this class treats an empty result as
     * permission to send, and nothing that calls it may either: the send gate is
     * `permit()` and this is a screen's read of one instruction.
     *
     * @return array<string, SuppressionListEntry>
     */
    private function standing(Customer $customer): array
    {
        $standing = [];

        foreach (OutreachChannel::cases() as $channel) {
            $entry = $this->consent->standingSuppression($customer, $channel);

            if ($entry !== null) {
                $standing[$channel->value] = $entry;
            }
        }

        return $standing;
    }

    /**
     * Who did it, in the form `audit_log` already stores actors in.
     *
     * The id rather than the name, because the row outlives the employment and
     * a name is the part that changes.
     */
    private static function actorFor(User $actor): string
    {
        return 'user:'.$actor->getKey();
    }

    /**
     * Why the owner may not clear this one.
     *
     * `SuppressionReason::liftRefusal()` answers for the two classes that never
     * lift; a STOP lifts on a carrier START and not on an owner's tap, which is a
     * different sentence and belongs here rather than on the enum — the enum
     * answers "can this ever be lifted", this answers "may *you* lift it".
     */
    private static function refusalFor(SuppressionReason $class): string
    {
        return match ($class) {
            SuppressionReason::Stop => 'This customer asked you to stop messaging them. '
                .'Only they can undo that, by replying START.',
            SuppressionReason::Complaint, SuppressionReason::Bounce => $class->liftRefusal()
                ?? 'This suppression is not yours to clear.',
            SuppressionReason::NeverContact => 'This is your own Never-contact instruction.',
        };
    }
}
