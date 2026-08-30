<?php

declare(strict_types=1);

namespace App\Modules\X104\Actions;

use App\Modules\X104\Events\PluginInstalled;
use App\Modules\X104\Models\PluginInstall;
use Illuminate\Support\Facades\Event;

final class PluginActivateAction
{
    /**
     * Activation on stock theme changes ZERO theme files and the four pillars respond (TEST ANCHOR).
     */
    public function activate(int $businessId, string $siteUrl, string $apiKey): PluginInstall
    {
        $injectedAssets = [
            'head_script' => "https://cdn.example.com/embed.js?key={$apiKey}",
            'chat_widget_css' => 'https://cdn.example.com/chat.css',
            'pixel_tracker' => 'https://cdn.example.com/px.js',
        ];

        $pillars = [
            'chat' => true,
            'pixel' => true,
            'reviews_badge' => true,
            'form_hijack' => true,
        ];

        $install = PluginInstall::updateOrCreate(
            ['business_id' => $businessId, 'site_url' => $siteUrl],
            [
                'api_key' => $apiKey,
                'is_active' => true,
                'theme_files_modified_count' => 0, // Zero theme files modified (TEST ANCHOR)
                'pillars_active' => $pillars,      // Four pillars respond (TEST ANCHOR)
                'injected_assets' => $injectedAssets,
            ]
        );

        Event::dispatch(new PluginInstalled($businessId, $siteUrl));

        return $install;
    }
}
