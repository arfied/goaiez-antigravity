<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CredentialChangeAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One recorded change to a vendor credential (`38` Part 1: "Rotation and every
 * reveal-less set is audit-logged").
 *
 * `AuditService` cannot serve this, for the reason `RegistryChange` already
 * records: `audit_log.business_id` is NOT NULL and its RLS policy is keyed on
 * the session tenant, so a platform-scoped act has no tenant to write under and
 * fails closed. This is that table's sibling, deliberately just as narrow.
 *
 * ⚠️ **IT HOLDS NO VALUE, IN EITHER DIRECTION, NOT EVEN ENCRYPTED.** A change
 * log carrying every superseded secret is a second store of every key this
 * platform has ever held — one that grows forever and is never itself rotated —
 * which inverts the point of rotation: a key is rotated *because* the old value
 * became dangerous. `last_four_before` and `last_four_after` are enough to
 * answer "did the value actually change", and are worth nothing to whoever
 * steals the table.
 *
 * APPEND-ONLY at the model layer, like `audit_log` and `registry_changes`.
 *
 * @property int $id
 * @property string $credential_key
 * @property CredentialChangeAction $action
 * @property ?string $last_four_before
 * @property ?string $last_four_after
 * @property string $actor
 * @property Carbon $created_at
 */
final class CredentialChange extends Model
{
    /**
     * Append-only rows have no meaningful `updated_at`; `created_at` is written
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
            'action' => CredentialChangeAction::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'The credential change log is append-only. A record of when a key was '
                .'rotated is worth nothing if it can be edited afterwards.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException('The credential change log is append-only.');
        });
    }
}
