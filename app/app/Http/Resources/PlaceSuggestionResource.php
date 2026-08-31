<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return is_array($this->resource) ? $this->resource : [
            'place_id' => $this->resource->place_id ?? $this->resource->id ?? '',
            'description' => $this->resource->description ?? $this->resource->name ?? '',
            'main_text' => $this->resource->main_text ?? '',
            'secondary_text' => $this->resource->secondary_text ?? '',
        ];
    }
}
