<?php

declare(strict_types=1);

namespace App\Modules\X104\Models;

use Illuminate\Database\Eloquent\Model;

class PluginInstall extends Model
{
    protected $table = 'plugin_installs';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'theme_files_modified_count' => 'integer',
        'pillars_active' => 'array',
        'injected_assets' => 'array',
    ];
}
