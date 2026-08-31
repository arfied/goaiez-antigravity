<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a tenant is not currently sending.
 *
 * ⚠️ **THE FIRST CASE IS THE ONLY ONE THAT HAPPENS WITHOUT A HUMAN, AND IT IS
 * THE REASON THIS ENUM EXISTS** (2102). *"A kill switch that needs somebody
 * awake is the mitigation this override cannot rely on."* An operator pause and
 * an automatic trip look identical on the sending path — both refuse — and need
 * completely different conversations afterwards, so the difference is stored
 * rather than reconstructed from who happened to be on shift.
 */
enum SendingPauseReason: string
{
    /**
     * The complaint rate crossed the threshold and the platform stopped this
     * tenant by itself.
     *
     * 2101's containment. ⚠️ **Resuming this is a decision, not a button that
     * un-pauses a timer** — the rate that tripped it does not fall on its own,
     * and a tenant resumed into the same list trips again within the hour.
     */
    case ComplaintRate = 'complaint_rate';

    /** An operator stopped this tenant deliberately. */
    case Operator = 'operator';

    /**
     * Billing stopped it — an exhausted balance on an account that cannot
     * auto-top-up, or a failed subscription payment.
     *
     * ⚠️ **NOT THE SAME AS RUNNING OUT OF CREDITS MID-CAMPAIGN.** That is a
     * refusal on one send and the tenant is otherwise healthy. This is the
     * account itself being stopped, and it survives a top-up until somebody
     * clears it.
     */
    case Billing = 'billing';

    /**
     * A compliance hold — an attestation withdrawn, a list under review, a
     * carrier escalation naming this tenant.
     */
    case Compliance = 'compliance';

    /**
     * The words an operator reads, which are not the words the column holds.
     *
     * ⚠️ **ADDED WITH THE FIRST SCREEN THAT RENDERS ONE** (2630). `22`'s rule
     * is *"outcome language only — every string names what the person controls,
     * never how the system is built"*, and `complaint_rate` is the second of
     * those: a column value, printed. `SignalState::label()` puts the same job
     * in the same place for the same reason — the pairing belongs to the type,
     * not to each template that draws it, because a template picking its own
     * wording is one that drifts from the next template.
     *
     * A match with no default, so a fifth case is a compile-time conversation
     * rather than one that renders its own raw value to an operator.
     */
    public function label(): string
    {
        return match ($this) {
            self::ComplaintRate => 'Too many complaints',
            self::Operator => 'Stopped by us',
            self::Billing => 'Billing',
            self::Compliance => 'Compliance hold',
        };
    }

    /**
     * Whether the platform may lift this by itself.
     *
     * A match with no default, so a fifth case is a compile-time conversation
     * rather than one that quietly inherits "the machine may resume this".
     *
     * ⛔ **NOTHING RETURNS TRUE TODAY, AND THAT IS THE ANSWER RATHER THAN AN
     * OMISSION.** Every pause here is lifted by a person. An automatic resume
     * on a complaint-rate trip is the failure mode 2102 is about, inverted: the
     * rate decays as the window rolls forward whether or not anything was fixed,
     * so a timer-based resume would restart the same campaign into the same
     * list and trip again — and the second trip would look like the first,
     * hiding that nobody ever looked.
     */
    public function isSelfClearing(): bool
    {
        return match ($this) {
            self::ComplaintRate, self::Operator, self::Billing, self::Compliance => false,
        };
    }
}
