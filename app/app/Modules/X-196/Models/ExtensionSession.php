<?php

declare(strict_types=1);

namespace App\Modules\X196\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionSession extends Model
{
    public const ACTIONS_PER_MINUTE_CAP = 30; // TEST ANCHOR: constant in bundle asserted by test

    protected $table = 'extension_sessions';

    protected $guarded = [];

    protected $attributes = [
        'is_authenticated_scrape' => false,
        'is_active' => true,
        'is_aborted' => false,
        'actions_count' => 0,
    ];

    protected $casts = [
        'is_authenticated_scrape' => 'boolean',
        'is_active' => 'boolean',
        'is_aborted' => 'boolean',
        'actions_count' => 'integer',
    ];

    public function injections()
    {
        return $this->hasMany(ExtensionInjection::class, 'session_id');
    }
}
