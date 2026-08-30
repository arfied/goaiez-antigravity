<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';

    protected $guarded = [];
}
