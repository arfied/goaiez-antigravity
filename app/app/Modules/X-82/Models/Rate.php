<?php

declare(strict_types=1);

namespace App\Modules\X82\Models;

use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    protected $table = 'rates';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'current_version' => 'integer',
        'is_active' => 'boolean',
    ];

    public function versions()
    {
        return $this->hasMany(RateVersion::class, 'rate_id');
    }
}
