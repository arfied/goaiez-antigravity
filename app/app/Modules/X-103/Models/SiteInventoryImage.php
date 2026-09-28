<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SiteInventoryImage extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'site_inventory_images';

    protected $guarded = [];

    protected $casts = [
        'bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function page()
    {
        return $this->belongsTo(SiteInventoryPage::class, 'page_id');
    }
}
