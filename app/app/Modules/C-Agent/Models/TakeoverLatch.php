<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Models;

use Illuminate\Database\Eloquent\Model;

class TakeoverLatch extends Model
{
    protected $table = 'c_agent_takeovers';
    protected $guarded = [];
}
