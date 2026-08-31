<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\AutopilotActionType;
use Database\Factories\ActivityFeedItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry in the owner-visible activity feed (DATA-MODEL §5.12).
 *
 * APPEND-ONLY (§5.14): every automated action writes one row, and history is
 * only history if it cannot be rewritten. Updates and deletes throw here at
 * the model layer. Known gap, on purpose: a Query Builder mass
 * update/delete bypasses model events — that path is what code review and
 * laravel-reviewer watch for, the same as a raw enum ALTER.
 *
 * ⚠️ **THE `@property` LINES ARE LOAD-BEARING, NOT DECORATION** —
 * `OutreachMessage`'s docblock argues it at length and the reason is identical
 * here. Larastan does not infer an enum or a `Carbon` from `casts()` alone, so
 * without them a `match ($item->action_type)` against `AutopilotActionType`
 * cases reads as a comparison between a string and an enum and fails level 8 as
 * *"always evaluate to true"* — while working perfectly at runtime, because the
 * cast is real. They arrived with the feed's first reader (6280): for the whole
 * life of this table nothing read a row back, so nothing had ever needed them.
 *
 * @property int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property AutopilotActionType $action_type
 * @property string $title
 * @property ?array<string, mixed> $metadata
 * @property ?Carbon $created_at
 */
final class ActivityFeedItem extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ActivityFeedItemFactory> */
    use HasFactory;

    /**
     * Append-only rows have no meaningful updated_at.
     */
    public const null UPDATED_AT = null;

    /**
     * The model is the row, not the feed, so the class name is singular-ish
     * and the table name cannot be derived from it.
     */
    protected $table = 'activity_feed';

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'activity_feed is append-only (DATA-MODEL §5.14). Write a new '
                .'row instead of editing history.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'activity_feed is append-only (DATA-MODEL §5.14). Rows are '
                .'never deleted.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_type' => AutopilotActionType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
