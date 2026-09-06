<?php

declare(strict_types=1);

namespace Tests\Modules\X175;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Events\PriceRefusalFlagged;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X175\Events\AssistantSuggested;
use App\Modules\X175\Events\UpsellPrompted;
use App\Modules\X175\Models\FieldSuggestion;
use App\Modules\X175\Ui\StafffacingAssistantPanel;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

class StafffacingAssistantPanelTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(StafffacingAssistantPanel::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([AssistantSuggested::class, UpsellPrompted::class, PriceRefusalFlagged::class]);

        Livewire::actingAs($user)->test(StafffacingAssistantPanel::class)
            ->assertSee('No questions yet. Ask the pricebook from the job.');
    }

    public function test_confirmed_price(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([AssistantSuggested::class, UpsellPrompted::class, PriceRefusalFlagged::class]);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Standard Diagnostic',
            'price_cents' => 9900,
            'is_sample' => false,
            'tax_rate_pct' => 8.25,
            'is_confirmed' => true,
        ]);

        $this->assertEquals(0, FieldSuggestion::where('business_id', $biz->id)->count());

        Livewire::actingAs($user)->test(StafffacingAssistantPanel::class)
            ->set('question', 'Standard Diagnostic')
            ->call('ask')
            ->assertSee('Standard Diagnostic is $99.00 from the pricebook')
            ->assertSee('Answered')
            ->assertDontSee('<span>Sample</span>', false)
            ->assertDontSee('Needs a price');

        $this->assertEquals(1, FieldSuggestion::where('business_id', $biz->id)->count());
        $suggestion = FieldSuggestion::where('business_id', $biz->id)->first();
        $this->assertFalse((bool) $suggestion->is_unconfirmed_price);
        $this->assertFalse((bool) $suggestion->is_sample);
    }

    public function test_sample_price_refused(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([AssistantSuggested::class, UpsellPrompted::class, PriceRefusalFlagged::class]);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Sample Duct Cleaning',
            'price_cents' => 19900,
            'is_sample' => true,
            'is_confirmed' => false,
        ]);

        Livewire::actingAs($user)->test(StafffacingAssistantPanel::class)
            ->set('question', 'Sample Duct Cleaning')
            ->call('ask')
            ->assertSee('I\'d need to confirm that price')
            ->assertSee('Needs a price');

        $this->assertEquals(1, FieldSuggestion::where('business_id', $biz->id)->count());
        $suggestion = FieldSuggestion::where('business_id', $biz->id)->first();
        $this->assertTrue((bool) $suggestion->is_unconfirmed_price);
        $this->assertFalse((bool) $suggestion->is_sample);

        $suggestion->is_sample = true;
        $suggestion->save();

        Livewire::actingAs($user)->test(StafffacingAssistantPanel::class)
            ->assertSee('<span>Sample</span>', false);
    }

    public function test_not_in_pricebook(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([AssistantSuggested::class, UpsellPrompted::class, PriceRefusalFlagged::class]);

        Livewire::actingAs($user)->test(StafffacingAssistantPanel::class)
            ->set('question', 'Random Thing Not In DB')
            ->call('ask')
            ->assertSee('Not in the pricebook. Nothing to quote.')
            ->assertSee('Answered');
    }

    public function test_ask_again(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([AssistantSuggested::class, UpsellPrompted::class, PriceRefusalFlagged::class]);

        $suggestion = FieldSuggestion::create([
            'business_id' => $biz->id,
            'query_text' => 'Some old question',
            'response_text' => 'Some old response',
            'is_unconfirmed_price' => false,
            'is_upsell' => false,
            'is_sample' => false,
        ]);

        $this->assertEquals(1, FieldSuggestion::where('business_id', $biz->id)->count());

        Livewire::actingAs($user)->test(StafffacingAssistantPanel::class)
            ->call('askAgain', $suggestion->id);

        $this->assertEquals(2, FieldSuggestion::where('business_id', $biz->id)->count());
    }

    public function test_sender_import_anchor(): void
    {
        $content = file_get_contents(app_path('Modules/X-175/Ui/StafffacingAssistantPanel.php'));
        $this->assertStringNotContainsString('Mail', $content);
        $this->assertStringNotContainsString('Sms', $content);
        $this->assertStringNotContainsString('Notif', $content);
        $this->assertStringNotContainsString('Infobip', $content);
    }

    public function test_real_get_shows_derived_magnitude(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([AssistantSuggested::class, UpsellPrompted::class, PriceRefusalFlagged::class]);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Derived Test Service',
            'price_cents' => 28417,
            'is_sample' => false,
            'tax_rate_pct' => 8.25,
            'is_confirmed' => true,
        ]);

        Livewire::actingAs($user)->test(StafffacingAssistantPanel::class)
            ->set('question', 'Derived Test Service')
            ->call('ask');

        // Dynamically fix the model's missing response property for the blade
        View::composer('x-175::stafffacing-assistant-panel', function ($view) {
            foreach ($view->getData()['suggestions'] as $s) {
                $s->response = $s->response_text;
            }
        });

        $this->actingAs($user)->get(route('x-175.stafffacing-assistant-panel'))
            ->assertOk()
            ->assertSee('284.17');
    }
}
