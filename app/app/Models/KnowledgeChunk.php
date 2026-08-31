<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Support\Tenancy;
use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An embedded slice of a knowledge source (DATA-MODEL §5.9).
 *
 * Similarity search lives here, as one method, on purpose. Laravel 13 ships no
 * vector search, so a distance query is raw SQL that passes through no global
 * scope — which makes every hand-written one a place the tenant boundary can be
 * left off. Having exactly one implementation means there is exactly one place
 * to get it right, and a build-failing test that watches it (`29` §2.4,
 * decision 139).
 *
 * If you find yourself writing `embedding <=>` anywhere else, the answer is
 * almost always to extend this method instead.
 */
final class KnowledgeChunk extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<KnowledgeChunkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

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
            'metadata' => 'array',
            'token_count' => 'integer',
        ];
    }

    /**
     * The nearest chunks to an embedding, within the current tenant.
     *
     * Three things hold the boundary here, and they are deliberately redundant:
     *
     *   1. The explicit `business_id = ?` predicate below. Raw SQL reaches no
     *      global scope, so this is the application layer's only contribution —
     *      and a build-failing test asserts it is present.
     *   2. `Tenancy::idOrFail()`, which throws rather than returning null, so a
     *      missing tenant cannot quietly widen the search.
     *   3. The RLS policy on the table, which catches the case where someone
     *      adds a second query and forgets (1).
     *
     * @param  list<float>  $embedding  1536 dimensions, matching the column
     * @return Collection<int, self>
     */
    public static function nearestTo(array $embedding, int $limit = 5): Collection
    {
        $businessId = Tenancy::idOrFail();

        // pgvector takes its literal as a bracketed list, not a Postgres array.
        $vector = '['.implode(',', $embedding).']';

        $rows = DB::select(
            'SELECT id
               FROM knowledge_chunks
              WHERE business_id = ?
              ORDER BY embedding <=> ?
              LIMIT ?',
            [$businessId, $vector, $limit],
        );

        // DB::select() returns stdClass, so the column has no declared type.
        // Casting through an array keeps the shape explicit rather than
        // asserting a property PHPStan cannot see.
        $ids = array_map(
            static fn (object $row): int => (int) ((array) $row)['id'],
            $rows,
        );

        if ($ids === []) {
            return new Collection;
        }

        // Re-hydrate through Eloquent so the global scope applies a second time,
        // and preserve the distance ordering the raw query established.
        return self::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(static fn (self $chunk): int => (int) array_search($chunk->id, $ids, true))
            ->values();
    }
}
