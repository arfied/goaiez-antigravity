<?php

declare(strict_types=1);

namespace Tests\Modules\X160\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X160\Models\Document;
use App\Modules\X160\Ui\ReviewScreen;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewScreenScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-160.review-screen'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing is waiting to be read.');

        Tenancy::setUser($owner->id);
        Document::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive price list 4641',
            'sha256_hash' => 'd4641'.str_repeat('a', 59),
            'status' => 'ingested',
            'mime_type' => 'application/pdf',
        ]);
        Tenancy::forget();

        $this->get(route('x-160.review-screen'))
            ->assertOk()
            ->assertSee('Distinctive price list 4641')
            ->assertDontSee('Nothing is waiting to be read.');

        Livewire::test(ReviewScreen::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-160.review-screen.admin'))->assertOk();

        Livewire::test(ReviewScreen::class)->assertOk();
    }
}
