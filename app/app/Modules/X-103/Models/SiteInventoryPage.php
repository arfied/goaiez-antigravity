<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SiteInventoryPage extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'site_inventory_pages';

    protected $guarded = [];

    protected $casts = [
        'headings' => 'array',
        'image_urls' => 'array',
        'image_alts' => 'array',
        'phones' => 'array',
        'emails' => 'array',
        'links_out' => 'array',
        'brand' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function images()
    {
        return $this->hasMany(SiteInventoryImage::class, 'page_id');
    }
}
