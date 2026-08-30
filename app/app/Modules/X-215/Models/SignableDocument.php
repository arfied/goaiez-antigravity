<?php

declare(strict_types=1);

namespace App\Modules\X215\Models;

use Illuminate\Database\Eloquent\Model;

class SignableDocument extends Model
{
    protected $table = 'signable_documents';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
    ];
}
