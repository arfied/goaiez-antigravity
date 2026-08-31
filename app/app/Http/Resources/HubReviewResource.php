<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HubReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id ?? 0,
            'author_name' => $this->resource->author_name ?? '',
            'rating' => $this->resource->rating ?? 5,
            'text' => $this->resource->text ?? '',
            'review_date' => $this->resource->review_date ?? null,
        ];
    }
}
