<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return is_array($this->resource) ? $this->resource : [
            'place_id' => $this->resource->place_id ?? $this->resource->id ?? '',
            'name' => $this->resource->name ?? '',
            'address' => $this->resource->address ?? '',
        ];
    }
}
