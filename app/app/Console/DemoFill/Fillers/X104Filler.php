<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X104\Models\PluginInstall;

class X104Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-104';
    }

    public function fill(Business $business): int
    {
        if (PluginInstall::where('business_id', $business->id)->where('site_url', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        PluginInstall::create([
            'business_id' => $business->id,
            'site_url' => self::MARKER.'https://active.example',
            'api_key' => self::MARKER.'key_active',
            'is_active' => true,
            'theme_files_modified_count' => 0,
            'pillars_active' => ['chat'],
            'injected_assets' => null,
        ]);

        PluginInstall::create([
            'business_id' => $business->id,
            'site_url' => self::MARKER.'https://stale.example',
            'api_key' => self::MARKER.'key_stale',
            'is_active' => false,
            'theme_files_modified_count' => 0,
            'pillars_active' => ['chat'],
            'injected_assets' => null,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return PluginInstall::where('business_id', $business->id)
            ->where('site_url', 'like', self::MARKER.'%')
            ->delete();
    }
}
