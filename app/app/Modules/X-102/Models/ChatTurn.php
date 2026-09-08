<?php

declare(strict_types=1);

namespace App\Modules\X102\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * READER: Track 1's C-Agent (via AgentAnswerAction) will read this table to feed
 * the LLM context, and X-102's CustomerfacingWidget will read it to render carousels.
 * This satisfies G5-31 and G16-21.
 */
class ChatTurn extends Model
{
    protected $table = 'chat_turns';

    protected $guarded = [];
}
