<?php

declare(strict_types=1);

namespace Tests\Modules\X175\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X175\Models\FieldSuggestion;
use App\Modules\X175\Ui\StafffacingAssistantPanel;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class StafffacingAssistantPanelScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-175.stafffacing-assistant-panel'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No questions yet.');

        Tenancy::setUser($owner->id);
        FieldSuggestion::create([
            'business_id' => $biz->id,
            'query_text' => 'Distinctive question 4471',
            'response_text' => 'Distinctive answer 4471',
            'is_unconfirmed_price' => false,
            'is_upsell' => false,
            'is_sample' => false
        ]);
        Tenancy::forget();

        $this->get(route('x-175.stafffacing-assistant-panel'))
            ->assertOk()
            ->assertSee('Distinctive question 4471')
            ->assertSee('Distinctive answer 4471')
            ->assertSee('Answered')
            ->assertDontSee('No questions yet.');

        Livewire::test(StafffacingAssistantPanel::class)->assertOk();
    }
}
