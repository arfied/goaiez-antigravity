<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\PaymentMethodReplacement;
use App\Services\Gbp\GbpConnections;

/**
 * The things support may never do while inside a customer's account (`28` §9.4).
 *
 * `28` states the rule as *"writes allowed except the blocklist below"* and
 * names nine items. **Eight of those nine describe features this application
 * does not have yet** — payment methods, credits, broadcasts, data export, the
 * advanced dashboard, user management, business deletion. A blocklist of things
 * that do not exist matches nothing and passes vacuously, which is decisions
 * 256, 285, 361 and 428 arriving for a fifth time. So two things had to be true
 * before this enum was worth writing:
 *
 *   1. **Something on it has to exist today.** `RecordConsent` does, and it is
 *      reachable: an agent holding a live act-as session who opens a tenant's
 *      own feedback page and submits it writes a consent record in that
 *      customer's name. That is manufactured evidence in the one table whose
 *      entire purpose is to be evidence, and no line in `28` covers it.
 *   2. **The ones that do not exist have to fail closed rather than silently.**
 *      A capability is refused the moment it is named here, whether or not
 *      anything calls it yet. When someone builds the credits purchase flow,
 *      the case is already sitting here waiting for them; what they must not be
 *      able to do is build it and have it quietly work under impersonation
 *      because nobody remembered a list in another document.
 *
 * ⚠️ **This is a blocklist, so it is only as good as its own completeness**, and
 * the honest statement of its limit is that a write with no case here is
 * permitted in act-as mode. An allowlist would fail closed instead, and was
 * considered and rejected for one reason: Livewire routes every interaction in
 * this application through a single endpoint — `POST livewire-16868c99/update`,
 * named **`default-livewire.update`**, verified with `route:list` rather than
 * assumed (1997) — so there is no route-shaped thing to allowlist per capability,
 * and an allowlist keyed on component method
 * names would be a second registry to keep in step with the components — which
 * is the drift decision 437 records, adopted deliberately. The guard therefore
 * sits at the same place `SendPermit` does: inside the service that owns the
 * capability, where the call cannot be routed around.
 */
enum ImpersonationCapability: string
{
    // ── Reachable today ─────────────────────────────────────────────────────

    /**
     * Writing a consent record in a customer's name.
     *
     * Not in `28`'s list, which predates consent records being this platform's
     * legal evidence under TCPA. `29` §2 requires the wording version,
     * timestamp, URL, IP hash and user agent be stored *as proof a specific
     * person agreed* — and proof that support can author is not proof.
     *
     * ⚠️ **Withdrawal and suppression are deliberately NOT blocked**, and the
     * asymmetry is the point rather than an omission. Every blocked capability
     * here creates authority; withdrawing consent and suppressing a number
     * destroy it. A support agent taking a call from somebody saying "stop
     * texting me" must be able to act on it in the same minute, and a blocklist
     * that stopped them would produce continued messaging to a person who
     * asked twice — the exact harm the register exists to prevent.
     */
    case RecordConsent = 'record_consent';

    /**
     * Connecting or disconnecting a provider account.
     *
     * `28` does not list it; `UserRole::canManageConnections()`'s own reasoning
     * demands it — connecting hands the platform a credential that acts as the
     * business. A support session is time-boxed and a credential is not, so a
     * 30-minute window that mints one outlives itself by design.
     *
     * ⛔ **IT HAD NO CALL SITE FROM THE DAY IT WAS DECLARED UNTIL 6483** (6455),
     * and it is the case where that cost the most, because the hole was **live**
     * rather than latent: a support lead in an act-as session could reach
     * `Account\Connections` and mint a Zernio grant holding `business.manage` on
     * a customer's Google listing. The role gate beside it could not have caught
     * that — inside a session the role asked is the owner's, and the owner may.
     * Guarded now at {@see GbpConnections::begin()} and
     * {@see GbpConnections::disconnect()}, which is why it
     * has moved into {@see self::reachableToday()}.
     */
    case ManageConnections = 'manage_connections';

    // ── `28` §9.4's list, for features that do not exist yet ────────────────
    // Named before their features so the feature cannot land unguarded. Each
    // one is refused now and will be refused then; what is missing is the call
    // site, and the lint below names which.

    /** Changing the owner's email, password or second factor. */
    case ChangeOwnerCredentials = 'change_owner_credentials';

    /** Adding or removing users on the tenant. */
    case ManageTenantUsers = 'manage_tenant_users';

    /** Deleting the business. */
    case DeleteBusiness = 'delete_business';

    /**
     * Viewing or changing payment methods — never via this door.
     *
     * ⛔ **THIS CASE ANSWERED `reachableToday() === false` UNTIL 2026-08-21, AND
     * ITS REFUSAL SENTENCE NAMED THE WRONG VENDOR** (6503). It read *"Card
     * details are held by Stripe and are not visible or editable from here"* —
     * written when Stripe was the only gateway, and left standing through 2056
     * making Authorize.Net primary. It is guarded now, in
     * {@see PaymentMethodReplacement::replace()}, which is
     * the first thing in `app/` that can change a payment method at all.
     *
     * ⚠️ **THE REASON IS SHARPER THAN "AN AGENT SHOULD NOT".** The card path is
     * Accept.js: the browser posts the card straight to the vendor and this
     * application never sees it, which is what preserves SAQ-A. An agent typing
     * a customer's card into the owner's own session over the phone is a MOTO
     * transaction, which that scope does not cover — the mechanism looks
     * identical from here and the compliance position is not.
     */
    case ManagePaymentMethods = 'manage_payment_methods';

    /** Purchasing credits, or anything else that spends the owner's money. */
    case PurchaseCredits = 'purchase_credits';

    /**
     * Ending the subscription (2980–2999).
     *
     * ⚠️ **NOT `ManagePaymentMethods` AND NOT `PurchaseCredits`, THOUGH BOTH
     * WERE AVAILABLE.** The first is about card details, which are held at the
     * gateway and are not visible from anywhere in this application; the second
     * is about *spending* money. This spends nothing and touches no card — it
     * stops the product, irreversibly on Authorize.Net, whose own documentation
     * says a cancelled subscription "cannot be reactivated". Borrowing either
     * case would have made an agent's refusal message describe a different act
     * from the one refused.
     */
    case CancelSubscription = 'cancel_subscription';

    /** Sending or scheduling a manual broadcast. */
    case SendBroadcast = 'send_broadcast';

    /** Accepting legal documents or a BAA on the tenant's behalf. */
    case AcceptLegalDocuments = 'accept_legal_documents';

    /**
     * Triggering a full data export — **and fetching one already built** (1904,
     * 1905).
     *
     * ⚠️ **THE FIRST CASE TO MOVE OUT OF THE GROUP ABOVE, WHICH IS WHAT THE
     * GROUP WAS WRITTEN FOR.** `28` §9.4 named it before there was anything to
     * guard; the export engine exists now, so it is guarded in two places, and
     * both are needed because they fail differently.
     * `ExportBuilder::request()` covers the *start*, and
     * `TenantExportDownloadController` covers the *fetch* — which
     * `Impersonating`'s own two layers cannot, because a download is a GET read
     * and both the view-only method check and the read-only connection pass it.
     *
     * ⚠️ It is therefore listed by {@see self::reachableToday()}, and the case
     * declaration stays here rather than moving up to the "reachable" group so
     * that `28` §9.4's own ordering is still readable in this file.
     */
    case ExportTenantData = 'export_tenant_data';

    /** Turning the advanced dashboard off after the owner turned it on. */
    case DisableAdvancedDashboard = 'disable_advanced_dashboard';

    /**
     * Why the refusal message says what it says.
     *
     * Shown to an agent, not to a customer, so it names the rule rather than
     * apologising — an agent who is told "not permitted" opens a ticket, and an
     * agent who is told *which* rule and *what to do instead* solves the
     * problem. `28`'s own instruction for the export case ("use the ops export
     * tool with approval instead") is the model for all of them.
     */
    public function refusal(): string
    {
        return match ($this) {
            self::RecordConsent => 'Consent has to be given by the customer themselves. Ask them to complete the form on their own device — a record created from here is not proof and would not survive a complaint.',
            self::ManageConnections => 'Connecting an account creates a long-lived credential, which a support session cannot do. Walk the owner through it on the call instead.',
            self::ChangeOwnerCredentials => 'Sign-in details belong to the owner. Send them a password reset rather than setting one for them.',
            self::ManageTenantUsers => 'Adding or removing people on the account is the owner\'s to do.',
            self::DeleteBusiness => 'Deleting a business is never a support action. Escalate it.',
            self::ManagePaymentMethods => 'Card details are held by the payment provider and are never visible from here. A card you type in is not the cardholder entering it, which is the whole basis we take cards on. Point them at their Plan page — they can put a new card on it themselves in one step.',
            self::PurchaseCredits => 'Spending the owner\'s money needs the owner. Send them the purchase link.',
            self::CancelSubscription => 'Ending the plan is the owner\'s to do, and on Authorize.Net it cannot be undone. Point them at the Billing page, where they can cancel it themselves in two clicks.',
            self::SendBroadcast => 'A message going out under the business\'s name has to be sent by the business.',
            self::AcceptLegalDocuments => 'Only the owner can accept an agreement on their own behalf. An acceptance recorded from here would not be one.',
            self::ExportTenantData => 'Use the ops export tool, which needs a second approver.',
            self::DisableAdvancedDashboard => 'The owner turned this on. Turning it off is theirs.',
        };
    }

    /**
     * Whether a call site for this capability exists in the application today.
     *
     * ⚠️ **This is a confession, kept honest by a test.** `ArchitectureTest`
     * asserts that every case answering `true` has a real guarded call site and
     * that every case answering `false` has none — so the day somebody builds
     * the credits flow and guards it, this method goes red until the list is
     * corrected, and the day somebody builds it and *forgets* to guard it,
     * nothing here changes and the lint that catches it is the one requiring a
     * guard on every service that writes. Neither test is sufficient alone.
     *
     * @return list<self>
     */
    public static function reachableToday(): array
    {
        return [
            self::RecordConsent,
            self::ManageConnections,
            // ✅ Guarded since 2026-08-21 in `PaymentMethodReplacement::replace()`
            // — 6404's card-replacement path, the first thing in `app/` that
            // could change a payment method at all (6503).
            self::ManagePaymentMethods,
            self::CancelSubscription,
            self::AcceptLegalDocuments,
            self::ExportTenantData,
        ];
    }
}
