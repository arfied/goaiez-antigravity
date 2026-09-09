<?php

declare(strict_types=1);

namespace App\Modules\X102\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * READER: X-102's CustomerfacingWidget will read this table to render carousels.
 * This provides the store required by G16-21 (readers unbuilt).
 */
class ChatTurn extends Model
{
    protected $table = 'chat_turns';

    protected $guarded = [];
}
