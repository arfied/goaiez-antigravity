<?php

declare(strict_types=1);

namespace App\Modules\X220\Models;

use App\Models\AiCall;
use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model
{
    protected $table = 'ai_prompts';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'frozen_at' => 'datetime',
    ];

    public function calls()
    {
        return $this->hasMany(AiCall::class, 'prompt_id');
    }
}
