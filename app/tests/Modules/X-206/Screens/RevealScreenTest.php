<?php

declare(strict_types=1);

namespace Tests\Modules\X206\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X206\Models\Credential;
use App\Modules\X206\Ui\Reveal;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class RevealScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-206.reveal'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No credentials to reveal.');

        Tenancy::set((int) $biz->id);
        $cred = Credential::create([
            'business_id' => $biz->id,
            'service_name' => 'distinctive_service_4520',
            'encrypted_secret' => Crypt::encryptString('secret_distinctive_4520'),
            'key_hint' => 'hint4520',
        ]);
        Tenancy::forget();

        $this->get(route('x-206.reveal'))
            ->assertOk()
            ->assertSee('distinctive_service_4520')
            ->assertSee('(hint4520)')
            ->assertDontSee('secret_distinctive_4520')
            ->assertDontSee('No credentials to reveal.');

        Tenancy::set((int) $biz->id);
        Livewire::actingAs($owner)
            ->test(Reveal::class, ['businessId' => $biz->id])
            ->call('reveal', $cred->id)
            ->assertSee('secret_distinctive_4520')
            ->assertSee('Shown once');
        $this->assertDatabaseHas('credential_reveals', [
            'credential_id' => $cred->id,
            'business_id' => $biz->id,
            'status' => 'permitted',
        ]);
        Tenancy::forget();
    }

    public function test_reveal_refuses_a_credential_of_another_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $other = $this->provisionTenant();

        Tenancy::set((int) $other->id);
        $foreign = Credential::create([
            'business_id' => $other->id,
            'service_name' => 'foreign_service_4521',
            'encrypted_secret' => Crypt::encryptString('secret_foreign_4521'),
            'key_hint' => 'hint4521',
        ]);
        Tenancy::forget();

        Tenancy::set((int) $biz->id);
        Livewire::actingAs($owner)
            ->test(Reveal::class, ['businessId' => $biz->id])
            ->call('reveal', $foreign->id)
            ->assertSee('not yours to reveal')
            ->assertDontSee('secret_foreign_4521');

        $this->assertDatabaseHas('credential_reveals', [
            'business_id' => $biz->id,
            'status' => 'refused',
            'refusal_reason' => 'UNAUTHORIZED_CROSS_TENANT',
        ]);
        Tenancy::forget();
    }
}
