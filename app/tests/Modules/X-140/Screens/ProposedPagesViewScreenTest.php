<?php

namespace Tests\Modules\X140\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ProposedPagesViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-140.proposed-pages'))->assertOk();

        Livewire::test(\App\Modules\X140\Ui\ProposedPagesView::class)->assertOk();
    }
}
