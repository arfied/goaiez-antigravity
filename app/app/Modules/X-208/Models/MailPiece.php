<?php

declare(strict_types=1);

namespace App\Modules\X208\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MailPiece extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'mail_pieces';

    protected $guarded = [];

    protected $casts = [
        'cost_cents' => 'integer',
    ];
}
