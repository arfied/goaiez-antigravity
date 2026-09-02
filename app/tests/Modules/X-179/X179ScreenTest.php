<?php

declare(strict_types=1);

namespace Tests\Modules\X179;

use App\Support\Tenancy;
use Tests\TestCase;

class X179ScreenTest extends TestCase
{
    public function test_prospecttenantfacing_top3_preview_screen_guest_redirect(): void
    {
        $response = $this->get('/prospects/top3-preview/123');
        $response->assertRedirect('/login');
    }

    public function test_prospecttenantfacing_top3_preview_screen_authed_ok(): void
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set((int) $biz->id);
        $user = $biz->owner;

        $response = $this->actingAs($user)->get('/prospects/top3-preview/123');
        $response->assertOk();
    }

    public function test_match_scores_screen_guest_redirect(): void
    {
        $response = $this->get('/prospects/match-scores/123');
        $response->assertRedirect('/login');
    }

    public function test_match_scores_screen_authed_ok(): void
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set((int) $biz->id);
        $user = $biz->owner;

        $response = $this->actingAs($user)->get('/prospects/match-scores/123');
        $response->assertOk();
    }
}
