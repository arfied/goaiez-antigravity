<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\WithheldGrantCeiling;
use App\Services\Support\AccountLifecycle;
use App\Services\Support\CreditGrants;
use App\Support\Admin\CreditGrantAccess;
use InvalidArgumentException;

/**
 * What a person may do (FOUND-04).
 *
 * **FOUND-04 names `spatie/laravel-permission` and this deliberately does not
 * use it.** Adding a dependency needs approval (CLAUDE.md), and the approved
 * list at Stage 0 is larastan alone (docs/DECISIONS.md 143-145). The package
 * would also bring four tables, a second cache, and a permission registry to
 * hold five fixed roles that no tenant can edit — and "never add a tenant-facing
 * toggle" applies to roles as much as to settings. A string column cast to this
 * enum, with Laravel's own Gate and policies above it, is the whole feature.
 *
 * If per-permission grants are ever genuinely needed — `28` §9.1 hints at it for
 * internal staff, with refund ceilings and per-tenant overrides — that is the
 * moment to re-open the dependency, not now.
 *
 * ⚠️ **THE FIRST GENUINE ONE ARRIVED WITHOUT NEEDING IT** — {@see
 * self::mayGrantCredits()} and {@see self::creditGrantCeiling()}, row 6's
 * credit-grant ceiling. `28` §9.1's *refund* ceilings did not: this application
 * has no refund path anywhere — `StripeApi` has exactly two public methods and
 * neither is one — so a ceiling on refunds would gate a control nothing
 * exercises, decision 272's shape, and it was refused rather than built. Credits
 * had a real writer (`CreditLedger::record()`) to gate, which is the whole
 * difference: a per-permission grant is a predicate here plus a service-level
 * guard, the same shape as every other capability question in this file, and
 * still no package.
 *
 * A `string` column cast to a backed enum, never a database enum: this set is
 * expected to grow — it just did, and PostgreSQL enum values can never be
 * dropped or reordered once added.
 *
 * **TWO POPULATIONS, ONE COLUMN.** The five original cases are *tenant* roles:
 * they say what a person may do inside the one business they belong to. The five
 * added for `28` §9.1 are *internal staff* roles, and the people holding them
 * belong to no business at all — `businesses.owner_user_id` never points at them
 * and `ResolveTenant` resolves no tenant for them, which is the correct and
 * deliberate outcome. An internal role therefore answers **false** to every
 * tenant-authority predicate below. That is not an oversight to be corrected the
 * first time a support agent needs to change a setting: the authority to act
 * inside a tenant comes from an impersonation session, not from a role, so that
 * it is time-boxed, reasoned, attributed and revocable. See
 * `App\Services\Impersonation`.
 *
 * ⚠️ **ONE WRITER.** `App\Services\Staff\StaffDirectory` is the only thing in
 * `app/` that may put a value in `users.role`, held there by an
 * `ArchitectureTest` lint. Until 2026-08-04 there was **no** writer at all and
 * every internal role below was unassignable (decision 740).
 *
 * ONE ROLE PER USER, and that is the current data model rather than a
 * simplification. `businesses.owner_user_id` is the only user-to-business link
 * DATA-MODEL defines; there is no membership pivot, so a user belongs to exactly
 * one business and carries one role in it. `Agency` exists as a role but cannot
 * yet span businesses — when a pivot lands, the role moves onto it and this enum
 * is unchanged.
 */
enum UserRole: string
{
    /** The platform. Everything, including other tenants. */
    case SuperAdmin = 'super_admin';

    /** Manages tenants on their behalf. Tenant-level authority, no platform access. */
    case Agency = 'agency';

    /** The business owner. Full authority inside their own tenant. */
    case Owner = 'owner';

    /** Day-to-day operation, including automation settings. */
    case Manager = 'manager';

    /** Does the work; changes nothing about how the system behaves. */
    case Staff = 'staff';

    // ── Internal staff (`28` §9.1) ───────────────────────────────────────────
    // Ours, not a tenant's. None of these belongs to a business.

    /** All operations tooling, integrations health, fix toolbox; no platform-global settings. */
    case OpsAdmin = 'ops_admin';

    /** Full billing operations, plan and coupon management, revenue dashboards. */
    case BillingAdmin = 'billing_admin';

    /** All support and CRM, escalation and queue admin, and act-as impersonation. */
    case SupportLead = 'support_lead';

    /** Tickets and CRM on assigned accounts, and view-only impersonation. */
    case SupportAgent = 'support_agent';

    /** Read-only across CRM, support and messaging health — junior and training staff. */
    case CsReadonly = 'cs_readonly';

    /**
     * No authority anywhere. The revoked state for an internal account.
     *
     * ⚠️ **Revocation needs a value to write, and every alternative was worse.**
     * `StaffDirectory` is the only writer of this column (740), so "take their
     * access away" has to be expressible as a role or it is not expressible at
     * all — and the three other ways to say it each cost more than a case:
     *
     * - **Delete the user.** `impersonation_sessions.agent_id` is
     *   `restrictOnDelete` on purpose (562) — deleting the person who opened a
     *   session must not delete the record that they did — so a leaver is
     *   undeletable the moment they have touched a customer's account, which is
     *   every leaver worth revoking.
     * - **A `disabled_at` column gating login.** There are four ways in (661)
     *   and the check would have to hold on all of them; slice 3 records what
     *   happens when a control covers three doors of four. This case needs no
     *   new gate, because every predicate below already answers false.
     * - **Demote to a tenant role.** `staff` on a person who belongs to no
     *   business reads as an oversight rather than a decision, and it is one
     *   `businesses.owner_user_id` row away from meaning something.
     *
     * The account survives, keeps its history, can still sign in, and can reach
     * nothing. Signing in is deliberate: a revoked person hitting a working
     * login and an empty console learns their access is gone, where a dead login
     * looks like an outage and generates a support call to the people who
     * revoked them.
     */
    case None = 'none';

    /**
     * How this role is named to an operator.
     *
     * Spelled out rather than derived from the case name: `cs_readonly` headlines
     * to "Cs Readonly", and a screen that assigns authority should not be the one
     * place in the console where the words look machine-made.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Agency => 'Agency',
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
            self::OpsAdmin => 'Operations admin',
            self::BillingAdmin => 'Billing admin',
            self::SupportLead => 'Support lead',
            self::SupportAgent => 'Support agent',
            self::CsReadonly => 'Read-only',
            self::None => 'No access',
        };
    }

    /**
     * Whether this role may change how the system behaves on the tenant's behalf.
     *
     * FOUND-04's acceptance criterion is specifically that "a `staff` user
     * cannot change autopilot settings", and this is the predicate behind it.
     * Staff read; they do not decide what the automation does.
     */
    public function canConfigureAutomation(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::Agency, self::Owner, self::Manager => true,
            self::Staff => false,
            // Internal staff hold no tenant authority by role. Inside an
            // impersonation session the acting identity is the owner, so this
            // predicate is asked of the owner's role and never of theirs.
            self::OpsAdmin, self::BillingAdmin, self::SupportLead,
            self::SupportAgent, self::CsReadonly, self::None => false,
        };
    }

    /**
     * Whether this person is one of ours.
     *
     * A question about *population*, not about power: it separates the people
     * who work here from tenants and agencies. An agency manages tenants; it is
     * not the platform, and the distinction is the difference between one
     * customer's data and everyone's.
     *
     * ⚠️ **Do not gate a capability on this.** It was the only internal
     * predicate while `super_admin` was the only internal role, so the two
     * questions were indistinguishable and this one answered both. They are not
     * the same question any more: `cs_readonly` is a junior on their first week
     * and is platform staff. Every gate that previously read this one now reads
     * canAdministerPlatform(), which is what it always meant.
     */
    public function isPlatformStaff(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::OpsAdmin, self::BillingAdmin,
            self::SupportLead, self::SupportAgent, self::CsReadonly => true,
            self::Agency, self::Owner, self::Manager, self::Staff, self::None => false,
        };
    }

    /**
     * Whether this role's authority is a tenant's rather than ours.
     *
     * The separation `StaffDirectory` refuses to cross in either direction: the
     * internal console may not hand somebody authority inside a business, and it
     * may not take one away either. `super_admin` is deliberately **not** here
     * despite sitting among the original five — it is ours, which is why
     * isPlatformStaff() has always said so — and `none` is neither, because it
     * is the absence of both.
     */
    public function isTenantRole(): bool
    {
        return match ($this) {
            self::Agency, self::Owner, self::Manager, self::Staff => true,
            self::SuperAdmin, self::OpsAdmin, self::BillingAdmin,
            self::SupportLead, self::SupportAgent, self::CsReadonly, self::None => false,
        };
    }

    /**
     * Whether this role administers the platform itself.
     *
     * Platform-global settings, the model router, plan prices, Horizon's
     * dashboard — everything whose blast radius is every tenant at once. This
     * is deliberately still `super_admin` alone: `28` §9.1 gives `ops_admin`
     * "no platform-global settings", and the other four less again.
     *
     * Widening it is a per-screen decision, made on the screen. Adding a role
     * here changes every one of them at once, which is how a junior ends up
     * able to edit a price.
     */
    public function canAdministerPlatform(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * The strongest impersonation mode this role may open, or null for none.
     *
     * `28` §9.4: view-only is `support_agent` and above, act-as is
     * `support_lead` and above. "Above" is by internal seniority, not by this
     * enum's declaration order — `billing_admin` is not a support role and gets
     * neither, because nothing in `28` §9.1 gives it one and the conservative
     * reading is the one that does not hand out a session.
     *
     * ⚠️ Returning the *strongest* mode rather than a per-mode boolean is what
     * stops the two questions drifting apart. A `canImpersonate(mode)` pair
     * invites a caller to ask only the one it cares about, and the caller that
     * asks "may they act?" and forgets "may they view?" is the harmless
     * direction — the other way round is a support agent with write access.
     */
    public function strongestImpersonationMode(): ?ImpersonationMode
    {
        return match ($this) {
            self::SuperAdmin, self::SupportLead => ImpersonationMode::Act,
            self::OpsAdmin, self::SupportAgent => ImpersonationMode::View,
            self::BillingAdmin, self::CsReadonly,
            self::Agency, self::Owner, self::Manager, self::Staff, self::None => null,
        };
    }

    /**
     * Whether this role may suspend a tenant, or lift a suspension.
     *
     * `28` §9.5 names the two roles for Suspend outright — `super_admin` and
     * `ops_admin` — so this is quoted rather than derived. It is deliberately
     * **not** `canAdministerPlatform()`: that predicate is `super_admin` alone
     * and §9.1 gives `ops_admin` "all operations tooling", which is exactly what
     * this is.
     *
     * ⚠️ **IT IS NOT `strongestImpersonationMode()` EITHER, AND THE OVERLAP IS
     * PARTIAL IN BOTH DIRECTIONS.** `support_lead` may open an act-as session
     * and may not suspend; `ops_admin` may suspend and gets only view-only.
     * Those are two different authorities — one is *acting as the customer*, the
     * other is *acting on the platform's behalf against them* — and a single
     * predicate covering both would hand whichever role was added next an
     * authority nobody granted it.
     *
     * Lifting takes the same gate as applying, on purpose. `28` §9.5 does not
     * name a role for the counterpart, and a lift that a wider set of people
     * could perform would make the suspension only as strong as its weakest
     * reverser — which is the whole of what a compliance stop is for.
     */
    public function canSuspendTenants(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::OpsAdmin => true,
            self::BillingAdmin, self::SupportLead, self::SupportAgent, self::CsReadonly,
            self::Agency, self::Owner, self::Manager, self::Staff, self::None => false,
        };
    }

    /**
     * Whether this role may pause a tenant's account on their behalf, or start
     * it again for them.
     *
     * `28` §9.5's *"pause on client's behalf — same as the owner's Pause
     * Everything, attributed to support"*, and §9.3's quick-actions rail. §9.5
     * gives Suspend a role gate and this one none, so it is derived rather than
     * quoted, and the derivation is stated because a derived gate is the kind
     * that drifts:
     *
     *   - It is a **change made on the customer's behalf**, which `28` §9.4 puts
     *     at `support_lead` and above — an agent who may only *view* an account
     *     must not be able to stop the product for it.
     *   - Anybody who may **suspend** an account may obviously perform the
     *     strictly lesser act of pausing it, which adds `ops_admin`.
     *
     * So it is the union of those two, and it is deliberately wider than
     * {@see self::canSuspendTenants()} rather than the same list: a pause is
     * reversible by the owner themselves from `/account`, and a suspension is
     * not.
     */
    public function canPauseTenantsOnBehalf(): bool
    {
        return $this->canSuspendTenants()
            || $this->strongestImpersonationMode() === ImpersonationMode::Act;
    }

    /**
     * Whether this role may connect or disconnect a provider account.
     *
     * Connecting an account hands the platform a credential that acts as the
     * business, so it sits with the people who own the relationship rather than
     * with everyone who can log in.
     */
    public function canManageConnections(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::Agency, self::Owner => true,
            self::Manager, self::Staff => false,
            // A credential that acts as the business is exactly what an
            // impersonation session must not be able to mint — see
            // ImpersonationCapability::ManageConnections.
            self::OpsAdmin, self::BillingAdmin, self::SupportLead,
            self::SupportAgent, self::CsReadonly, self::None => false,
        };
    }

    /**
     * Whether this role may grant a tenant credits from the support console.
     *
     * `28` §9.3's quick-actions rail lists *"grant credits"* beside pause/resume,
     * and §9.1 connects a role to money moving into a tenant's ledger in exactly
     * two places: `support_agent`'s *"credits grant ≤ 500 with reason"* and
     * `support_lead`'s *"refunds ≤ $200"* — the second is not credits by name,
     * but it is the only other line in that table naming a figure a role may
     * move on a tenant's account, so it is read as the same population rather
     * than invented separately. `super_admin` is *"everything"*.
     *
     * ⚠️ **`ops_admin` IS DELIBERATELY EXCLUDED, THOUGH IT ALREADY REACHES THIS
     * SCREEN.** {@see self::canPauseTenantsOnBehalf()} extends a stop/start
     * authority to `ops_admin` by derivation — pausing is a strictly lesser act
     * than suspending, which `28` §9.5 names it for outright. Money does not
     * chain the same way: `ops_admin`'s whole line in §9.1 is *"operations
     * tooling, integrations health, fix toolbox, flags per tenant"*, with no
     * word about billing, refunds or credits anywhere in it, so there is no
     * lesser-included authority to derive it from. `billing_admin` is excluded
     * for a different reason — {@see self::strongestImpersonationMode()}
     * answers null for it, so it cannot open Account 360 at all, and granting an
     * ability on a screen a role cannot reach would be dead code.
     */
    public function mayGrantCredits(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::SupportLead, self::SupportAgent => true,
            self::OpsAdmin, self::BillingAdmin, self::CsReadonly,
            self::Agency, self::Owner, self::Manager, self::Staff, self::None => false,
        };
    }

    /**
     * The most of one product this role may grant a tenant in one action, in that
     * product's own ledger unit ({@see CreditProduct::unit()}), or null for no
     * ceiling.
     *
     * ⛔ **THE PRODUCT IS A PARAMETER AND HAS NO DEFAULT** (3426). This method
     * answered with no arguments until the ledger held three products, and every
     * caller meant SMS because SMS was all that could be granted. A default of
     * `CreditProduct::Sms` would have left both callers compiling and reading
     * correctly while an email or an AI grant was measured against a limit
     * denominated in text messages — 3419's own argument for `balance()`, and the
     * compiler error at each call site is again the migration.
     *
     * ⛔ **THE UNIT IS WHY ONE FIGURE CANNOT GOVERN THREE.** `28` §9.1 gives a
     * number to exactly one role and it is *"credits grant ≤ 500"* — **500
     * texts**. The same 500 is half a tenant's monthly email allotment (3298), and
     * on AI it is **five cents**, because that pool counts hundredths of a cent
     * (3420). Reusing it would be three different policies wearing one integer.
     *
     * ⛔ **NULL NOW HAS EXACTLY ONE READING, AND IT USED TO HAVE TWO** (3840).
     * Null means **unbounded** — `support_lead` and `super_admin` get no figure in
     * `28` §9.1 **on any product**, and that silence is unchanged by 3426, so it is
     * read the same way it always was rather than re-derived per product. Every
     * other role returned *the same null* while meaning *may not grant at all*, so
     * one value carried the widest possible answer and the narrowest at once —
     * and `CreditGrants::grant()` read it as the wide one, because that is what
     * `$ceiling !== null` asks. **The tell was that the two readings were written
     * down in this very docblock and the code could not tell them apart.**
     *
     * ⚠️ **THE OTHER TWO ANSWERS ARE BOTH REFUSALS AND THEY ARE DIFFERENT
     * REFUSALS.** `support_agent`'s email and AI ceilings are the owner's to set,
     * are not in `28` §9.1, and are withheld rather than defaulted — asking raises
     * {@see WithheldGrantCeiling}, which is `WithheldRegistryValue`'s posture (502)
     * and `CreditUnit::perCent()`'s shape (3433): a `match` with a throwing arm is
     * the only form where the unanswerable case is loud. **A role that may not
     * grant at all raises `InvalidArgumentException` instead**, and the two are not
     * merged: a withheld figure is a question waiting for one sentence from the
     * owner, and this one is a question with no answer at all. Telling an operator
     * to *"ask a support lead"* — which is what `WithheldGrantCeiling` says —
     * would be false for a role that will never be admitted here.
     *
     * ⚠️ **THIS STILL DOES NOT RE-DECIDE ELIGIBILITY, IT REFUSES TO ANSWER A
     * QUESTION THAT PRESUPPOSES IT.** {@see CreditGrantAccess} is the gate and
     * {@see CreditGrants::grant()} asks {@see self::mayGrantCredits()} for itself
     * before it asks this; the split is {@see AccountLifecycle}'s, unchanged — a
     * gate answers *may they act*, a service answers *what does the act allow*.
     * What this arm stops is the third caller reading "no ceiling" off a role that
     * has no business here at all.
     *
     * @throws WithheldGrantCeiling when this role's ceiling for this product has
     *                              not been ruled and may not be guessed.
     * @throws InvalidArgumentException when this role may not grant credits at
     *                                  all, so it has no ceiling to report.
     */
    public function creditGrantCeiling(CreditProduct $product): ?int
    {
        return match ($this) {
            self::SupportAgent => match ($product) {
                CreditProduct::Sms => 500,
                CreditProduct::Email, CreditProduct::Ai => throw WithheldGrantCeiling::for($this, $product),
            },
            self::SuperAdmin, self::SupportLead => null,
            self::OpsAdmin, self::BillingAdmin, self::CsReadonly,
            self::Agency, self::Owner, self::Manager, self::Staff, self::None => throw new InvalidArgumentException(
                $this->label().' may not grant credits, so there is no ceiling to report for '
                .$product->value.'. Ask UserRole::mayGrantCredits() first — a null returned here '
                .'means no limit, and returning it for a role that may not grant at all is how an '
                .'ineligible actor reaches the ledger unbounded.'
            ),
        };
    }
}
