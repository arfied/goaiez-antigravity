<?php

declare(strict_types=1);

namespace Tests\Modules\X189\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X189\Models\BrandedMedia;
use App\Modules\X189\Ui\PreviewPerDestination;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PreviewPerDestinationScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-189.preview-per-destination'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No branded media generated.');

        Tenancy::setUser($owner->id);
        BrandedMedia::create([
            'business_id' => $biz->id,
            'source_asset_url' => 'https://example.test/source_distinctive_4570.jpg',
            'license_source' => 'client_upload',
            'output_media_url' => 'https://example.test/branded_distinctive_4570.jpg',
            'overlay_layer' => ['logo' => 'top-left'],
            'destination' => 'instagram'
        ]);
        Tenancy::forget();

        $this->get(route('x-189.preview-per-destination'))
            ->assertOk()
            ->assertSee('[instagram]')
            ->assertSee('branded_distinctive_4570.jpg')
            ->assertDontSee('No branded media generated.');

        Livewire::test(PreviewPerDestination::class)->assertOk();
    }
}
