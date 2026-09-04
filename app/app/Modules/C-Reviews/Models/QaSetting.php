<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class QaSetting extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'qa_settings';

    protected $guarded = [];

    protected $casts = [
        'min_public_stars' => 'integer',
        'auto_reply_enabled' => 'boolean',
        'incentive_lint_active' => 'boolean',
    ];
}
