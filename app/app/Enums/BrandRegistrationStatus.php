<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Sms\BrandRegistrations;

/**
 * Where a tenant's own 10DLC filing has got to (`26` §Automated TCR brand
 * registration lifecycle, decision 3310).
 *
 * ⚠️ **THIS IS THE *TENANT'S* BRAND, NEVER THE PLATFORM'S.** The GOAIEZ brand and
 * campaign are ours, filed once, and no row here describes them — `phone_numbers`
 * with a null `business_id` is what carries the platform pool. A row in
 * `brand_registrations` exists only because a tenant is filing on their own
 * account, which is the thing 3310 makes a precondition of broadcasting.
 *
 * ⛔ **"APPROVED" MEANS BRAND *AND* CAMPAIGN, AND THE DATABASE INSISTS.** A
 * carrier-approved brand with no approved campaign sends nothing, so an
 * `approved` row without both provider references would be a green light for a
 * route that does not exist. The creating migration's
 * `brand_registrations_approved_rows_are_complete` refuses it.
 *
 * Written only by {@see BrandRegistrations}.
 *
 * ⚠️ **THE TENANT-FACING WORDS LIVE HERE AND NOWHERE ELSE** (5329, 5420). Until
 * 2026-08-18 the honest sentence below — *"roughly one to two weeks from
 * submission … there is no reason a tenant's is faster"* — sat in a docblock,
 * which no tenant reads, and `grep -rn 'BrandRegistration' resources/
 * app/Livewire/` returned nothing at all. `App\Livewire\Account\Texting` is the screen that
 * closes that, and it renders {@see self::label()}, {@see self::signal()} and
 * {@see self::sentence()} rather than carrying a copy of any of them — a lint in
 * `BrandRegistrationScreenTest` fails the build if the wait sentence is ever
 * typed into a template.
 */
enum BrandRegistrationStatus: string
{
    /**
     * Filed with the vendor, waiting on the carriers. Roughly one to two weeks
     * from submission for the platform's own filing (1562), and there is no
     * reason a tenant's is faster.
     */
    case Submitted = 'submitted';

    /**
     * Brand **and** campaign approved. The only status that lets a broadcast go
     * out — {@see BrandRegistrations::isApproved()}.
     */
    case Approved = 'approved';

    /**
     * The carriers refused it. Terminal for this row: a re-file is a new
     * submission with its own history, because the reason the first was refused
     * is a fact somebody will be asked about.
     */
    case Rejected = 'rejected';

    /**
     * The upper end of the ordinary wait, in whole days.
     *
     * ⚠️ **THIS IS THE SENTENCE ABOVE AS A NUMBER, AND THE TWO MOVE TOGETHER.**
     * 1562's artefact — `RESEARCH-10dlc-tollfree-form-reference.md`, quoted
     * rather than remembered — gives *"brand approval 15min–3d · campaign
     * ≈1–2wk"*, and 3310 makes the **campaign** half the precondition, so the
     * figure is the campaign's fortnight and not the brand's three days.
     *
     * ⛔ **IT IS NOT AN ESTIMATE OF WHEN A PARTICULAR FILING WILL CLEAR AND
     * NOTHING MAY RENDER IT AS ONE.** Its only reader is the screen above,
     * which
     * uses it to decide whether a filing has been waiting *longer* than the
     * ordinary wait — the direction that stops the page telling somebody in
     * their sixth week that these usually take a fortnight, which is 5329's
     * whole subject. A page that added "expected by the 14th" from this
     * constant would be inventing a vendor commitment nobody has given.
     */
    public const int USUAL_WAIT_DAYS = 14;

    /**
     * Whether a filing in this state permits sending on the tenant's own brand.
     *
     * ⚠️ **A `match` WITH NO `default`.** The permissive answer is the dangerous
     * one here — a fourth state inheriting `true` would let a filing nobody has
     * approved authorise a marketing blast — so a new case is a compile-time
     * conversation.
     */
    public function permitsSending(): bool
    {
        return match ($this) {
            self::Approved => true,
            self::Submitted, self::Rejected => false,
        };
    }

    /**
     * The word a tenant reads for this state.
     *
     * ⚠️ **OUTCOME LANGUAGE (`22`), SO NO "SUBMITTED", NO "10DLC" AND NO "TCR".**
     * A tenant is not tracking a filing through a registry; they are waiting for
     * the phone networks to accept their business, and the label says which of
     * those has happened. `SignalState::label()`'s own rule, one enum over: what
     * the state means for the person, never what the system did to work it out.
     */
    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Waiting on the phone networks',
            self::Approved => 'Accepted',
            self::Rejected => 'Not accepted',
        };
    }

    /**
     * The signal colour this state is drawn in — never on its own.
     *
     * ⚠️ **`Attention` FOR A WAIT IS THE CLOSEST OF FOUR AND IS NOT A PERFECT
     * FIT, WHICH IS WRITTEN DOWN RATHER THAN SMOOTHED OVER.** `SignalState`'s
     * own definitions are *"worth a look; never blocks anything"* for
     * `Attention` and *"costing the business something now"* for `Alert`, and a
     * filing with the carriers is neither: it blocks one feature and there is
     * nothing for anybody to look at. `Alert` would manufacture urgency about
     * something the tenant cannot move, which is the thing `SignalState`'s own
     * docblock says makes an instrument stop being believed. **What carries the
     * truth is {@see self::label()} and {@see self::sentence()}** — `22`'s rule
     * is that colour is never the sole indicator, and here it is the least of
     * the three.
     *
     * ⛔ **THE ABSENCE OF A FILING IS `SignalState::Unknown` AND IT IS NOT A
     * FOURTH CASE OF THIS ENUM.** The screen maps a missing row to it,
     * exactly as `SignalState::Unknown` is *"the absence of a measurement"*
     * rather than a fourth severity. A `NotFiled` case here would inherit
     * `permitsSending()`'s `false` correctly and would also become a status a
     * row could be *stored* in, which is a state nobody recorded.
     */
    public function signal(): SignalState
    {
        return match ($this) {
            self::Submitted => SignalState::Attention,
            self::Approved => SignalState::Ok,
            self::Rejected => SignalState::Alert,
        };
    }

    /**
     * The honest sentence a tenant in this state is owed.
     *
     * ⛔ **THE `Submitted` ARM IS 5329's DELIVERABLE AND ITS SHAPE IS THE RULING
     * RATHER THAN A STYLE CHOICE.** It names the ordinary wait as a range, says
     * plainly that nothing here can shorten it, and **refuses to give a date** —
     * withholding beats inventing, which is the rule the Limited tier price
     * follows (157) and the one `CLAUDE.md` states for every figure a vendor has
     * not given us. `RecordBrandRegistration` prints the same refusal to the
     * operator on every `submit`; this is the tenant's half of it.
     *
     * ⛔ **NO ARM SAYS OR IMPLIES THAT BEING ACCEPTED LETS A TENANT TEXT
     * ANYBODY** — 10DLC brand registration is carrier registration for tenant sending and does not constitute recipient consent (carrier approval is distinct from recipient consent), and 2100.
     * The `Approved` arm is scoped to *whose number the message leaves on*,
     * which is the whole of what the phone networks decided.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Submitted => 'Your registration is with the phone networks now. They usually take '
                .'roughly one to two weeks to decide, and nothing we do here makes that faster. We '
                .'will not guess at a date — this page changes as soon as they answer.',
            self::Approved => 'The phone networks have accepted your business in its own name, so a '
                .'text campaign to your own list can go out on your own number.',
            self::Rejected => 'The phone networks did not accept this registration. Starting again '
                .'means a fresh registration rather than reopening this one, so what they said '
                .'about this one stays on the record.',
        };
    }
}
