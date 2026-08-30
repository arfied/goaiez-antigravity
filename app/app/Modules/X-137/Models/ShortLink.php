<?php

declare(strict_types=1);

namespace App\Modules\X137\Models;

use Illuminate\Database\Eloquent\Model;

class ShortLink extends Model
{
    protected $table = 'short_links';

    protected $guarded = [];
}
