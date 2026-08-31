<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\CrmTimelineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Every event against a customer, in one stream (DATA-MODEL §5.5).
 */
final class CrmTimeline extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CrmTimelineFactory> */
    use HasFactory;

    /**
     * Singular by spec — 'crm_timeline', not the plural Eloquent would infer.
     */
    protected $table = 'crm_timeline';

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
