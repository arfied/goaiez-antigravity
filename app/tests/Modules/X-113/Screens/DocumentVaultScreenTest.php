<?php

namespace Tests\Modules\X113\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DocumentVaultScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-113.document-vault'))->assertOk();

        Livewire::test(\App\Modules\X113\Ui\DocumentVault::class)->assertOk();
    }
}
