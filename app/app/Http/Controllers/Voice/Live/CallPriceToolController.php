<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice\Live;

use App\Enums\CallAnsweredBy;
use App\Models\Business;
use App\Models\Call;
use App\Modules\CAgent\Actions\AgentVoiceRefusalRecordAction;
use App\Modules\X163\Actions\PriceQuoteAction;
use App\Services\Assistant\PriceBook;
use App\Services\Voice\Live\LiveCallTokens;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The AI receptionist's price tool (AI receptionist plan, wave 3b, 2026-10-05): the ONLY way a price reaches a phone call.
 *
 * The receptionist is never handed a price list. When a caller asks what something costs, the worker calls this with the
 * caller's question, and X-163's {@see PriceQuoteAction} — the same quote the web chat uses — answers from the business's
 * CONFIRMED prices only:
 *  - a match: the figure, how to say it, and the line the business says with every price ({@see PriceBook::disclaimer()});
 *  - otherwise a refusal (`NO_FACT` — nothing confirmed matches; `UNCONFIRMED` / `SAMPLE_STATE_REFUSED` — a figure exists but
 *    nobody has confirmed it), recorded against the call, and the words to say instead.
 *
 * The tenant and the call come from the call token in the path and from nothing in the body.
 */
final class CallPriceToolController
{
    public const string NO_PRICE_WORDS = "I don't have a confirmed price for that. The business will confirm it and get back to you.";

    private const int MAX_QUESTION = 500;

    public function __invoke(
        Request $request,
        string $callToken,
        LiveCallTokens $tokens,
        PriceQuoteAction $quotes,
        PriceBook $prices,
        AgentVoiceRefusalRecordAction $refusals,
    ): JsonResponse {
        $claim = $tokens->open($callToken);

        if ($claim === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $question = $request->json('question');

        if (! is_string($question) || trim($question) === '' || mb_strlen($question) > self::MAX_QUESTION) {
            return response()->json(['status' => 'invalid', 'reason' => 'question is required, at most 500 characters'], 422);
        }

        $question = trim($question);

        return Tenancy::actingAs($claim->businessId, function () use ($claim, $question, $quotes, $prices, $refusals): JsonResponse {
            $call = Call::query()
                ->whereKey($claim->callId)
                ->where('answered_by', CallAnsweredBy::Agent->value)
                ->first();

            if (! $call instanceof Call) {
                return response()->json(['status' => 'not_found'], 404);
            }

            $quote = $quotes->handle($claim->businessId, $question);

            if (isset($quote['amount']) && is_int($quote['amount']) && isset($quote['service_name']) && is_string($quote['service_name'])) {
                $currency = (string) (Business::query()->whereKey($claim->businessId)->value('currency') ?? 'USD');

                return response()->json([
                    'status' => 'price',
                    'service' => $quote['service_name'],
                    'amount_cents' => $quote['amount'],
                    'currency' => strtoupper($currency),
                    'spoken' => self::spoken($quote['amount'], $currency),
                    'disclaimer' => $prices->disclaimer(),
                ]);
            }

            $code = is_string($quote['refusal_code'] ?? null) ? $quote['refusal_code'] : 'NO_FACT';
            $reason = is_string($quote['reason'] ?? null) ? $quote['reason'] : 'No price quote available for the given intent';

            $refusals->record((int) $call->getKey(), $code, $reason, $question);

            return response()->json(['status' => 'refused', 'refusal_code' => $code, 'say' => self::NO_PRICE_WORDS]);
        });
    }

    /**
     * A figure as the receptionist says it — the SMS assistant's formatter (`AgentComposer::money()`), so a price reads the
     * same on a call as in a text.
     */
    private static function spoken(int $minorUnits, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => '',
        };

        $formatted = number_format($minorUnits / 100, 2, '.', '');

        return $symbol === '' ? $formatted.' '.strtoupper($currency) : $symbol.$formatted;
    }
}
