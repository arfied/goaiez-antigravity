<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Auth\FailedSignIns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One (address, source, hour) bucket of failed credential checks (9860–9879).
 *
 * ⚠️ NOT TENANT-OWNED, and on `TenancyTest`'s scope allowlist for the reason
 * its migration states: the row is written before anybody is authenticated and
 * most often names no account at all, so there is no tenant to scope by. What
 * replaces the scope is a chokepoint lint naming {@see FailedSignIns} as the
 * only file in `app/` allowed to read or write one.
 *
 * ⛔ **THE ROW IS NOT WRITTEN THROUGH THIS CLASS AND CANNOT BE.** The write is
 * `INSERT … ON CONFLICT … DO UPDATE SET attempts = attempts + 1`, which
 * Eloquent's `upsert()` cannot express — it can only set a column to a literal,
 * and a read-modify-write through the model would lose counts under exactly the
 * concurrency this table exists to measure. {@see FailedSignIns::record()} is
 * the statement; this class is the read shape.
 *
 * ⚠️ **SO THE APPEND-ONLY REFUSALS BELOW ARE NARROWER THAN `StaffEventRecord`'s
 * LOOK.** They refuse the model layer only, and the sanctioned writer reaches
 * past them the way `PruneTrialOriginClaims` reaches past `TrialClaim`'s: one
 * named writer, argued at the line. A Query Builder mass update bypasses model
 * events — the known gap this shares with `audit_log` and `registry_changes`.
 *
 * @property int $id
 * @property string $email_hash
 * @property ?string $ip_hash
 * @property Carbon $window_start
 * @property bool $account_existed
 * @property int $attempts
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
final class FailedSignIn extends Model
{
    /**
     * The bucket carries its own two timestamps, written explicitly by the one
     * statement allowed to write here. `created_at`/`updated_at` would say the
     * same thing twice and disagree the first time a repair script touched a
     * row.
     */
    public $timestamps = false;

    protected $table = 'failed_sign_ins';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'account_existed' => 'boolean',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'failed_sign_ins is written by App\Services\Auth\FailedSignIns::record() and '
                .'by nothing else. A security register an ordinary save can edit records nothing.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'failed_sign_ins rows are removed by auth:prune-failed-sign-ins on a clock and '
                .'by nothing else — see App\Support\TableHorizons.'
            );
        });
    }
}
