<?php

declare(strict_types=1);

namespace App\Modules\X208\Models;

use Illuminate\Database\Eloquent\Model;

class MailPiece extends Model
{
    protected $table = 'mail_pieces';

    protected $guarded = [];

    protected $casts = [
        'cost_cents' => 'integer',
    ];
}
