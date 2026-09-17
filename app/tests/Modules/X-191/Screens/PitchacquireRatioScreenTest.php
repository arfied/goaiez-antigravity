<?php

declare(strict_types=1);

namespace Tests\Modules\X191\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X191\Models\LinkPitch;
use App\Modules\X191\Models\LinkPlacement;
use App\Modules\X191\Models\LinkTarget;
use App\Modules\X191\Ui\PitchacquireRatio;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PitchacquireRatioScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-191.pitchacquire-ratio'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No outreach yet.');

        Tenancy::setUser($owner->id);

        $target = LinkTarget::create([
            'business_id' => $biz->id,
            'domain' => 'distinctive-4608.example',
            'target_url' => 'https://distinctive-4608.example/',
        ]);

        LinkPitch::create([
            'business_id' => $biz->id,
            'target_id' => $target->id,
            'pitch_body' => 'Distinctive pitch 4608 a',
            'sent_month' => '2026-09',
            'is_sent' => true,
        ]);

        LinkPitch::create([
            'business_id' => $biz->id,
            'target_id' => $target->id,
            'pitch_body' => 'Distinctive pitch 4608 b',
            'sent_month' => '2026-09',
            'is_sent' => true,
        ]);

        LinkPlacement::create([
            'business_id' => $biz->id,
            'pitch_id' => null,
            'placed_url' => 'https://distinctive-4608.example/blog',
            'anchor_text' => 'Distinctive anchor 4608',
        ]);

        Tenancy::forget();

        $this->get(route('x-191.pitchacquire-ratio'))
            ->assertOk()
            ->assertSee('2 pitches sent')
            ->assertSee('1 links earned')
            ->assertSee('50% acquired');

        Livewire::test(PitchacquireRatio::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-191.pitchacquire-ratio.admin'))->assertOk();

        Livewire::test(PitchacquireRatio::class)->assertOk();
    }
}
