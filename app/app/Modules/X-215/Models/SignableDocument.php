<?php

declare(strict_types=1);

namespace App\Modules\X215\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SignableDocument extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'signable_documents';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
    ];
}
