<?php

declare(strict_types=1);

namespace App\Modules\X102\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * READER: C-Agent (via AgentAnswerAction) will read this table to feed
 * the LLM context, and X-102's CustomerfacingWidget will read it to render carousels.
 * This provides the store required by G5-31 and G16-21 (readers unbuilt).
 */
class ChatTurn extends Model
{
    protected $table = 'chat_turns';

    protected $guarded = [];
}
