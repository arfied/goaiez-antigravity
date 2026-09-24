<?php

declare(strict_types=1);

namespace App\Modules\X206\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class PlacesKeyValidateAction
{
    /**
     * @return array{valid: bool, status: int}
     */
    public function handle(string $candidateKey): array
    {
        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $candidateKey,
                'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId',
            ])
                ->timeout(8)
                ->acceptJson()
                ->post('https://places.googleapis.com/v1/places:autocomplete', ['input' => 'main street']);

            return [
                'valid' => $response->successful(),
                'status' => $response->status(),
            ];
        } catch (ConnectionException) {
            return ['valid' => false, 'status' => 0];
        }
    }
}
