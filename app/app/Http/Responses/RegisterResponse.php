<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a newly registered person goes: the wizard, not a card checkout.
 *
 * ⛔ **THE PREMISE THIS CLASS WAS BUILT ON WAS REVERSED ON 2026-08-11 AND THE
 * REDIRECT MOVED ON 2026-08-24 — 9201, RAISED HERE AT 9158.** It sent every new
 * owner to `billing.checkout` on the strength of *"14-day free trial, **credit
 * card required**"*; **2065 dropped the card requirement** — *"no card
 * required, card optional"* — and **2056 made Authorize.Net the primary
 * gateway**, so the first screen a brand-new owner met was a hosted Checkout on
 * the *secondary* one. **Nothing in `app/` changed when the ruling did** for
 * thirteen days, which is `CLAUDE.md`'s *a ruling with no writer looks exactly
 * like a ruling with one*.
 *
 * ⚠️ **AND THE THIRD REASON WAS IN NO RAISE AND NO DOCUMENT** (9201): a Stripe
 * Checkout Session *"cannot stop after a fixed number of payments and cannot
 * vary the amount between them"* — `BillingCheckout`'s own words, named bare
 * because a docblock cross-reference here becomes a `use` statement under
 * `composer lint` and this class imports nothing from that namespace — so the retail annual's **$332.33 × 3** and the founder annual's
 * **$249.99 + $250.00** were **unsellable on the one screen every new customer
 * was sent to** — refused into the same `RuntimeException` arm as *already
 * subscribed*.
 *
 * ⛔ **THE ROUTE IS NAMED, AND FALLING THROUGH TO `config('fortify.home')`
 * WOULD BE THE WRONG SPELLING OF THE SAME DESTINATION** (9202). That key is one
 * string for several account shapes and five other Fortify journeys still
 * terminate on it — password-confirmed, logout, password-reset, verify-email and
 * redirect-as-intended, because `config/fortify.php` declares no `redirects`
 * key. `StaffTest`'s *"one file in `app/` reads the sign-in fallback"* fails the
 * build on a second reader of it, and `LoginResponse` is that file.
 *
 * ⛔ **THIS DOCBLOCK CLAIMED THAT MOVING `fortify.home` WOULD "SEND EVERY
 * RETURNING CUSTOMER TO A BILLING PAGE ON EVERY SIGN-IN", AND THAT DESCRIBED A
 * WORLD WITHOUT `LoginResponse`** (9228). Login has not taken `fortify.home` as
 * its answer since 9156: `LoginResponse` reads it as a **fallback** only —
 * no user, an unhydrated role, staff with no nav item, a tenant-role user owning
 * no business — and answers `account.home` or `setup.index` for everybody else.
 * `fortify.home` is still `/setup` and is still not this class's lever; what
 * changed is that the sentence explaining why was three months stale.
 *
 * ⚠️ **WHAT REPLACED 690's ARGUMENT.** Until this response existed, registration
 * went straight to `/setup` and **nothing anywhere in the application ever
 * mentioned payment** — the gap decision 586's prose describes. ⛔ **No test
 * ever pinned that gap**: 586's tripwire is `BillingSubscriptionTest`'s *"cannot
 * produce a trial at provisioning"*, which calls `TenantProvisioner::provision()`
 * directly and asserts null Stripe ids, and **a redirect cannot redden it**
 * (9228). So the card affordance that replaces this redirect is pinned by tests
 * of its own: the wizard's last step and `/account/plan` each offer
 * `billing.index` to an owner who has never bought, and
 * `Architecture/BillingTest`'s door census fails the build if either link leaves
 * the tree unannounced.
 *
 * ⚠️ **AND CHECKOUT WAS NEVER A GATE.** Nothing refuses `/setup` to somebody who
 * never adds a card; `Subscriptions::isEntitled()` still answers true for
 * `pending_checkout` (588, 685). **Whether that stays true is the owner's open
 * question (9203) and is not answered here.** This change reduces the rate at
 * which such rows are minted and settles nothing about the ones that exist.
 */
final class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            // Fortify's own headless contract: 201 with no body. A JSON client
            // is not a browser and cannot be redirected anywhere useful; it
            // reads `/api/me` next, which reports the subscription state (580).
            return new JsonResponse('', 201);
        }

        return new RedirectResponse(route('setup.index'));
    }
}
