<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use Illuminate\Database\Eloquent\Model;

class QaSetting extends Model
{
    protected $table = 'qa_settings';

    protected $guarded = [];

    protected $casts = [
        'min_public_stars' => 'integer',
        'auto_reply_enabled' => 'boolean',
        'incentive_lint_active' => 'boolean',
    ];
}
