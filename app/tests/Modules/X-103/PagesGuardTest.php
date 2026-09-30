<?php

namespace Tests\Modules\X103;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\Pages;
use Livewire\Livewire;
use Tests\TestCase;

class PagesGuardTest extends TestCase
{
    public function test_a_manager_cannot_publish_or_build(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $biz = $this->provisionTenant(['owner_user_id' => $manager->id]);
        $this->actingAs($manager);

        // Copied from app/tests/Modules/X-103/X103Test.php:777
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline', 'subline' => 'Hero text', 'source' => 'crawl'],
                ['type' => 'about', 'text' => 'About text'],
            ],
        ]);

        Livewire::test(Pages::class)
            ->call('addPage')
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->call('runBuild')
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->call('publishAll')
            ->assertForbidden();

        $this->get(route('x-103.pages'))->assertOk();
    }

    public function test_an_owner_still_can(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // Copied from app/tests/Modules/X-103/X103Test.php:777
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline', 'subline' => 'Hero text', 'source' => 'crawl'],
                ['type' => 'about', 'text' => 'About text'],
            ],
        ]);

        Livewire::test(Pages::class)
            ->call('addPage')
            ->assertStatus(200);

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertStatus(200);

        Livewire::test(Pages::class)
            ->call('runBuild')
            ->assertStatus(200);

        Livewire::test(Pages::class)
            ->call('publishAll')
            ->assertStatus(200);
    }
}
