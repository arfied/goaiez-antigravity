<?php

declare(strict_types=1);

namespace Tests\Modules\X201\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X201\Ui\DisputeCard;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DisputeCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-201.dispute-card'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No disputes.');

        Tenancy::setUser($owner->id);
        $dispute = Dispute::create([
            'business_id' => $biz->id,
            'invoice_id' => 4620,
            'chargeback_amount_cents' => 54321,
            'reason' => 'Distinctive reason 4620',
            'status' => 'compiled',
        ]);
        DisputeEvidence::create([
            'business_id' => $biz->id,
            'dispute_id' => $dispute->id,
            'evidence_type' => 'invoice',
            'file_url_or_content' => 'Distinctive evidence 4620',
        ]);
        Tenancy::forget();

        $this->get(route('x-201.dispute-card'))
            ->assertOk()
            ->assertSee('Invoice #4620')
            ->assertSee('543.21')
            ->assertSee('1 evidence items')
            ->assertSee('Distinctive evidence 4620')
            ->assertSee('Approve and seal')
            ->assertDontSee('No disputes.');

        Livewire::test(DisputeCard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-201.dispute-card.admin'))->assertOk();

        Livewire::test(DisputeCard::class)->assertOk();
    }
}
