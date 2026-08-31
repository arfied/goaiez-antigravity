<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\AutomationRunStatus;
use Database\Factories\AutomationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution of one automation (DATA-MODEL §5.12). Written by the
 * AutopilotJob base class (FOUND-05) on start and finish.
 *
 * started_at/finished_at are the lifecycle; the table has no created_at or
 * updated_at at all.
 *
 * ⚠️ **THE `@property` LINES ARE WHAT MAKE {@see casts()} VISIBLE TO STATIC
 * ANALYSIS**, added 2026-08-26 (9820–9839) when `VisibilitySyncHistory` became
 * the first thing in `app/` to read `status` or `output` as attributes rather
 * than through a query. Larastan types a model's properties from the schema
 * unless it is told otherwise, so without these it reads `status` as `string`
 * and every `match` arm against an enum case is "always false" — a level-8
 * failure over code that is correct at runtime. `OauthConnection` carries the
 * same pair of lines for the same reason.
 *
 * @property AutomationRunStatus $status
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property int|null $reply_id
 */
final class AutomationRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<AutomationRunFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * ⚠️ **`reply_id` IS GUARDED, AND `$guarded` REFUSES `fill()` AND NOTHING
     * ELSE** (`CLAUDE.md` §Critical rules — six of eight write shapes evade
     * it). It is here as the cheap layer anyway: the column is
     * `GENERATED ALWAYS AS (…) STORED` from the creating migration
     * (`2026_08_26_172707_add_reply_id_to_automation_runs.php`), and Postgres
     * itself refuses an explicit value for a generated column in an `INSERT` —
     * the real protection is the schema, not this array.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'reply_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AutomationRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'quality_score' => 'integer',
            'gate_passed' => 'boolean',
            'reply_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
