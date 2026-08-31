<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Contracts\Billing\GatewayRequestFailure;
use App\Enums\CancellationOutcome;
use App\Exceptions\ImpersonationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelSubscriptionRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\Billing\GatewayRefusals;
use App\Services\Billing\SubscriptionCancellation;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Masmerise\Toaster\Toaster;
use Symfony\Component\HttpFoundation\Response;

/**
 * The press that ends a plan — the one thing this application promised on its
 * checkout page and could not do (2980–2999).
 *
 * ⛔ **BEFORE THIS ROUTE EXISTED, `grep -rn "cancel" routes/*.php` RETURNED
 * NOTHING.** Every cancellation arrived inbound: `AuthorizeNetWebhooks`
 * projecting a vendor-side end, `Dunning` exhausting its schedule into
 * `suspendForNonPayment()`. `billing/authorize-net.blade.php` said *"you can
 * cancel any time"* to every buyer, and the only way to act on that sentence was
 * to email support and have somebody do it in a vendor dashboard.
 *
 * ## A named POST route rather than a Livewire action (1900, 1997)
 *
 * `TenantExportRequestController`'s shape, for its reason and one more.
 * `SuspendedTenantStatus` runs on the whole `web` group and exempts by **route
 * name**; a Livewire button posts to `default-livewire.update`, so the exemption
 * that looks right exempts every action on every screen. This route is
 * exemptible at exactly the width of the promise — and it has to be, because
 * `TenantSuspension::suspend()` **writes `suspended_at` and touches the
 * subscription not at all**. A suspended tenant is still being charged, and
 * without the exemption would have no way to stop it.
 *
 * ⚠️ **AND IT IS WHAT PUTS THE POLICY AND THE FORM REQUEST ON A REAL REQUEST.**
 * `Livewire::test()` runs no middleware (809), so a Livewire action would have
 * left `auth`, CSRF and `authorize()` unexercised by anything the suite runs.
 *
 * ## What it does not do
 *
 * **It writes no subscription state.** {@see SubscriptionCancellation} calls the
 * gateway and records the request; the row reaches `canceled` when a verified
 * notification says so and at no other moment (2056). So the toast below names
 * the *outcome of the request* and never the status.
 */
final class CancelSubscriptionController extends Controller
{
    public function __invoke(
        CancelSubscriptionRequest $request,
        SubscriptionCancellation $cancellation,
    ): RedirectResponse {
        $business = $this->business();

        // The route sits behind `auth`, so this cannot happen — asserted rather
        // than defaulted because the actor is what the audit entry is *for*, and
        // `29` §2 rule 42's record of a sensitive act with no actor on it is a
        // record of nothing.
        $user = $request->user();

        abort_if(! $user instanceof User, Response::HTTP_FORBIDDEN);

        try {
            $outcome = $cancellation->request($business, 'user:'.$user->getKey());
        } catch (ImpersonationRefused $refusal) {
            // `28` §9.4's blocklist as a sentence the agent can act on rather
            // than the 500 an unhandled exception would be —
            // `TenantExportRequestController`'s handling, one capability over.
            abort(Response::HTTP_FORBIDDEN, $refusal->getMessage());
        } catch (GatewayRequestFailure $e) {
            // ⚠️ THE CLASSIFIED REASON, NEVER THE VENDOR'S MESSAGE — the two
            // checkout controllers' rule, and it matters more here: somebody who
            // has just tried to stop being charged is owed a sentence saying
            // whether it worked.
            //
            // ⚠️ AND THE RECORDED REQUEST SURVIVES THIS. `request()` writes
            // `cancellation_requested_at` **before** the vendor call, so a
            // failure still leaves evidence that they asked — which is the
            // record that matters if the next charge lands anyway.
            //
            // ⛔ **AND THE CATCH IS THE CONTRACT NOW** (12240). The union
            // `StripeRequestFailed|AuthorizeNetRequestFailed` compiled only
            // because the two classes happened to declare the same properties,
            // and reading `$e->reason` off it was a coincidence nothing held.
            // {@see GatewayRequestFailure} is the four members this arm needs.
            Log::warning('a cancellation could not be passed to the gateway', [
                'business_id' => $business->id,
                'reason' => $e->reason,
                'configuration' => $e->configuration,
                // ⛔ **THE FLAG THIS SURFACE COULD NOT SEE, AND HERE IT IS THE
                // WHOLE OF WHAT THE FIX IS** (12241). The customer sentence
                // below is right either way — it promises a record, not a
                // remedy — so this line is the only place a malformed request
                // and an unset credential differ on the statutory cancel path.
                // Without it an operator reading `configuration: false` on an
                // `E00014` concludes the vendor refused us, and goes looking at
                // a merchant account where nothing is wrong.
                'malformed_request' => $e->malformedRequest,
            ]);

            // ⛔ **THE SECOND SENTENCE PROMISED TWO THINGS AND NEITHER WAS
            // TRUE — 9297.** *"our team has been told and will finish it for
            // you"*: nothing told anybody — the only writer of
            // `PlatformHealthSignal::VendorCall`, which
            // `OperatorAlertKind::VendorErrorRate` reads, is the AI router
            // (9235) — and nothing was arranging to finish it. On the
            // Automatic Renewal Law path.
            //
            // ⛔ **AND THIS ARM WAS UNREACHABLE FOR THE COMMONEST CAUSE OF IT
            // UNTIL 9294.** An unset gateway credential raised a bare
            // `RuntimeException` out of `AuthorizeNetApi`, which is neither of
            // the two types caught here, so **the statutory cancel path
            // answered a 500** on every deployment this application has had.
            // ⛔ **THE FIFTH COPY, AND IT SAID *"YOUR"* — 12424.** Four sites
            // carried this sentence verbatim and this one carried a different
            // pronoun and different punctuation, so a lane grepping the exact
            // string found four and would have reported the move complete.
            // ⛔ **The pronoun was not a variant, it was a wrong claim**: what
            // did not answer is our own merchant gateway, on our contract, and
            // this person is ending a subscription **we** hold there.
            // {@see GatewayRefusals::cannotReachGateway()} carries the argument
            // and the question this arm still owes.
            Toaster::error($e->retryable
                ? GatewayRefusals::cannotReachGateway()
                : GatewayRefusals::cannotCancel());

            return back();
        }

        // ⚠️ OUTCOME LANGUAGE, AND ONE SENTENCE PER HONEST ANSWER (`22`, and
        // `CancellationOutcome`'s own docblock). No personal data in a toast
        // (104), and the toast is never the record — the audit entry is, and
        // `SubscriptionCancellation` writes it.
        match ($outcome) {
            CancellationOutcome::StopsAtPeriodEnd => Toaster::success(
                'Ended — you keep everything until the end of the period you have paid for',
            ),
            CancellationOutcome::KeepsPaidTerm => Toaster::success(
                'Ended — you keep the year you have paid for, and nothing further is charged',
            ),
            CancellationOutcome::StopsNow => Toaster::success(
                'Ended — nothing further will be charged',
            ),
            CancellationOutcome::NoFurtherCharge => Toaster::success(
                'Nothing further will be charged — every payment for your plan had already been taken',
            ),
            CancellationOutcome::AlreadyEnded => Toaster::success(
                'Your plan had already ended, so nothing more will be charged',
            ),
            CancellationOutcome::NothingToCancel => Toaster::success(
                'There is no paid plan on this account, so nothing is being charged',
            ),
        };

        return back();
    }

    /**
     * ⚠️ THE TENANT COMES FROM THE SESSION AND NEVER FROM THE REQUEST, so there
     * is no id for a client to supply — `Account\ReviewRules`' rule after
     * decision 805 shipped a component whose `$businessId` was writable from the
     * page. The 403 is `TenantExportRequestController`'s: internal staff belong
     * to no business, so a signed-in support agent posting here has nothing to
     * resolve.
     */
    private function business(): Business
    {
        $id = Tenancy::id();

        abort_if($id === null, Response::HTTP_FORBIDDEN);

        $business = Business::query()->find($id);

        abort_if(! $business instanceof Business, Response::HTTP_NOT_FOUND);

        return $business;
    }
}
