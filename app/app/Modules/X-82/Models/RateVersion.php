<?php

declare(strict_types=1);

namespace App\Modules\X82\Models;

use Illuminate\Database\Eloquent\Model;

class RateVersion extends Model
{
    protected $table = 'rate_versions';

    protected $guarded = [];

    protected $casts = [
        'version_number' => 'integer',
        'amount_cents' => 'integer',
        'effective_from' => 'datetime',
    ];

    public function rate()
    {
        return $this->belongsTo(Rate::class, 'rate_id');
    }
}
