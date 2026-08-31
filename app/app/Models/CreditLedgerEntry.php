<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CreditKind;
use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use Database\Factories\CreditLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One movement of a tenant's credit balance (`DATA-MODEL` §Credits & broadcasts).
 *
 * APPEND-ONLY, on `AuditLogEntry`'s precedent and for the same reason: a balance
 * history that can be edited is not a history. Updates and deletes throw at the
 * model layer. **Known gap, on purpose and inherited**: a Query Builder mass
 * update or delete bypasses model events entirely — that path is what code review
 * and `laravel-reviewer` watch for, and it is the same gap `audit_log` carries
 * rather than a new one this table introduces.
 *
 * ⚠️ **`balance_after` ON THE HIGHEST id IS THE BALANCE OF ITS OWN PRODUCT AND
 * POOL** — since decision 3419, and it was the pool's alone from 3307. It has
 * never been the tenant's since either landed. Not a SUM, and not
 * `subscriptions.sms_credits_used`, which is a plan-pool placeholder that this
 * table never mirrors. Read it through `App\Services\Billing\CreditLedger`, which
 * an `ArchitectureTest` lint makes the only file in `app/` allowed to touch this
 * model at all.
 *
 * ⛔ **`delta` IS NOT ONE UNIT OF ONE THING — ITS MEANING DEPENDS ON `product`.**
 * A `-1` on an {@see CreditProduct::Sms} row is one text; on a
 * {@see CreditProduct::Ai} row it is one hundredth of a cent
 * ({@see App\Enums\CreditUnit}). Any reader summing across products is adding
 * messages to money, which is why nothing may reach this model but the service.
 *
 * @property int $id
 * @property int $business_id
 * @property int $delta
 * @property int $balance_after
 * @property CreditKind $kind
 * @property CreditPool $pool
 * @property CreditProduct $product
 * @property ?string $ref_type
 * @property ?int $ref_id
 * @property ?string $reason
 * @property string $created_by
 */
final class CreditLedgerEntry extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CreditLedgerEntryFactory> */
    use HasFactory;

    /**
     * Append-only rows have no meaningful updated_at, and the column does not
     * exist — Eloquent would try to write one without this.
     */
    public const null UPDATED_AT = null;

    /**
     * The model is one movement; the table is the ledger.
     */
    protected $table = 'credit_ledger';

    /**
     * `business_id` is guarded for `AuditLogEntry`'s reason: it comes from the
     * tenant in context via `BelongsToTenant`, never from a caller's array.
     *
     * `balance_after` is guarded too, which is the load-bearing one here. It is
     * the authoritative balance, and a mass-assignable running total is one a
     * caller can set to any number it likes while every constraint above still
     * passes — the service computes it under a lock or it is not trustworthy.
     *
     * `pool` joins them for the same reason one level up (3307). It decides which
     * running total this row belongs to and whether the credits on it expire at
     * the period boundary, so it is the service's to choose from the kind and the
     * draw order — never a caller's to assert. A caller that could name the pool
     * could put a purchase in the monthly pool and watch it evaporate at the end
     * of the month, and two of the three CHECKs added with the column exist
     * because that mistake is unrecoverable in an append-only table.
     *
     * `product` joins them for the strongest version of the same reason (3419).
     * It decides **which of six running totals** this row belongs to *and what its
     * `delta` is denominated in* — a message or a hundredth of a cent. A caller
     * that could name the product could pay for a text out of the AI balance, and
     * the two are not even the same kind of quantity, so the row would be wrong in
     * a way no CHECK can express and no later edit can undo.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'balance_after', 'pool', 'product'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'credit_ledger is append-only (29 §299). Write a new row '
                .'instead of editing the balance history.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'credit_ledger is append-only (29 §299). Rows are never '
                .'deleted — reverse a movement with a new one.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CreditKind::class,
            'pool' => CreditPool::class,
            'product' => CreditProduct::class,
            'delta' => 'integer',
            'balance_after' => 'integer',
            'ref_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
