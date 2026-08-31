<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use Database\Factories\KnowledgeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What the Business Brain was built from (DATA-MODEL §5.9).
 *
 * ⚠️ **THIS TABLE HAD NO WRITER, NO READER AND NO EMBEDDER UNTIL 2026-08-12.**
 * It shipped at Stage 0 with a factory and an isolation test and nothing in
 * `app/` that touched it — decision 272's shape, and the tell CLAUDE.md names:
 * an isolation test passing perfectly against a table nothing writes, so the
 * suite is green and the feature is inert. `KnowledgeIngestor` is the writer,
 * `KnowledgeRetriever` the reader, `IngestKnowledgeSourceJob` the embedder.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property KnowledgeSourceType $type
 * @property ?string $url
 * @property ?string $file_path
 * @property ?int $byte_size
 * @property ?string $title
 * @property KnowledgeSourceStatus $status
 * @property ?Carbon $last_ingested_at
 * @property ?string $checksum
 * @property int $version
 * @property bool $is_active
 */
final class KnowledgeSource extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<KnowledgeSourceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => KnowledgeSourceType::class,
            'status' => KnowledgeSourceStatus::class,
            'is_active' => 'boolean',
            'last_ingested_at' => 'datetime',
            'version' => 'integer',
            // Null for a `Url` source, which has no object anywhere, and for any
            // upload stored before 4762 added the column. Never a zero.
            'byte_size' => 'integer',
        ];
    }

    /**
     * The embedded slices this source was broken into.
     *
     * ⚠️ THE RELATIONSHIP IS SCOPED ON BOTH SIDES, WHICH IS NOT REDUNDANT. The
     * foreign key alone would be enough if `source_id` were globally unique, and
     * it is — but `KnowledgeChunk` carries `BelongsToTenant`, so reading through
     * this relationship stays inside the tenant even if a source id from
     * somewhere else is ever handed to it. The multi-tenancy skill's "joins to
     * unscoped tables" trap, avoided by using a relationship rather than a join.
     *
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'source_id');
    }
}
