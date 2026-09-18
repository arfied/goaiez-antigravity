<?php

declare(strict_types=1);

namespace Tests\Modules\CSms\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CSms\Models\SmsComposition;
use App\Modules\CSms\Ui\ComposerSegmentWarning;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ComposerSegmentWarningScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-sms.composer-segment-warning'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No texts yet.');

        Tenancy::setUser($owner->id);
        SmsComposition::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125554618',
            'message_class' => 'marketing',
            'body' => 'Distinctive text 4618',
            'segments_count' => 2,
            'encoding' => 'gsm7',
            'status' => 'sent',
        ]);
        Tenancy::forget();

        $this->get(route('c-sms.composer-segment-warning'))
            ->assertOk()
            ->assertSee('+15125554618')
            ->assertSee('Distinctive text 4618')
            ->assertSee('2 segments · gsm7')
            ->assertSee('bills as 2 segments')
            ->assertDontSee('No texts yet.');

        Livewire::test(ComposerSegmentWarning::class)->assertOk();
    }
}
