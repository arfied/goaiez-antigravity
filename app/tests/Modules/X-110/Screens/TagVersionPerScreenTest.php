<?php

declare(strict_types=1);

namespace Tests\Modules\X110\Screens;

use App\Enums\UserRole;
use App\Models\PixelBundleVersion;
use App\Models\User;
use App\Modules\X110\Ui\TagVersionPer;
use Livewire\Livewire;
use Tests\TestCase;

class TagVersionPerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-110.tag-version-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Tag Versions</h1>', false);

        Livewire::test(TagVersionPer::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-110.tag-version-per.admin'))->assertOk();

        Livewire::test(TagVersionPer::class)->assertOk();
    }

    public function test_published_versions_render_newest_first_with_the_active_one_marked(): void
    {
        PixelBundleVersion::factory()->create([
            'sha' => 'deadbeef7731',
            'published_at' => now()->subDays(2),
            'status' => 'active',
        ]);
        PixelBundleVersion::factory()->create([
            'sha' => 'cafebabe7732',
            'published_at' => now()->subDay(),
            'status' => 'rolled_back',
            'halt_reason' => 'Distinctive halt 7732',
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-110.tag-version-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertSeeInOrder(['cafebabe7732', 'deadbeef7731'])
            ->assertSee('serving now')
            ->assertSee('Distinctive halt 7732')
            ->assertDontSee('14 KB smart pixel');
    }

    public function test_no_published_bundle_is_an_honest_empty_state(): void
    {
        PixelBundleVersion::query()->delete();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-110.tag-version-per'))
            ->assertOk()
            ->assertSee('No pixel bundle has been published yet.');
    }
}
