<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebstudioTemplateProject extends Model
{
    protected $table = 'webstudio_template_projects';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }
}
