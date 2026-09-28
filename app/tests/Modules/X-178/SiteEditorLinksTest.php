<?php

namespace Tests\Modules\X178;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Tests\TestCase;

class SiteEditorLinksTest extends TestCase
{
    public function test_lists_pages_or_prompts_for_first()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($business->id);

        $response = $this->actingAs($owner)->get(route('x-178.site-editor-assistant'));
        $response->assertOk();
        $response->assertSee('Add your first page');

        $alpha = Page::create(['business_id' => $business->id, 'title' => 'Distinctive Alpha 3181', 'slug' => 'alpha']);
        Page::create(['business_id' => $business->id, 'title' => 'Distinctive Beta 3182', 'slug' => 'beta']);

        $response = $this->actingAs($owner)->get(route('x-178.site-editor-assistant'));
        $response->assertOk();
        $response->assertSee('Open Distinctive Alpha 3181 in the editor');
        $response->assertSee('?edit='.$alpha->id);
    }
}
