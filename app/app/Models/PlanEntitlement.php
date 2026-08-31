<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One version of one per-plan registry value (DATA-MODEL §Part 11).
 *
 * Ours, not a tenant's — these are the terms a business is billed under, not
 * data it owns — so the model sits on the `TenancyTest` scope allowlist
 * with its argument written there, alongside `LegalDocument` for the same
 * reason.
 *
 * ⚠️ **A ROW IS NEVER UPDATED.** An edit writes version + 1; the old row stays
 * and is what `38` D-151's grandfathering reads. Updating in place would destroy
 * the price a subscriber signed up under, and nothing anywhere records it
 * otherwise — the same unrecoverable-damage argument that made a published
 * `legal_documents` row immutable (417–420). Deletes are refused for the same
 * reason. `DefaultsRegistry` is the only writer.
 *
 * @property int $id
 * @property Plan $plan
 * @property string $key
 * @property mixed $value
 * @property int $version
 * @property Carbon $effective_at
 * @property string $set_by
 */
final class PlanEntitlement extends Model
{
    /**
     * `effective_at` and `version` say when a value applies and in what order.
     * A created_at would say when the row was inserted, which is a third
     * timestamp answering a question nobody asks of this table.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The current version of one key for one plan, or null when unset.
     *
     * Ordered by `version` rather than by `effective_at`: a value can be written
     * to take effect later, and "highest version" is the one this reads. NULLS
     * LAST is said out loud even though `version` is NOT NULL — the convention
     * test asks for it on every descending sort but `id`, and a column that is
     * NOT NULL today is one migration away from not being.
     */
    public static function current(Plan $plan, string $key): ?self
    {
        return self::query()
            ->where('plan', $plan->value)
            ->where('key', $key)
            ->orderByRaw('version DESC NULLS LAST')
            ->first();
    }

    /**
     * Every current value for one plan, keyed by registry key.
     *
     * One query rather than one per key. `DISTINCT ON` is Postgres-specific and
     * this application is Postgres-only by decision 131.
     *
     * @return array<string, self>
     */
    public static function currentFor(Plan $plan): array
    {
        /** @var array<int, self> $rows */
        $rows = self::query()
            ->fromSub(
                self::query()
                    ->selectRaw('DISTINCT ON (key) *')
                    ->where('plan', $plan->value)
                    ->orderByRaw('key ASC, version DESC NULLS LAST'),
                'current_entitlements',
            )
            ->get()
            ->all();

        $byKey = [];

        foreach ($rows as $row) {
            $byKey[$row->key] = $row;
        }

        ksort($byKey);

        return $byKey;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'value' => 'json',
            'version' => 'integer',
            'effective_at' => 'datetime',
        ];
    }

    /**
     * Append-only at the model layer, like `audit_log` and a published
     * `legal_documents` row.
     *
     * Known gap, the same one AuditLogEntry documents: a Query Builder mass
     * update or delete bypasses model events. That path is what code review
     * watches for, and the `ArchitectureTest` chokepoint lint narrows who can
     * reach this table at all to one service.
     */
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'A plan entitlement is versioned, not edited. Write a new version — '
                .'the old value is what an existing subscriber is still billed under.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'A plan entitlement is never deleted. The row a subscriber signed up '
                .'under is the only record of what they agreed to pay.'
            );
        });
    }
}
