<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notes about a nearby peer's website — title, description, headings.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $competitor_id
 * @property string $url
 * @property ?string $title
 * @property ?string $description
 * @property ?array $headings
 * @property string $status
 * @property ?string $refusal_reason
 * @property CarbonImmutable $fetched_at
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 */
class CompetitorSiteNote extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headings' => 'array',
            'fetched_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Competitor, $this>
     */
    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
