<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\BrandRegistrationStatus;
use App\Enums\SignalState;
use App\Models\BrandRegistration;
use App\Services\Campaigns\BroadcastPreconditions;
use App\Services\Sms\BrandRegistrations;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Where a tenant's own 10DLC registration has got to, in the tenant's own words
 * — decision 5329, built at 5420–5439.
 *
 * ---------------------------------------------------------------------------
 * THE GAP THIS CLOSES, IN THE WORDS OF THE ROW THAT FOUND IT
 * ---------------------------------------------------------------------------
 * 5329: *"the law wants the honest days-language wherever a tenant waits.
 * `BrandRegistrationStatus` carries the honest sentence — the one-to-two-week
 * wait, and *'there is no reason a tenant's is faster'* — in a
 * docblock, which no tenant reads; `grep -rn 'BrandRegistration' resources/
 * app/Livewire/` returns nothing."* This is the page that says **how long that
 * takes and where theirs is**.
 *
 * ⛔ **THE SENTENCE THAT STOOD HERE — *"so a tenant was refused a campaign,
 * told by `BroadcastPreconditions` that 'this account has no approved 10DLC
 * registration of its own'"* — DESCRIBES SOMETHING THAT HAS NEVER HAPPENED TO
 * ANYBODY. CORRECTED 2026-08-28 (11520–11524).** Two independent things make
 * it false. **{@see BroadcastPreconditions} forbids the first in writing**: its
 * three strings are *"operator sentences that reach a log"* and *"none of
 * these three sentences is tenant-facing and none may become so"* (5329,
 * 5422) — so no tenant was ever shown that wording, here or anywhere.
 * **And nothing in `app/` reaches a campaign at all**:
 * `Services\Campaigns\Campaigns::enrol()` is the sole writer of
 * `campaign_recipients` and has no caller outside `tests/`, so the refusal this
 * page was built beside sits on a path no tenant can walk.
 *
 * ⚠️ **THE PAGE IS NOT WRONG AND IS NOT WITHDRAWN.** What it renders is a
 * recorded fact about a filing and an honest wait, and that is true whether or
 * not a campaign is reachable. **What was wrong was the story about why it
 * exists**, and that story is what a later lane would have read as evidence
 * that the campaign path is live — which is the reading that cost 10979 two
 * wrong diagnoses one table over (11085).
 *
 * ---------------------------------------------------------------------------
 * WHAT IT IS NOT
 * ---------------------------------------------------------------------------
 * ⛔ **IT IS NOT A TOGGLE AND IT IS NOT A SETTINGS SCREEN.** `CLAUDE.md`'s
 * standing rule — *"never add a tenant-facing toggle; every toggle is a future
 * support ticket"* — has been overruled exactly twice, each time by an explicit
 * ruling, and this needs neither: **there is no control on this page at all.**
 * It has no public properties a browser can set, no action methods, and writes
 * nothing. Every writer of `brand_registrations` is still `sms:brand-registration`,
 * Ops-only, exactly as `BrandRegistrations`' own docblock requires.
 *
 * ⛔ **AND IT IS NOT A PRE-FLIGHT OF WHETHER A CAMPAIGN WILL START.**
 * {@see BroadcastPreconditions}'s docblock is explicit that its three facts
 * *"are not fields on a form that a screen can pre-flight; they are properties
 * of the message about to be sent"* — a filing can be refused at 3am while a
 * campaign that started at midnight is still running. This page therefore
 * reports **one recorded fact with a history the tenant is waiting on**, says in
 * plain words that it is one of three, and says that all three are asked again
 * for every message. It never renders a green light.
 *
 * ---------------------------------------------------------------------------
 * WHERE THE WORDS COME FROM
 * ---------------------------------------------------------------------------
 * ⚠️ **THE STATUS ENUM IS THE SOURCE AND THE TEMPLATE HOLDS NO COPY OF IT** —
 * `BotController`'s shape, which renders `RobotsPolicy::userAgentToken()` rather
 * than typing the token. `BrandRegistrationScreenTest` asserts both directions:
 * that the sentence reaches the rendered page, **and** that the phrase naming
 * the wait appears in no template and in no class under `app/Livewire`. A
 * hand-copied string that drifts from the enum is the defect this codebase
 * repeats most.
 *
 * ---------------------------------------------------------------------------
 * NO LINK TO ANOTHER OWNER SCREEN, WHICH IS A LINT AND NOT AN OVERSIGHT
 * ---------------------------------------------------------------------------
 * ⚠️ `Architecture/OwnerNavTest`'s *"an owner screen does not hand-write its own
 * link to another owner screen"* fails the build on a `route('account.…')` in an
 * owner template, and its argument is that movement between owner screens comes
 * from `OwnerNav` rather than from a convention invented per page. So the two
 * other preconditions are **named in words** — the tenant's own number and the
 * credit they have bought — and the shell is what carries somebody to them.
 * Reachability is the nav entry, which is this application's answer to "reachable
 * from where the tenant hits the wall".
 *
 * AUTHORIZATION: `auth` alone, `Account\PixelInstall`'s and `Account\WidgetInstall`'s
 * reasoning exactly — there is nothing here to change, so there is no second gate
 * to add, and anybody signed in to the business may read where its registration
 * has got to.
 */
#[Layout('components.account.layout')]
final class Texting extends Component
{
    public function render(BrandRegistrations $registrations): View
    {
        // ⚠️ **ONE REFUSAL, NOT TWO** (decision 5051, `Account\PixelInstall`).
        // It fails closed without this line too — `TenantScope` calls
        // `Tenancy::idOrFail()` and the read throws — so what this buys is the
        // **status code**: 403 rather than a 500 for a signed-in support agent
        // who belongs to no business, which is the ordinary way somebody arrives
        // here with nothing resolved. A second guard beside it would leave both
        // unfalsifiable, which is 398's shape.
        abort_if(Tenancy::id() === null, 403);

        // ⛔ **`mostRecent()` AND NOT `live()`.** `live()` excludes a refused filing,
        // so a tenant the carriers turned down would be shown exactly what a
        // tenant who has never filed is shown — and the refusal reason, which
        // `BrandRegistrations::reject()` demands precisely because *"the tenant
        // will ask what to fix"*, would be the one thing they could never see.
        $filing = $registrations->mostRecent();

        return view('livewire.account.texting', [
            'filing' => $filing,
            'status' => $filing?->status,

            // ⚠️ **THE ABSENCE OF A FILING IS `Unknown`, NOT A FOURTH STATUS.**
            // `SignalState::Unknown` is *"the absence of a measurement … never
            // rendered as a zero"*, and that is exactly this: we have not filed
            // anything, so there is nothing to report and the page says so
            // rather than guessing a state.
            'signal' => $filing?->status->signal() ?? SignalState::Unknown,
            'label' => $filing?->status->label() ?? 'Not started',
            'sentence' => $filing?->status->sentence(),

            // ⚠️ **ASKED OF THE ROW RATHER THAN RE-DERIVED FROM THE STATUS**, on
            // `BrandRegistration::permitsSending()`'s own instruction: *"this
            // exists so a screen holding a row does not re-derive the rule"*.
            'ready' => $filing?->permitsSending() ?? false,

            'waitingLongerThanUsual' => $this->waitingLongerThanUsual($filing),
        ]);
    }

    /**
     * Has this filing been with the carriers longer than the ordinary wait?
     *
     * ⛔ **THIS EXISTS SO THE PAGE CANNOT OVERSTATE, WHICH IS THE ONLY DIRECTION
     * `BrandRegistrationStatus::USUAL_WAIT_DAYS` MAY BE READ IN.** The `Submitted`
     * sentence names an ordinary wait of a week or two; printed
     * unqualified to somebody in their sixth week it is no longer honest, and
     * 5329's whole subject is honest waiting. So the page adds a second sentence
     * when the ordinary wait has passed — it does **not** count down to a date,
     * because no vendor has given us one.
     *
     * ⚠️ **`Submitted` ONLY.** An approved or refused filing has an answer, so
     * how long it took is not something the tenant is still waiting on.
     */
    private function waitingLongerThanUsual(?BrandRegistration $filing): bool
    {
        if (! $filing instanceof BrandRegistration) {
            return false;
        }

        if ($filing->status !== BrandRegistrationStatus::Submitted) {
            return false;
        }

        return $filing->submitted_at
            ->addDays(BrandRegistrationStatus::USUAL_WAIT_DAYS)
            ->isPast();
    }
}
