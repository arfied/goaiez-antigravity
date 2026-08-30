<?php

declare(strict_types=1);

namespace App\Modules\X196\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionInjection extends Model
{
    protected $table = 'extension_injections';

    protected $guarded = [];

    protected $casts = [
        'prospect_payload' => 'array',
    ];
}
