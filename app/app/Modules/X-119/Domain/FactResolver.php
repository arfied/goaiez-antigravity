<?php

declare(strict_types=1);

namespace App\Modules\X119\Domain;

use App\Modules\X119\Events\FactCreated;
use App\Modules\X119\Events\FactInvalidated;
use App\Modules\X119\Events\GroundingMissing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class FactResolver
{
    /**
     * Resolve a structured fact. Prices resolve only through this structured query.
     */
    public function lookup(int $businessId, string $key, string $channel = 'customer'): array
    {
        $row = DB::table('facts')
            ->where('business_id', $businessId)
            ->where('key', $key)
            ->where('is_valid', true)
            ->first();

        if ($row === null) {
            Event::dispatch(new GroundingMissing(
                businessId: $businessId,
                queryKey: $key,
                reason: 'Fact not found or invalid'
            ));

            return [
                'status' => 'missing',
                'key' => $key,
                'value' => null,
            ];
        }

        // TEST ANCHOR: A Fact in SAMPLE state cannot be returned to a customer channel
        $isSample = str_contains((string) $row->value, 'SAMPLE') || str_starts_with((string) $row->key, 'sample.');
        if ($isSample && $channel === 'customer') {
            Event::dispatch(new GroundingMissing(
                businessId: $businessId,
                queryKey: $key,
                reason: 'A Fact in SAMPLE state cannot be returned to a customer channel'
            ));

            return [
                'status' => 'refused',
                'code' => 'SAMPLE_FACT_REFUSED',
                'key' => $key,
                'value' => null,
                'message' => 'Sample fact cannot be delivered to customer channel',
            ];
        }

        return [
            'status' => 'grounded',
            'fact_id' => $row->id,
            'key' => $row->key,
            'value' => $row->value,
            'version' => $row->version,
            'commit_id' => $row->commit_id,
        ];
    }

    /**
     * Confirm a fact or update its validity.
     */
    public function confirm(int $businessId, int $factId): array
    {
        DB::table('facts')
            ->where('business_id', $businessId)
            ->where('id', $factId)
            ->update([
                'is_valid' => true,
                'updated_at' => now(),
            ]);

        return ['status' => 'confirmed', 'fact_id' => $factId];
    }

    /**
     * Teach a volunteered detail and record its source (G13-38).
     */
    public function teach(int $businessId, string $key, string $value, string $source = 'volunteered'): array
    {
        return DB::transaction(function () use ($businessId, $key, $value, $source) {
            $commitId = (string) Str::uuid();

            // The newest teaching supersedes the old row: lookup() takes the first is_valid row with no ordering (wave 836).
            DB::table('facts')->where('business_id', $businessId)->where('key', $key)->where('is_valid', true)->update(['is_valid' => false]);

            $id = DB::table('facts')->insertGetId([
                'business_id' => $businessId,
                'key' => $key,
                'value' => $value,
                'version' => 1,
                'is_valid' => true,
                'commit_id' => $commitId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Event::dispatch(new FactCreated(
                businessId: $businessId,
                factId: $id,
                key: $key,
                value: $value,
                source: $source
            ));

            return [
                'fact_id' => $id,
                'key' => $key,
                'value' => $value,
                'source' => $source,
                'commit_id' => $commitId,
            ];
        });
    }

    /**
     * Invalidate facts for an unpublished page sharing one commit id (TEST ANCHOR).
     */
    public function invalidatePageFacts(int $businessId, string $pageKey): string
    {
        return DB::transaction(function () use ($businessId, $pageKey) {
            $commitId = (string) Str::uuid();

            $matching = DB::table('facts')
                ->where('business_id', $businessId)
                ->where('key', 'like', "{$pageKey}%")
                ->where('is_valid', true)
                ->get();

            foreach ($matching as $fact) {
                DB::table('facts')
                    ->where('id', $fact->id)
                    ->update([
                        'is_valid' => false,
                        'commit_id' => $commitId,
                        'updated_at' => now(),
                    ]);

                Event::dispatch(new FactInvalidated(
                    businessId: $businessId,
                    factId: (int) $fact->id,
                    key: (string) $fact->key,
                    commitId: $commitId
                ));
            }

            return $commitId;
        });
    }
}
