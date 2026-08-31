<?php

declare(strict_types=1);

namespace App\Modules\X116\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = 'templates';

    protected $guarded = [];

    protected $casts = [
        'design_tokens' => 'array',
        'conversion_rate' => 'float',
    ];

    public function blocks()
    {
        return $this->hasMany(TemplateBlock::class, 'template_id');
    }
}
