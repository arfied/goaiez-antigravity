<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StaffEvent;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One thing that happened to or by an internal account (`28` §9.1).
 *
 * Named `StaffEventRecord` rather than `StaffEvent` because the enum owns that
 * name and the enum is what a reader reaches for first — a model and its own
 * type enum cannot both be `StaffEvent`, and of the two the enum is the one
 * named in prose.
 *
 * ⚠️ NOT TENANT-OWNED, and on the `TenancyTest` scope allowlist for the
 * reason its migration states: internal staff belong to no business, so there is
 * no tenant to scope to and `audit_log` structurally cannot hold either fact
 * (741). What replaces the scope is a chokepoint lint naming
 * `App\Services\Staff\StaffDirectory` as the only file allowed to read or write
 * one, and the admin gate on the one screen that renders them.
 *
 * APPEND-ONLY at the model layer, like `audit_log` (DATA-MODEL §5.14) and
 * `registry_changes`. Known gap, on purpose and shared with both: a Query
 * Builder mass update bypasses model events.
 *
 * @property int $id
 * @property StaffEvent $event
 * @property int $subject_user_id
 * @property string $actor
 * @property ?UserRole $role_before
 * @property ?UserRole $role_after
 * @property ?string $reason
 * @property Carbon $created_at
 * @property-read ?User $subject
 */
final class StaffEventRecord extends Model
{
    /**
     * Append-only rows have no meaningful updated_at; `created_at` is written
     * explicitly by the one service allowed to write here.
     */
    public $timestamps = false;

    protected $table = 'staff_events';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The account this happened to.
     *
     * ⚠️ Safe to eager-load from a platform screen where `Business` was not
     * (626): `User` carries no tenant scope, because a user exists before a
     * business does. That is why the staff view can print an agent's name and
     * prints an account *number*.
     *
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => StaffEvent::class,
            'role_before' => UserRole::class,
            'role_after' => UserRole::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'staff_events is append-only (`28` §9.1). A record of who was given '
                .'access, by whom, and why is worth nothing if it can be edited.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException('staff_events is append-only (`28` §9.1).');
        });
    }
}
