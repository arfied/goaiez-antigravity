<?php

declare(strict_types=1);

namespace Tests\Modules\X160\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X160\Models\Document;
use App\Modules\X160\Ui\UploadDrop;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class UploadDropScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-160.upload-drop'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No documents yet.');

        Tenancy::setUser($owner->id);
        Document::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive price list 4641',
            'sha256_hash' => 'd4641'.str_repeat('a', 59),
            'status' => 'ingested',
            'mime_type' => 'application/pdf',
        ]);
        Tenancy::forget();

        $this->get(route('x-160.upload-drop'))
            ->assertOk()
            ->assertSee('Distinctive price list 4641')
            ->assertDontSee('No documents yet.');

        Livewire::test(UploadDrop::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-160.upload-drop.admin'))->assertOk();

        Livewire::test(UploadDrop::class)->assertOk();
    }

    public function test_control_writes_and_clears_empty_states(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $table = (new Document)->getTable();

        Livewire::test(UploadDrop::class)
            ->set('title', 'Test Document 123')
            ->set('content', 'This is the body of the test doc.')
            ->call('createDocument')
            ->assertSet('title', '')
            ->assertSet('content', '')
            ->assertSee('Recorded document ');

        $this->assertDatabaseHas($table, [
            'business_id' => $biz->id,
            'title' => 'Test Document 123',
            'status' => 'ingested',
        ]);

        $this->get(route('x-160.upload-drop'))
            ->assertSee('Test Document 123')
            ->assertDontSee('No documents yet.');

        $this->get(route('x-160.review-screen'))
            ->assertDontSee('Nothing is waiting to be read.');
    }

    public function test_control_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $table = (new Document)->getTable();

        Livewire::test(UploadDrop::class)
            ->set('title', '')
            ->set('content', 'Some content')
            ->call('createDocument')
            ->assertSet('error', 'Title and content are required.');
        
        $this->assertDatabaseMissing($table, [
            'title' => '',
        ]);

        Livewire::test(UploadDrop::class)
            ->set('title', 'Some title')
            ->set('content', '')
            ->call('createDocument')
            ->assertSet('error', 'Title and content are required.');
            
        $this->assertDatabaseMissing($table, [
            'title' => 'Some title',
        ]);
    }
}