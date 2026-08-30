<?php

declare(strict_types=1);

namespace App\Modules\X122\Actions;

use App\Modules\X122\Events\ActionReversed;
use App\Modules\X122\Models\ActionInvocation;
use App\Modules\X122\Models\ActionManifest;
use App\Modules\X122\Models\ActionReversal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class ActionReverseAction
{
    public function handle(int $invocationId, int $businessId, ?string $actor = 'system'): array
    {
        return DB::transaction(function () use ($invocationId, $businessId, $actor) {
            $inv = ActionInvocation::where('business_id', $businessId)->findOrFail($invocationId);
            $manifest = ActionManifest::where('business_id', $businessId)
                ->where('action_name', $inv->action_name)
                ->first();

            if ($manifest === null || ! $manifest->is_reversible || $manifest->reversal_action === null) {
                throw new InvalidArgumentException("Action {$inv->action_name} is not reversible");
            }

            $reversal = ActionReversal::create([
                'business_id' => $businessId,
                'invocation_id' => $inv->id,
                'reversal_action_name' => $manifest->reversal_action,
                'parameters' => $inv->parameters,
                'reversed_by' => $actor ?? 'system',
                'reversed_at' => now(),
            ]);

            $inv->update(['status' => 'reversed']);

            Event::dispatch(new ActionReversed(
                businessId: $businessId,
                reversalId: $reversal->id,
                invocationId: $inv->id,
                actionName: $inv->action_name
            ));

            return [
                'status' => 'reversed',
                'reversal_id' => $reversal->id,
                'reversal_action' => $manifest->reversal_action,
            ];
        });
    }
}
