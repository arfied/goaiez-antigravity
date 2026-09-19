<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

use App\Modules\CAgent\Models\AgentInstruction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AgentTeachAction
{
    /**
     * In a single transaction: updates instruction & writes Fact (TEST ANCHOR).
     */
    public function handle(int $businessId, string $key, string $value): array
    {
        return DB::transaction(function () use ($businessId, $key, $value) {
            $instruction = AgentInstruction::updateOrCreate(
                ['business_id' => $businessId, 'instruction_key' => $key],
                ['instruction_text' => $value, 'is_active' => true]
            );

            // Update or insert Fact in facts table
            $existingFact = DB::table('facts')
                ->where('business_id', $businessId)
                ->where('key', $key)
                ->first();

            if ($existingFact) {
                DB::table('facts')
                    ->where('id', $existingFact->id)
                    ->update([
                        'value' => $value,
                        'is_valid' => true,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('facts')->insert([
                    'business_id' => $businessId,
                    'key' => $key,
                    'value' => $value,
                    'version' => 1,
                    'is_valid' => true,
                    'commit_id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'instruction_id' => $instruction->id,
                'key' => $key,
                'value' => $value,
            ];
        });
    }
}
