<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One recorded change to a `platform_settings` value.
 *
 * `38` Part 2's Ops UX: "every change is audited with before/after". `29` §2
 * rule 42: every sensitive action reaches an append-only log. `AuditService`
 * serves neither here — `audit_log.business_id` is NOT NULL and its RLS policy
 * is keyed on the session tenant, so a platform-wide settings change has no
 * tenant to write under and would fail closed. This is the platform-scoped
 * equivalent, deliberately narrow.
 *
 * NOT NEEDED FOR `plan_entitlements`, which versions instead: there the previous
 * value is still a row, which beats a log entry describing one. `platform_
 * settings` is keyed on `key` alone and cannot do that.
 *
 * APPEND-ONLY at the model layer, like `audit_log` (DATA-MODEL §5.14). Ours,
 * not a tenant's, so it sits on the `TenancyTest` scope allowlist.
 *
 * ⚠️ **A VALUE HERE IS OPERATIONAL, NEVER PERSONAL.** These are our caps,
 * budgets and prices. Nothing in this table should ever hold customer data — the
 * registry is not a place to put a per-tenant anything, which is the reason
 * `platform_settings` refuses a nullable `business_id` in the first place.
 *
 * @property int $id
 * @property string $setting_key
 * @property mixed $value_before
 * @property mixed $value_after
 * @property string $actor
 * @property Carbon $created_at
 */
final class RegistryChange extends Model
{
    /**
     * Append-only rows have no meaningful updated_at; `created_at` is written
     * explicitly by the one service allowed to write here.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_before' => 'json',
            'value_after' => 'json',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'The registry change log is append-only. A record of what a value used '
                .'to be is worth nothing if it can be edited.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException('The registry change log is append-only.');
        });
    }
}
