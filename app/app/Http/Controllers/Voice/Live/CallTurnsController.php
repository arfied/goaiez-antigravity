<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice\Live;

use App\Enums\CallAnsweredBy;
use App\Models\Call;
use App\Modules\CAgent\Actions\AgentVoiceTurnRecordAction;
use App\Services\Voice\Live\LiveCallTokens;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The voice worker sends what was said on a call, a batch of turns at a time (AI receptionist plan, wave 3a).
 *
 * The tenant and the call come from the call token in the path and from nothing in the body. A token that opens to nothing,
 * or to a call this tenant's receptionist did not answer, is a 404 — one answer for every failure.
 */
final class CallTurnsController
{
    private const int MAX_TURNS = 50;

    private const int MAX_TEXT = 4000;

    private const int MAX_METRICS = 20;

    public function __invoke(Request $request, string $callToken, LiveCallTokens $tokens, AgentVoiceTurnRecordAction $record): JsonResponse
    {
        $claim = $tokens->open($callToken);

        if ($claim === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $turns = $this->turns($request->json('turns'));

        if ($turns === null) {
            return response()->json(['status' => 'invalid', 'reason' => 'turns must be 1 to 50 objects with turn, caller and agent'], 422);
        }

        return Tenancy::actingAs($claim->businessId, function () use ($claim, $turns, $record): JsonResponse {
            $call = Call::query()
                ->whereKey($claim->callId)
                ->where('answered_by', CallAnsweredBy::Agent->value)
                ->first();

            if (! $call instanceof Call) {
                return response()->json(['status' => 'not_found'], 404);
            }

            return response()->json(['status' => 'recorded', 'written' => $record->record((int) $call->getKey(), $turns)]);
        });
    }

    /**
     * @return list<array{turn: int, caller: string, agent: string, metrics: array<string, int|float>}>|null
     */
    private function turns(mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === [] || count($raw) > self::MAX_TURNS || ! array_is_list($raw)) {
            return null;
        }

        $turns = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                return null;
            }

            $turn = $item['turn'] ?? null;
            $caller = $item['caller'] ?? null;
            $agent = $item['agent'] ?? null;
            $metrics = $item['metrics'] ?? [];

            if (! is_int($turn) || $turn < 1 || $turn > 100000
                || ! is_string($caller) || mb_strlen($caller) > self::MAX_TEXT
                || ! is_string($agent) || mb_strlen($agent) > self::MAX_TEXT
                || ! is_array($metrics) || count($metrics) > self::MAX_METRICS
            ) {
                return null;
            }

            $clean = [];

            foreach ($metrics as $key => $value) {
                if (! is_string($key) || preg_match('/^[a-z0-9_]{1,40}$/', $key) !== 1 || (! is_int($value) && ! is_float($value))) {
                    return null;
                }

                $clean[$key] = $value;
            }

            $turns[] = ['turn' => $turn, 'caller' => $caller, 'agent' => $agent, 'metrics' => $clean];
        }

        return $turns;
    }
}
