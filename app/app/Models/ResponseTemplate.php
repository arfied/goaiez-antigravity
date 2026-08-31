<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\BrandVoice;
use Database\Factories\ResponseTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A curated reply template (DATA-MODEL §5.3).
 */
final class ResponseTemplate extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ResponseTemplateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'brand_voice' => BrandVoice::class,
            'is_active' => 'boolean',
        ];
    }
}
