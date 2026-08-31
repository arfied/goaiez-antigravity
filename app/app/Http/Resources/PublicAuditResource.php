<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token ?? '',
            'business_name' => $this->resource->business_name ?? '',
            'status' => $this->resource->status ?? '',
            'score' => $this->resource->score ?? null,
            'findings' => $this->resource->findings ?? [],
        ];
    }
}
