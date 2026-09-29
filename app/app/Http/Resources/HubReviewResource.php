<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\X181\Models\QaTicket;
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
            'ticket' => isset($this->resource->customer_id) ? QaTicket::where('person_id', $this->resource->customer_id)->first() : null,
            'author' => $this->resource->reviewer_name ?? '',
            'comment' => $this->resource->comment ?? '',
            'posted_at' => $this->resource->review_create_time ?? null,
            'rating' => $this->resource->rating ?? 5,
        ];
    }
}
