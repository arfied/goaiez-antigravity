<?php

declare(strict_types=1);

namespace App\Modules\X161\Models;

use Illuminate\Database\Eloquent\Model;

class DemoTenant extends Model
{
    protected $table = 'demo_tenants';

    protected $guarded = [];

    protected $casts = [
        'is_mock' => 'boolean',
        'is_converted' => 'boolean',
    ];

    public function sessions()
    {
        return $this->hasMany(DemoSession::class, 'demo_tenant_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(DemoLedger::class, 'demo_tenant_id');
    }
}
