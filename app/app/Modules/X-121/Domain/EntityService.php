<?php

declare(strict_types=1);

namespace App\Modules\X121\Domain;

use App\Modules\X121\Events\FactInvalidated;
use App\Modules\X121\Models\EntityHistoryRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EntityService
{
    private const HIGH_READ_NOUNS = ['businesses', 'facts', 'sites', 'numbers'];

    /**
     * Read an entity with high-read caching and read-replica routing (G4-12, G4-21).
     */
    public function read(string $table, int $id, int $businessId, bool $forcePrimary = false): ?array
    {
        $cacheKey = "entity:{$table}:{$businessId}:{$id}";

        if (in_array($table, self::HIGH_READ_NOUNS, true) && ! $forcePrimary) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return (array) $cached;
            }
        }

        $connection = $forcePrimary ? 'pgsql' : $this->resolveReadConnection();
        $row = DB::connection($connection)
            ->table($table)
            ->where($table === 'businesses' ? 'id' : 'business_id', $businessId)
            ->where('id', $id)
            ->first();

        if ($row === null) {
            return null;
        }

        $data = (array) $row;

        if (in_array($table, self::HIGH_READ_NOUNS, true)) {
            Cache::put($cacheKey, $data, 3600);
        }

        return $data;
    }

    /**
     * Write/update an entity with inline cache invalidation and temporal history (G4-12, G17-28, G4-37).
     */
    public function write(string $table, int $id, int $businessId, array $attributes, ?string $actor = 'system'): array
    {
        return DB::transaction(function () use ($table, $id, $businessId, $attributes, $actor) {
            $existing = DB::table($table)
                ->where($table === 'businesses' ? 'id' : 'business_id', $businessId)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                throw new InvalidArgumentException("Entity {$table}:{$id} not found in business {$businessId}");
            }

            $previousState = (array) $existing;
            $deltas = [];

            foreach ($attributes as $k => $v) {
                if (! array_key_exists($k, $previousState) || $previousState[$k] !== $v) {
                    $deltas[$k] = [
                        'old' => $previousState[$k] ?? null,
                        'new' => $v,
                    ];
                }
            }

            $currentVersion = (int) ($previousState['version'] ?? 1);
            $newVersion = $currentVersion + 1;
            $commitId = (string) Str::uuid();

            // Record initial v1 snapshot if not present
            $hasV1 = EntityHistoryRecord::where('business_id', $businessId)
                ->where('entity_type', $table)
                ->where('entity_id', $id)
                ->exists();

            if (! $hasV1 && $existing) {
                EntityHistoryRecord::create([
                    'business_id' => $businessId,
                    'entity_type' => $table,
                    'entity_id' => $id,
                    'version' => $currentVersion,
                    'field_deltas' => [],
                    'snapshot' => $previousState,
                    'reversal_action' => 'entity.restore',
                    'created_by' => $actor,
                    'commit_id' => (string) Str::uuid(),
                ]);
            }

            if (Schema::hasColumn($table, 'version')) {
                $attributes['version'] = $newVersion;
            }

            if ($table === 'facts' && array_key_exists('value', $attributes)) {
                $attributes['commit_id'] = $commitId;
            }

            if (Schema::hasColumn($table, 'updated_at')) {
                $attributes['updated_at'] = now();
            }

            DB::table($table)
                ->where($table === 'businesses' ? 'id' : 'business_id', $businessId)
                ->where('id', $id)
                ->update($attributes);

            // Record field-level history (G17-28, G4-46)
            $history = EntityHistoryRecord::create([
                'business_id' => $businessId,
                'entity_type' => $table,
                'entity_id' => $id,
                'version' => $newVersion,
                'field_deltas' => $deltas,
                'snapshot' => array_merge($previousState, $attributes),
                'reversal_action' => 'entity.restore',
                'created_by' => $actor,
                'commit_id' => $commitId,
            ]);

            // Invalidate high-read cache inline (G4-12)
            $cacheKey = "entity:{$table}:{$businessId}:{$id}";
            Cache::forget($cacheKey);

            // TEST ANCHOR: Price change to a Fact invalidates dependents in the same transaction
            if ($table === 'facts' && isset($attributes['value'])) {
                Event::dispatch(new FactInvalidated(
                    businessId: $businessId,
                    factId: $id,
                    key: (string) ($previousState['key'] ?? $attributes['key'] ?? 'unknown'),
                    commitId: $commitId
                ));
            }

            return [
                'entity' => (array) DB::table($table)->where('id', $id)->first(),
                'version' => $newVersion,
                'history_id' => $history->id,
                'commit_id' => $commitId,
            ];
        });
    }

    /**
     * Restore a previous version with a compensable reversal record (G4-42, G11-14, G17-28).
     */
    public function restore(string $table, int $id, int $targetVersion, int $businessId, ?string $actor = 'system'): array
    {
        return DB::transaction(function () use ($table, $id, $targetVersion, $businessId, $actor) {
            $history = EntityHistoryRecord::where('business_id', $businessId)
                ->where('entity_type', $table)
                ->where('entity_id', $id)
                ->where('version', $targetVersion)
                ->first();

            if ($history === null || $history->snapshot === null) {
                throw new InvalidArgumentException("Version {$targetVersion} not found for {$table}:{$id}");
            }

            $snapshot = $history->snapshot;
            unset($snapshot['id'], $snapshot['created_at']);

            return $this->write($table, $id, $businessId, $snapshot, "restore:v{$targetVersion}:{$actor}");
        });
    }

    /**
     * Compare versions side-by-side (G4-46).
     */
    public function compareVersions(string $table, int $id, int $versionA, int $versionB, int $businessId): array
    {
        $recA = EntityHistoryRecord::where('business_id', $businessId)
            ->where('entity_type', $table)
            ->where('entity_id', $id)
            ->where('version', $versionA)
            ->first();

        $recB = EntityHistoryRecord::where('business_id', $businessId)
            ->where('entity_type', $table)
            ->where('entity_id', $id)
            ->where('version', $versionB)
            ->first();

        return [
            'version_a' => $recA?->snapshot,
            'version_b' => $recB?->snapshot,
            'deltas' => $recB?->field_deltas,
        ];
    }

    private function resolveReadConnection(): string
    {
        return config('database.connections.pgsql_read') ? 'pgsql_read' : 'pgsql';
    }
}
