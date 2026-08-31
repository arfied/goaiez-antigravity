<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrialClaimKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One identity, claimed by one account, at one moment.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there — and it
 * is the fifth model in this schema that *names* a tenant and still cannot take
 * the trait, after `ImpersonationSession` (562), `StripeCustomer` (682),
 * `AuthorizeNetCustomer` and `SupportQueueEntry`. The shape here is
 * `SupportQueueEntry`'s rather than `StripeCustomer`'s: it does not establish a
 * tenant for a reader who has none, it answers a question that **cannot be asked
 * one account at a time**. "Has any other account already claimed this identity?"
 * is a cross-tenant question by construction, and under `FORCE` row-level
 * security a tenant-scoped table answers it with zero rows — `withoutGlobalScope()`
 * included, because the policy is the database's (569).
 *
 * ⚠️ **THE ROW HOLDS NO CLEAR IDENTITY, ONLY A KEYED HMAC OF ONE.** See the
 * creating migration for the two rules that force that: `29`'s "never store raw
 * IP", and `stripe_customers`' rule that a cross-tenant readable table must hold
 * nothing a tenant could be harmed by another reader seeing.
 *
 * ⚠️ **APPEND-ONLY IN BOTH LAYERS, AND THE MODEL IS THE WEAKER ONE.** A trigger
 * on the table refuses an UPDATE outright; this refuses one earlier, with a
 * message a developer can act on rather than a SQLSTATE.
 *
 * ⛔ **DELETE IS REFUSED HERE AND DELIBERATELY *NOT* AT THE DATABASE, AND THE
 * ASYMMETRY IS THE WHOLE POINT** (3237). The creating migration's first draft
 * said freezing DELETE "would make deleting a tenant impossible" and used that
 * to leave it open in **both** layers. It is true of a **trigger** — the foreign
 * key cascade is a database-level delete and a `BEFORE DELETE` trigger would
 * refuse it, making a tenant undeletable — and it is **false of a model hook**,
 * because Eloquent's `deleting` event never fires for a cascade. So this guard
 * costs tenant deletion nothing and closes the door that was left open: a repair
 * script or a future cleanup command calling `TrialClaim::query()->delete()`,
 * silently un-burning every identity in the register with no trigger underneath
 * to stop it.
 *
 * @property int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property TrialClaimKind $kind
 * @property string $identity_hash
 * @property CarbonImmutable $created_at
 */
final class TrialClaim extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'trial_claims';

    /**
     * ⚠️ **EVERY COLUMN, AND IT COSTS NOTHING BECAUSE NOTHING MASS-ASSIGNS ONE.**
     * `TrialEligibility` writes through `DB::table()->insertOrIgnore()`, so the
     * guard is never in the one writer's way. What it closes is the shape that
     * would be worst on this table specifically: `business_id` decides whose
     * claim a row is, and this is **the one table in the schema where writing
     * the wrong one is not refused by row-level security**, because the policy
     * is permissive by design. An open `$guarded` there is one request-shaped
     * array away from filing one account's identity under another's.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'location_id', 'kind', 'identity_hash', 'created_at'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'trial_claims is append-only: a claim records that an identity was used '
                .'at a moment in time, and rewriting one un-burns it. Write a new claim.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'trial_claims is append-only: deleting a claim un-burns the identity it '
                .'recorded. Deleting a tenant releases its claims through the foreign key '
                .'cascade, which does not raise this event.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'location_id' => 'integer',
            'kind' => TrialClaimKind::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
