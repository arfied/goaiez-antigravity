<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\BillingTerm;
use App\Enums\LifecycleRung;
use App\Enums\PaymentGateway;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Services\Billing\PlanCharges;
use App\Services\Billing\Subscriptions;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The business's subscription, one row per business (DATA-MODEL §5.12).
 *
 * DATA-MODEL's shape, not Cashier's — **and row 22 slice A settled which of the
 * two wins.** Cashier's own subscription storage is a table also called
 * `subscriptions`, with a different shape, so the two cannot coexist; this one
 * has been here since Stage 0, is tenant-owned with RLS, and is what
 * `MeController` reads. So this row stays and Stripe's subscription state is
 * projected onto it by slice B's webhook handlers.
 *
 * ⚠️ **SLICE B CORRECTED THE OTHER HALF OF THAT PLAN (decision 681).** 581 also
 * said `Business` would take Cashier's `ManagesCustomer` and a `stripe_id`
 * column. It does not: **`stripe_customer_id` is right here**, and has been
 * since Stage 0, so a column on `businesses` would be a second home for one
 * fact — the identical argument that made Cashier's subscription storage lose,
 * applied to columns rather than to tables. `pm_type` and `pm_last_four` are
 * likewise absent, on 582's own rule: nothing shows a card until slice C's
 * portal, and a column with no reader is a shape this project keeps producing.
 * ⛔ **THIS SAID "HAS NOW COUNTED TWELVE TIMES" AND A COUNT IS THE ONE THING
 * THAT CANNOT BE WRITTEN HERE — CORRECTED 2026-08-24.** The population is
 * derived rather than listed now: `php artisan db:column-readers` reads every
 * column from `pg_class` and reports which ones nothing outside `app/Models`
 * names, so the figure belongs with the run that produced it. **The claim these
 * two columns are absent is still true** — enumerated untruncated on 2026-08-24
 * over `app/`, `database/`, `routes/`, `resources/` and `config/`, where a
 * reader or a writer could be: `pm_type` and `pm_last_four` are named twice,
 * here and in the migration explaining why they went onto `businesses` and came
 * back off it. (⚠️ `docs/` names them repeatedly and is deliberately not in
 * that scope — a document discussing a column is not a caller, and folding the
 * two together is how an absence claim gets quietly widened past what was
 * measured.) **Cashier stays installed and unadopted.**
 *
 * `sms_credits_included` is deliberately nullable with no default: the included
 * allowance is undecided (open question F), and null fails closed rather than
 * becoming the de facto price sheet.
 *
 * ⛔ **`sms_credits_included` AND `sms_credits_used` NOW HAVE NEITHER A WRITER
 * NOR A READER, AND THAT IS THE CORRECTED STATE RATHER THAN A NEW GAP
 * (decisions 3111, 3240, 3243).** They never had a writer. Until this slice
 * they had exactly one reader — `MeResource` — which meant `/api/me` reported a
 * permanent `null` and a permanent `0` to every tenant as though they were
 * facts about the account. The reader is gone; the columns stay, because the
 * defect 3111 names is *the wrong answer served to an outside caller* and
 * dropping the schema is a separate migration whose review belongs to whoever
 * builds the allotment (3244).
 *
 * ⚠️ **Anything that reaches for either column is either resurrecting that
 * fiction or building the plan pool.** If it is the second, open question F,
 * 3115's cap-versus-balance collision and 3117's fraud controls all come with
 * it — and `tests/Feature/Architecture/BillingTest.php`'s plan-pool lint is
 * what makes you notice, because this file is now the *only* place in `app/`
 * permitted to name them. **Do not add a second entry to that allowlist to
 * make a read compile.**
 *
 * ⚠️ **`App\Services\Billing\Subscriptions` IS THE ONLY THING THAT MAY WRITE
 * THIS**, enforced by an `ArchitectureTest` lint. The rules about which states
 * may coexist live there, and a `status` set anywhere else is a tenant entitled
 * to the product with nothing billing them.
 *
 * ⚠️ **AND IT NOW CARRIES THE PRICE THIS CUSTOMER AGREED TO (3443, 3444).** The
 * table's own migration said "no money columns here: prices live in Stripe" and
 * that was right for as long as the **gateway** was the only thing that needed a
 * price. It is not: `RenewalReminders` quotes an amount back to somebody who has
 * already bought, and the owner's grandfathering ruling makes today's registry
 * figure the wrong one to quote them the moment a price moves. `price_cents`,
 * `additional_location_cents`, `additional_locations` and `price_currency` are
 * all-or-nothing at the database, and null on every row that predates them — see
 * the migration, and {@see PlanCharges::agreedPriceFor()}
 * for what a null group falls back to.
 *
 * ⚠️ **THE ROW NOW NAMES ITS GATEWAY (decision 2056).** Authorize.Net is
 * additive rather than a replacement, so a business's subscription lives on one
 * of two vendors and says which. Three CHECK constraints hold the shape: a
 * trialing row has a subscription id from *whichever* gateway it is on, a
 * `pending_checkout` row has neither, and a row's ids must match its `gateway`.
 * `gateway` is nullable with no default because most existing rows are
 * `pending_checkout` and are on neither vendor — see the migration.
 *
 * @property-read int $id
 * @property ?Plan $plan
 * @property ?SubscriptionStatus $status
 * @property ?PaymentGateway $gateway
 * @property ?string $stripe_customer_id
 * @property ?string $stripe_subscription_id
 * @property ?string $authorize_net_customer_profile_id
 * @property ?string $authorize_net_payment_profile_id
 * @property ?string $authorize_net_subscription_id
 * @property ?Carbon $authorize_net_synced_at
 * @property ?Carbon $authorize_net_starts_on
 * @property ?int $sms_credits_included
 * @property ?int $sms_credits_used
 * @property ?Carbon $current_period_end
 * @property ?Carbon $trial_ends_at
 * @property ?Carbon $ends_at
 * @property ?Carbon $stripe_synced_at
 * @property ?BillingTerm $term
 * @property ?int $instalment_payments
 * @property ?Carbon $annual_term_ends_on
 * @property ?Carbon $cancellation_requested_at
 * @property ?Carbon $renewal_reminded_for
 * @property ?Carbon $renewal_reminder_claimed_at
 * @property ?LifecycleRung $trial_rung_sent
 * @property ?int $price_cents
 * @property ?int $additional_location_cents
 * @property ?int $additional_locations
 * @property ?string $price_currency
 */
final class Subscription extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * ⚠️ `plan` and `status` are guarded as well as `business_id`.
     *
     * They are the two columns that decide whether a tenant is entitled to the
     * product and what they are charged for it, so an ordinary `update()` from a
     * form or an importer cannot reach them. Reaching past this takes a
     * deliberate `forceFill()`, which is a finding a person will see in review —
     * the same reasoning `BaaRecord` uses for the columns that decide whether an
     * agreement is executed.
     *
     * ⚠️ `gateway` JOINS THEM, FOR THE SAME REASON AND ONE MORE. It decides
     * which vendor every later read consults, so an ordinary `update()` moving
     * it would silently repoint a live subscription at a gateway that has never
     * heard of it — and the CHECK that refuses the incoherent row would then
     * surface as a constraint violation from whatever screen made the change.
     *
     * ⚠️ **AND THE FOUR AGREED-PRICE COLUMNS JOIN THEM, WHICH IS THE STRONGEST
     * CASE OF THE FOUR.** `plan`, `status`, `gateway` and `term` are guarded
     * because they decide what a tenant is charged for; these *are* what they are
     * charged. A form or an importer reaching one would rewrite the agreement
     * 3443 exists to preserve, and it would do it silently — the number would
     * still be a plausible price. Reaching past this takes a deliberate
     * `forceFill()` in {@see Subscriptions}, which is the
     * only writer this table has.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'plan',
        'status',
        'gateway',
        'term',
        'price_cents',
        'additional_location_cents',
        'additional_locations',
        'price_currency',
    ];

    /**
     * Whether the end this row already records has passed.
     *
     * ⛔ **`ends_at` IS THE RECORDED MOMENT SERVICE STOPS, AND UNTIL 2026-08-23
     * NOTHING IN THIS APPLICATION EVER ASKED WHETHER IT HAD ARRIVED** (8960).
     * `Subscriptions::isEntitled()` read `status` and nothing else, and
     * `Subscriptions::applyAuthorizeNetSubscription()`'s paid-term arm
     * deliberately parks a **cancelled** annual row at `active` with
     * `ends_at = annual_term_ends_on` so the tenant keeps the year they bought
     * (2748, 2980–2999). Authorize.Net sends no further notification for a
     * subscription it has already cancelled — verified against the vendor's own
     * webhook reference, read 2026-08-23 — so **nothing ever revisited that row**
     * and the day the year ran out passed with no consequence at all.
     *
     * ⚠️ **IT IS A QUESTION ABOUT A DATE WE RECORDED, NEVER A POLICY ABOUT
     * DELINQUENCY.** `CLAUDE.md` reserves *"what a delinquent tenant loses and
     * when"* for the owner, and this answers nothing about it: `past_due` is
     * untouched, a missing row is untouched, and a `pending_checkout` account
     * never carries a value here. What it refuses is an account whose own
     * recorded end has been and gone — the distinction
     * `Subscriptions::suspendForNonPayment()` already draws, *"the tenant is not
     * being cut off early, they have stopped having a subscription."*
     *
     * ⛔ **IT DELIBERATELY DOES NOT READ `annual_term_ends_on`, AND THAT IS
     * DECISION 2749 RATHER THAN AN OVERSIGHT** (8963). A paid-in-full instalment
     * year that was **never cancelled** carries a **null** `ends_at` — the
     * `expired` arm above resets the status to `active` before `$endsAt` is
     * computed, so it writes null — and 2749 rules that such a row *"stays
     * entitled"* until the owner says otherwise. **`ends_at` is precisely what
     * tells the two populations apart**: one asked to leave and we wrote down the
     * date, the other did not.
     *
     * ⚠️ **A DATE PROJECTED FROM STRIPE MEANS THE SAME THING**, which is why this
     * is not scoped to a gateway: `StripeWebhooks` writes
     * `ended_at ?? cancel_at ?? canceled_at` and names this exact hazard at the
     * write — *"a subscription that says it ended today and is entitled, which is
     * neither state."*
     */
    public function accessHasEnded(): bool
    {
        return $this->ends_at instanceof Carbon && $this->ends_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'status' => SubscriptionStatus::class,
            'gateway' => PaymentGateway::class,
            'authorize_net_synced_at' => 'datetime',
            // `date`, not `datetime`: ARB's `startDate` is a calendar day in the
            // merchant's own time zone and carries no time at all. Casting it to
            // a datetime would invent midnight UTC and make "does the trial end
            // today" answer differently either side of the dateline.
            'authorize_net_starts_on' => 'date',
            'sms_credits_included' => 'integer',
            'sms_credits_used' => 'integer',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'no_card_trial_extended_until' => 'datetime',
            'ends_at' => 'datetime',
            'stripe_synced_at' => 'datetime',
            'term' => BillingTerm::class,
            'instalment_payments' => 'integer',
            // `date` for `authorize_net_starts_on`'s reason: a term ends on a
            // day, and inventing a time on it would make "has the year run out"
            // answer differently either side of the dateline.
            'annual_term_ends_on' => 'date',
            'cancellation_requested_at' => 'datetime',
            // `date`, for `annual_term_ends_on`'s reason: this is the renewal
            // *date* a notice was sent about, and the gate is an equality
            // comparison against another date.
            'renewal_reminded_for' => 'date',
            // ⛔ `datetime`, and the difference from the line above is the whole
            // mechanism rather than a detail. `renewal_reminded_for` is the
            // **record** of a notice — a renewal falls on a day. This is the
            // **claim** that arbitrates who may send it, and it is compared
            // against a moment less than an hour old, which a date cannot
            // express (7100-7119).
            'renewal_reminder_claimed_at' => 'datetime',
            // ⛔ THE CLAIM **AND** THE RECORD FOR THE NO-CARD TRIAL LADDER, in
            // one column, which is the opposite choice from the pair above and
            // is argued at its own migration (9396): a pre-renewal notice is a
            // statutory duty and a trial warning is not, so the failure worth
            // leaving standing here is the missed one rather than the duplicate.
            // ⚠️ A `string` column cast to a backed enum — never a database
            // `enum` — with a CHECK bounding it to the trial rungs.
            'trial_rung_sent' => LifecycleRung::class,
            // ⚠️ `integer`, and the `money is never compared or cast as a float`
            // lint fails the build on a `*_cents` column cast to `float` or
            // `double`. Cents are integers here from the first billing slice
            // precisely so that no later screen has to remember it.
            'price_cents' => 'integer',
            'additional_location_cents' => 'integer',
            'additional_locations' => 'integer',
        ];
    }
}
