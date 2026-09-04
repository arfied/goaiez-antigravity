<?php

declare(strict_types=1);

namespace App\Modules\X201\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DisputeEvidence extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'dispute_evidence';

    protected $guarded = [];
}
