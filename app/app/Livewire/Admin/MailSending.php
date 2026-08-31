<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\SignalState;
use App\Services\Mail\MailDrivers;
use App\Services\Mail\MailQuota;
use App\Services\Mail\MailSendRate;
use App\Support\Admin\AdminAccess;
use App\Support\CredentialManifest;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The email sending meter — T137 R3's *"encoded as a visible limit"*, and the
 * screen decision 4442 recorded as owed (built at 4600).
 *
 * ## Why this exists at all, given 2095
 *
 * ⚠️ **2095's RULING IS THAT A METER IS NOT THE MECHANISM, AND IT STANDS.** The
 * thing that saves the platform is the alert `MailQuota` raises by itself at
 * `critical`, because *"a meter is a thing somebody looks at"* and this failure
 * is silent and total at once. That half was built with the service; this is
 * the half that was not.
 *
 * ⛔ **`MailQuota::reading()` HAD NO READER FOR FIVE DAYS AND ITS DOCBLOCK SAID
 * IT WAS FOR "THE ADMIN SCREEN R3 ASKS FOR"** (4442). Its only consumers were
 * two exception messages asking for `['used']`. That is `CLAUDE.md`'s most
 * repeated defect in its quietest form — not a table nobody writes, but **a
 * method that describes a screen into existence**, which is 2505's shape at
 * method scope: the next reader does not go looking for a screen the code says
 * is already there.
 *
 * ## What an operator can only find out here
 *
 * Three questions, and none of them is answered anywhere else in the console:
 *
 *   1. **Is anything being sent at all?** Since 4603 the ceiling is per mailer
 *      and `smtp` — how SES is reached — carries **no seed**, so a deployment
 *      that sets `MAIL_MAILER=smtp` and nothing else sends nothing and says so
 *      only in `failed_jobs`. This screen is where that is legible, and it
 *      names the registry row to set.
 *   2. **How close is the window to the ceiling**, over the rolling 24 hours
 *      the vendor actually meters rather than over a calendar day.
 *   3. **Where does customer mail stop**, which is *not* the ceiling — the
 *      reserve holds a slice back so a sign-in link still goes out on an
 *      account a review-invite batch has otherwise consumed.
 *
 * ## It cannot disagree with the alert, and it must not disagree with Ops
 *
 * ⛔ **EVERY FIGURE COMES OUT OF ONE `reading()` CALL AND THE TEMPLATE DOES NO
 * ARITHMETIC** (4485). That decision records two panels on one screen giving
 * two answers about one business at one moment, because each renderer
 * remembered the rule separately — and its fix was that they agree **by
 * construction**. The same hazard was live here one layer down: `reading()`
 * compared a *rounded* ratio against the alert threshold while the alert
 * compared the unrounded quotient, so this screen could have reported an
 * account as alerting about a window that had raised no alert. The predicate is
 * now computed once, beside the alert (4600).
 *
 * ⚠️ **AND THE SECOND SCREEN IS `Admin\PlatformSettings`, WHICH SHOWS THE
 * ROWS.** The reserve is clamped below the ceiling, so a deployment with a
 * ceiling of 200 and the seeded reserve of 200 holds back **199** — the Ops
 * editor would say 200 and this would say 199 about one deployment, which is
 * 3418's both-halves-right-on-their-own-screen shape. The reading carries both
 * figures and this screen states the derivation rather than picking one.
 *
 * ⛔ **AND IT IS DELIBERATELY NOT A PANEL ON `Admin\SendingControls`.** That
 * screen's own docblock says why: it is SMS only, and its rates belong to the
 * 10DLC complaint trip. *"Email delivery is the mail engine's own reporting
 * and putting it under the same heading would put two different vendors' bounce
 * vocabularies behind one number."*
 *
 * ## No tenant, no personal data, and nothing to audit
 *
 * The ceiling belongs to **one sending account**, not to a tenant: nothing here
 * is scoped, filtered or attributable to a business, which is `MailQuota`'s own
 * *"nothing about this is per tenant"*. So there is no audit entry — 419 makes
 * `AuditService::record()` open with `Tenancy::idOrFail()`, and filing a
 * platform reading under whichever business the operator happens to own would
 * be worse than not filing it, which is `SendingControls::haltPlatform()`'s
 * reasoning for the same absence.
 *
 * ⚠️ **THE ONLY ADDRESS ON THIS PAGE IS OURS.** `sendingAccount()` is the
 * Workspace user or the configured from address — never a recipient's — which
 * is the distinction `platform_mail_sends` draws in its own schema, where the
 * meter deliberately stores no address, subject or notification class.
 */
final class MailSending extends Component
{
    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    public function render(MailQuota $quota, MailDrivers $drivers, MailSendRate $rate): View
    {
        $this->authorize(AdminAccess::GATE);

        $reading = $quota->reading();
        $signal = $drivers->feedbackSignal();

        return view('livewire.admin.mail-sending', [
            'reading' => $reading,
            // ⚠️ **THE SECOND LIMIT, AND IT IS ON THIS SCREEN BECAUSE OTHERWISE
            // IT IS A REGISTRY ROW NOBODY WOULD EVER SET** (4648, 4650). Unlike
            // the ceiling, an unstated rate refuses nothing — so nothing forces
            // an operator to notice it, which is 272's shape and is answered
            // here the only way a fail-open control can be: by being visible
            // beside the fail-closed one an operator is already reading.
            'sendRatePerSecond' => $rate->perSecond(),
            'sendRateKey' => $rate->rateKey(),
            'state' => $this->state($reading),
            'stateLabel' => $this->stateLabel($reading),
            'customerStopsAt' => $reading['ceiling'] === null || $reading['reserve'] === null
                ? null
                : $reading['ceiling'] - $reading['reserve'],
            // ⚠️ **THE BAR'S WIDTH IS COMPUTED HERE BECAUSE THE DOCBLOCK ABOVE
            // SAYS THE TEMPLATE DOES NO ARITHMETIC.** It was one `min(100,
            // round(…))` in the markup — a sum small enough to feel exempt, and
            // **a claim with one exception is 314–316's shape inside the slice
            // that quotes it.** Clamped, because a window can exceed its
            // ceiling: `record()` counts what was handed over, so an operator
            // who lowers the ceiling mid-window makes `used` larger than it.
            'barPercent' => $reading['ratio'] === null
                ? null
                : min(100.0, round($reading['ratio'] * 100, 1)),
            // ⚠️ **THE SAME CALL THE SEND GATE MAKES, NOT A SECOND READING OF
            // THE SAME CONFIG KEYS.** `feedbackSignal()` is what
            // `PlatformMailer::assertCustomerMailPermitted()` asks, including
            // 4429's downgrade of a `typed` claim with no SNS topic behind it —
            // so a deployment in that half-configured state reads here exactly
            // as it behaves, rather than as the `.env` line says it should.
            'customerMailPermitted' => $signal->permitsCustomerMail(),
            'feedbackRefusal' => $signal->customerMailRefusal(),
            // ⛔ **THE ONE CREDENTIAL ON THIS PLATFORM THAT IS ON NO REGISTER,
            // SAID ON THE SCREEN AN OPERATOR OPENS WHEN NO MAIL IS GOING OUT**
            // (9374). `MAIL_USERNAME` and `MAIL_PASSWORD` are read by
            // `config/mail.php` straight from the environment, so
            // `Admin\Credentials` cannot list them, nothing tests them, and
            // wave 26's credential bell cannot fire about them in any state.
            // ⚠️ **HERE RATHER THAN ON `Admin\Credentials`, AND THE CHOICE IS
            // ARGUED**: the question an operator is holding when this matters is
            // *"why is no mail going out"*, and this is the screen that answers
            // it — it already names the transport, the sending account and the
            // row that stops everything. A companion line on the Credentials
            // screen is owed and is not this lane's file (9376).
            'mailerCredential' => CredentialManifest::mailerCredential($drivers->active()),
        ]);
    }

    /**
     * The one signal state for the window.
     *
     * ⚠️ **AN UNSTATED CEILING IS `Alert` RATHER THAN `Unknown`, AND THE
     * DIFFERENCE IS WHAT AN OPERATOR DOES NEXT.** `Unknown` is the honest word
     * for a figure nobody has measured — `SignalState::fromScore(null)`'s case,
     * and `RateReading`'s empty denominator. Nothing here is unmeasured: the
     * count is exact and the consequence is certain, which is that no message
     * goes out at all. Greying it out would describe a gap in our knowledge
     * where there is an outage in the product.
     *
     * @param  array{ceiling: int|null, alerting: bool, ...}  $reading
     */
    private function state(array $reading): SignalState
    {
        if ($reading['ceiling'] === null) {
            return SignalState::Alert;
        }

        return $reading['alerting'] ? SignalState::Attention : SignalState::Ok;
    }

    /**
     * Outcome language (`22`, `29` §5.7) — what is happening to the mail, never
     * which class decided it.
     *
     * @param  array{ceiling: int|null, alerting: bool, ...}  $reading
     */
    private function stateLabel(array $reading): string
    {
        if ($reading['ceiling'] === null) {
            return 'Email is stopped';
        }

        return $reading['alerting'] ? 'Close to the limit' : 'Email is sending';
    }
}
