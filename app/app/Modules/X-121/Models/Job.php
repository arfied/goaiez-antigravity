<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Job extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'work_orders';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'person_id' => 'integer',
        'price_cents' => 'integer',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
