<?php

declare(strict_types=1);

namespace Tests\Modules\X179;

use App\Models\User;
use App\Modules\X179\Models\TemplateMatch;
use App\Modules\X179\Ui\MatchScores;
use App\Modules\X179\Ui\ProspecttenantfacingTop3Preview;
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
        $user = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $biz->id);

        TemplateMatch::create([
            'business_id' => $biz->id,
            'prospect_id' => 123,
            'template_id' => 'tmpl-1',
            'path_type' => 'path-1',
            'rendered_preview' => 'preview',
        ]);

        $response = $this->actingAs($user)->get('/prospects/top3-preview/123');
        $response->assertOk();
        $response->assertSeeLivewire(ProspecttenantfacingTop3Preview::class);
    }

    public function test_match_scores_screen_guest_redirect(): void
    {
        $response = $this->get('/prospects/match-scores/123');
        $response->assertRedirect('/login');
    }

    public function test_match_scores_screen_authed_ok(): void
    {
        $user = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $biz->id);

        TemplateMatch::create([
            'business_id' => $biz->id,
            'prospect_id' => 123,
            'template_id' => 'tmpl-1',
            'path_type' => 'path-1',
            'rendered_preview' => 'preview',
        ]);

        $response = $this->actingAs($user)->get('/prospects/match-scores/123');
        $response->assertOk();
        $response->assertSeeLivewire(MatchScores::class);
    }
}
