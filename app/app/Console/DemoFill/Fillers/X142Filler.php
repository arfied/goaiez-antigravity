<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X142\Models\McpToken;
use App\Modules\X142\Models\WebhookSubscription;

class X142Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-142';
    }

    public function fill(Business $business): int
    {
        if (McpToken::where('business_id', $business->id)->where('token_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        McpToken::create([
            'business_id' => $business->id,
            'token_name' => self::MARKER.'Claude Desktop',
            'role_scope' => 'owner',
            'permissions' => ['job.create', 'job.read'],
            'token_hash' => hash('sha256', 'demo-claude-desktop'),
            'is_revoked' => false,
        ]);

        McpToken::create([
            'business_id' => $business->id,
            'token_name' => self::MARKER.'Zapier bridge',
            'role_scope' => 'staff',
            'permissions' => ['job.read'],
            'token_hash' => hash('sha256', 'demo-zapier-bridge'),
            'is_revoked' => false,
        ]);

        WebhookSubscription::create([
            'business_id' => $business->id,
            'target_url' => 'https://hooks.example/demo/jobs',
            'event_filter' => self::MARKER.'job.completed',
            'secret' => 'sec_demo_jobs',
            'is_active' => true,
        ]);

        WebhookSubscription::create([
            'business_id' => $business->id,
            'target_url' => 'https://hooks.example/demo/reviews',
            'event_filter' => self::MARKER.'review.received',
            'secret' => 'sec_demo_reviews',
            'is_active' => false,
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $tokensCount = McpToken::where('business_id', $business->id)
            ->where('token_name', 'like', self::MARKER.'%')
            ->delete();

        $subscriptionsCount = WebhookSubscription::where('business_id', $business->id)
            ->where('event_filter', 'like', self::MARKER.'%')
            ->delete();

        return $tokensCount + $subscriptionsCount;
    }
}
