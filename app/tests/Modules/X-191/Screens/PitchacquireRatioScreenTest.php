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

    public function test_can_prospect_and_pitch(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PitchacquireRatio::class)
            ->set('domain', 'testdomain.com')
            ->set('targetUrl', 'https://testdomain.com/contact')
            ->set('isPbn', false)
            ->set('daScore', 40)
            ->call('prospect')
            ->assertSet('success', function ($value) {
                return str_contains($value, 'Recorded target') && str_contains($value, 'nothing downstream is wired to it yet.');
            });

        $this->assertDatabaseHas((new LinkTarget)->getTable(), [
            'business_id' => $biz->id,
            'domain' => 'testdomain.com',
            'is_pbn' => false,
            'domain_authority' => 40,
        ]);

        $target = LinkTarget::where('domain', 'testdomain.com')->firstOrFail();

        Livewire::test(PitchacquireRatio::class)
            ->set('targetId', (string) $target->id)
            ->set('pitchBody', 'My pitch body')
            ->set('pageSpecificFact', 'I noticed your article.')
            ->call('pitch')
            ->assertSet('success', function ($value) use ($target) {
                return str_contains($value, 'Recorded pitch for target '.$target->id) && str_contains($value, 'nothing downstream is wired to it yet (no email is actually sent).');
            });

        $this->assertDatabaseHas((new LinkPitch)->getTable(), [
            'business_id' => $biz->id,
            'target_id' => $target->id,
            'pitch_body' => 'My pitch body',
            'page_specific_fact' => 'I noticed your article.',
            'is_sent' => true,
        ]);

        $this->get(route('x-191.pitchacquire-ratio'))
            ->assertOk()
            ->assertSee('1 pitches sent');
    }

    public function test_refuses_empty_pitch_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PitchacquireRatio::class)
            ->call('pitch')
            ->assertSet('error', 'Please fill out target ID and pitch body.');

        $this->assertDatabaseMissing((new LinkPitch)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }

    public function test_refuses_pbn_target(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PitchacquireRatio::class)
            ->set('domain', 'toxic-pbn.com')
            ->set('targetUrl', 'https://toxic-pbn.com/')
            ->set('isPbn', true)
            ->set('daScore', 10)
            ->call('prospect')
            ->assertSet('error', null);

        $target = LinkTarget::where('domain', 'toxic-pbn.com')->firstOrFail();

        Livewire::test(PitchacquireRatio::class)
            ->set('targetId', (string) $target->id)
            ->set('pitchBody', 'Pitching a PBN')
            ->set('pageSpecificFact', 'fact')
            ->call('pitch')
            ->assertSet('error', 'Pitch rejected: target flagged as PBN/toxic network (TEST ANCHOR)');

        $this->assertDatabaseMissing((new LinkPitch)->getTable(), [
            'business_id' => $biz->id,
            'pitch_body' => 'Pitching a PBN',
        ]);
    }

    public function test_refuses_missing_page_specific_fact(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $target = LinkTarget::create([
            'business_id' => $biz->id,
            'domain' => 'good.com',
            'target_url' => 'https://good.com/',
        ]);

        Livewire::test(PitchacquireRatio::class)
            ->set('targetId', (string) $target->id)
            ->set('pitchBody', 'Pitch with no fact')
            ->call('pitch')
            ->assertSet('error', 'Pitch rejected: pitch template must contain a page-specific verified fact (TEST ANCHOR & G11-34)');

        $this->assertDatabaseMissing((new LinkPitch)->getTable(), [
            'business_id' => $biz->id,
            'pitch_body' => 'Pitch with no fact',
        ]);
    }

    public function test_refuses_unknown_target(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PitchacquireRatio::class)
            ->set('targetId', '999999')
            ->set('pitchBody', 'Pitching nowhere')
            ->call('pitch')
            ->assertSet('error', 'Unknown target.');

        $this->assertDatabaseMissing((new LinkPitch)->getTable(), [
            'business_id' => $biz->id,
            'pitch_body' => 'Pitching nowhere',
        ]);
    }
}
