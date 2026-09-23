<?php

declare(strict_types=1);

namespace Tests\Modules\X160\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X160\Models\Document;
use App\Modules\X160\Ui\ReviewScreen;
use App\Modules\X160\Ui\UploadDrop;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_can_confirm_a_document(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(UploadDrop::class)
            ->set('title', 'Distinctive Spec 4471')
            ->set('content', 'Some content...')
            ->call('createDocument');

        $doc = Document::where('business_id', $biz->id)->firstOrFail();
        $id = $doc->id;

        Livewire::test(ReviewScreen::class)
            ->call('confirmDocument', $id)
            ->assertSet('confirmSuccess', 'Distinctive Spec 4471 is confirmed and has left the review list. Nothing reads confirmed documents yet.');

        $this->assertDatabaseHas((new Document)->getTable(), [
            'id' => $id,
            'status' => 'confirmed',
        ]);
    }

    public function test_a_confirmed_document_leaves_the_review_list(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(UploadDrop::class)
            ->set('title', 'Distinctive Spec 4471')
            ->set('content', 'Some content...')
            ->call('createDocument');

        $doc = Document::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(ReviewScreen::class)->call('confirmDocument', $doc->id);

        Tenancy::forget();

        $this->get(route('x-160.review-screen'))
            ->assertOk()
            ->assertSee('Nothing is waiting to be read.')
            ->assertDontSee('Distinctive Spec 4471');
    }

    public function test_confirming_another_tenants_document_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        Livewire::test(UploadDrop::class)
            ->set('title', 'Document B')
            ->set('content', 'Content B')
            ->call('createDocument');

        $docB = Document::where('business_id', $bizB->id)->firstOrFail();

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ReviewScreen::class)->call('confirmDocument', $docB->id);
    }
}
