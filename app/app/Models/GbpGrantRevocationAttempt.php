<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GbpRevocationOutcome;
use App\Services\Gbp\GbpConnections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One recorded attempt to end a Google grant a deleted tenant left behind —
 * the log behind `gbp_account_bindings.revocation_owed_at` (4880, 4888(a)).
 *
 * ⚠️ **NOT TENANT-SCOPED, AND NOT A GAP — SEE THE CREATING MIGRATION.** Every
 * row here describes a business `TenantDeletion` has already destroyed, so
 * there is no tenant left to scope it to; `audit_log` cannot hold this for the
 * same reason `registry_changes` and `credential_changes` cannot (their own
 * migrations record it). This table is that pattern applied a third time.
 *
 * {@see GbpConnections} is the only writer: every attempt —
 * the immediate one `TenantDeletion` makes right after erasure, the nightly
 * sweep's, and an operator's own retry from the Ops screen — runs through its
 * `attemptRevocation()`, so there is exactly one place this table's `outcome`
 * can be decided.
 *
 * APPEND-ONLY, like `audit_log`, `registry_changes` and `credential_changes`:
 * a record of what was tried is falsified by editing it after the fact.
 * ⛔ **AND IT IS ENFORCED IN `booted()` BELOW RATHER THAN STATED HERE** (5070).
 * This paragraph named two models that guard themselves and then guarded
 * nothing, which is 314–316's shape exactly: the sentence claiming the
 * protection is what stops the next reader looking for the mechanism. The
 * stakes are the whole of why this table exists — it is the only surviving
 * record that anybody tried to end a subprocessor's read-and-write grant on a
 * deleted customer's Google listing, and the only record of which operator
 * pressed the button.
 *
 * @property-read int $id
 * @property string $account_ref
 * @property int $business_id
 * @property int $location_id
 * @property GbpRevocationOutcome $outcome
 * @property ?string $reason
 * @property string $actor
 * @property Carbon $created_at
 */
final class GbpGrantRevocationAttempt extends Model
{
    /**
     * Append-only rows have no meaningful `updated_at`; `created_at` is
     * written explicitly by the one service allowed to write here —
     * `CredentialChange`'s own shape.
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
            'outcome' => GbpRevocationOutcome::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * ⛔ **THE MECHANISM BEHIND THE CLASS DOCBLOCK'S "APPEND-ONLY"** —
     * `CredentialChange`'s and `RegistryChange`'s guard, word for word, on a log
     * with more riding on it than either: an edited row here would misattribute
     * an attempt on a former customer's Google listing to the wrong operator,
     * and a deleted one would erase the fact that the grant was ever chased at
     * all. Nothing in `app/` updates or deletes a row here, which is precisely
     * the condition under which the first such write gets waved through.
     */
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'The grant revocation log is append-only. A record of what was tried '
                .'against a deleted customer\'s Google grant, and by whom, is worth '
                .'nothing if it can be edited afterwards.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException('The grant revocation log is append-only.');
        });
    }
}
