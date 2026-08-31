<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\CrmNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A free-text note against a customer (DATA-MODEL §5.5).
 *
 * Written and read only by `App\Services\Crm\CrmNotes`, held there by an
 * `ArchitectureTest` lint. Read that service before adding a second query: the
 * ordering and the `created_at` stamping are both load-bearing and neither is
 * obvious from this file.
 *
 * @property ?Carbon $created_at
 * @property string $body
 */
final class CrmNote extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CrmNoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * `crm_notes` has `created_at` and no `updated_at`, so Eloquent's automatic
     * stamping is off wholesale — which is also why nothing casts `created_at`
     * for us and the cast below is written by hand.
     */
    public $timestamps = false;

    /**
     * Who typed it.
     *
     * ⚠️ Nullable in the schema and never null on any row this application
     * writes: `CrmNotes::add()` refuses a note with no author. The column stays
     * nullable because `users` is `nullOnDelete` here — a note has to survive
     * the person who wrote it leaving, and losing the note would be worse than
     * losing the name.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
