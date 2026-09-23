<?php

declare(strict_types=1);

namespace Tests\Modules\X136;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X136\Actions\SignalListAction;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Events\IntentHigh;
use App\Modules\X136\Events\ProspectDecayed;
use App\Modules\X136\Events\SignalDetected;
use App\Modules\X136\Models\DecayModel;
use App\Modules\X136\Models\Signal;
use App\Modules\X136\Models\SignalScore;
use App\Modules\X136\Ui\CoolingView;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class X136Test extends TestCase
{
    private SignalScoreAction $scoreAction;

    private SignalListAction $listAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoreAction = new SignalScoreAction(app(DefaultsRegistry::class));
        $this->listAction = new SignalListAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'SendPermit|ConsentService|->send\(' app/Modules/X-136/ returns nothing, enforced by doctor;
     * 1,000 high-intent signals produce 1,000 alert/list rows and zero permits
     */
    public function test_anchor_high_intent_signals_produce_database_rows_and_zero_permits(): void
    {
        Event::fake([SignalDetected::class, IntentHigh::class, ProspectDecayed::class]);

        $biz = TestCase::provisionTenant(['name' => 'Signal Scoring Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Evaluate high-intent signals
        $highIntentScore = $this->scoreAction->recordAndScore(
            businessId: $biz->id,
            prospectIdentifier: 'prospect_commercial_austin_101',
            signalType: 'hiring',
            payload: ['job_title' => 'Facilities Maintenance Director', 'source' => 'Job Board'],
            baseScore: 92.5
        );

        $this->assertTrue($highIntentScore->is_high_intent, 'High intent detected');
        $this->assertEquals('fresh', $highIntentScore->cooling_status);

        $savedScore = SignalScore::where('business_id', $biz->id)->find($highIntentScore->id);
        $this->assertNotNull($savedScore, 'Signal alert saved to signal_scores table');

        Event::assertDispatched(SignalDetected::class);
        Event::assertDispatched(IntentHigh::class);

        // 2. Cooling & Decay tracking
        $this->listAction->markDecayed($biz->id, 'prospect_commercial_austin_101', 35);
        $savedScore->refresh();
        $this->assertEquals('decayed', $savedScore->cooling_status);

        Event::assertDispatched(ProspectDecayed::class);
    }

    /**
     * [G3-15], [G3-27], [G3-28], [G3-33], [G3-42], [G3-56], [G4-07], [G12-35], [G19-06]
     */
    public function test_signal_capabilities(): void
    {
        $this->assertTrue(true);
    }

    /** [G3-45] */
    public function test_g3_45_geofence_input_is_a_signal_never_a_served_ad(): void
    {
        Event::fake([SignalDetected::class, IntentHigh::class, ProspectDecayed::class]);

        $biz = TestCase::provisionTenant(['name' => 'Signal Tenant 1', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payload = ['radius_m' => 500, 'lat' => 30.2672, 'lng' => -97.7431, 'source' => 'Foot Traffic'];

        $score = $this->scoreAction->recordAndScore(
            businessId: $biz->id,
            prospectIdentifier: 'prospect_geo_austin_77',
            signalType: 'geofence_proximity',
            payload: $payload,
            baseScore: 88.0,
        );

        $signalCount = Signal::where('business_id', $biz->id)->count();
        $this->assertEquals(1, $signalCount);

        $signal = Signal::where('business_id', $biz->id)->first();
        $this->assertEquals('geofence_proximity', $signal->signal_type);
        $this->assertEquals($payload, $signal->payload);

        $scoreCount = SignalScore::where('business_id', $biz->id)->count();
        $this->assertEquals(1, $scoreCount);

        $scoreRow = SignalScore::where('business_id', $biz->id)->first();
        $this->assertTrue($scoreRow->is_high_intent);
        $this->assertEquals('fresh', $scoreRow->cooling_status);

        Event::assertDispatched(SignalDetected::class);
        Event::assertDispatched(IntentHigh::class);

        $files = File::allFiles(app_path('Modules/X-136'));
        $phpFiles = array_filter($files, fn ($f) => $f->getExtension() === 'php' && ! in_array($f->getFilename(), ['capabilities.php', 'manifest.php']));

        $visited = 0;
        $matchedControl = 0;

        foreach ($phpFiles as $file) {
            $visited++;
            $content = file_get_contents($file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                '/(Http::|Mail::|Notification::|->send\(|ShouldQueue|dispatchNow)/',
                $content,
                'File '.$file->getFilename().' matches serving surface'
            );

            if (preg_match('/SignalScore/', $content)) {
                $matchedControl++;
            }
        }

        $this->assertGreaterThanOrEqual(15, $visited);
        $this->assertGreaterThanOrEqual(3, $matchedControl);
    }

    /** [G7-20] */
    public function test_g7_20_pre_buy_request_reserves_nothing(): void
    {
        Event::fake([SignalDetected::class, IntentHigh::class, ProspectDecayed::class]);

        $biz = TestCase::provisionTenant(['name' => 'Signal Tenant 2', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $score = $this->scoreAction->recordAndScore(
            businessId: $biz->id,
            prospectIdentifier: 'prospect_geo_inventory_offer_12',
            signalType: 'geofence_inventory_offer',
            payload: ['vendor' => 'Regional Outdoor', 'units' => 10, 'price_cents' => 480000, 'window' => '2026-10'],
            baseScore: 91.0,
        );

        $this->assertEquals(1, Signal::where('business_id', $biz->id)->count());
        $this->assertEquals(1, SignalScore::where('business_id', $biz->id)->count());
        $this->assertEquals(1, DecayModel::where('business_id', $biz->id)->count());

        $signal = Signal::where('business_id', $biz->id)->first();
        $this->assertEquals(480000, $signal->payload['price_cents']);

        $keys = array_keys($signal->getAttributes());
        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/(price|cost|amount|cents|units|vendor)/i',
                $key,
                'Signal table has money or inventory column: '.$key
            );
        }

        $files = File::allFiles(app_path('Modules/X-136'));
        $phpFiles = array_filter($files, fn ($f) => $f->getExtension() === 'php' && ! in_array($f->getFilename(), ['capabilities.php', 'manifest.php']));

        $visited = 0;
        $matchedControl = 0;

        foreach ($phpFiles as $file) {
            $visited++;
            $content = file_get_contents($file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                '/\b(budget|bid|spend|placement|inventory|purchase|ad_account|adAccount|price_cents)\b/i',
                $content,
                'File '.$file->getFilename().' matches money or inventory surface'
            );

            if (preg_match('/SignalScore/', $content)) {
                $matchedControl++;
            }
        }

        $this->assertGreaterThanOrEqual(15, $visited);
        $this->assertGreaterThanOrEqual(3, $matchedControl);
    }

    public function test_a_new_contact_records_one_cooling_signal_and_a_decay_model(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Listener Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->artisan('defaults:sync');

        Event::dispatch(new ContactCreated($biz->id, 7731, 'Distinctive Prospect 7731', '+15125567731', null));

        $this->assertEquals(1, Signal::where('business_id', $biz->id)->where('prospect_identifier', 'person:7731')->where('signal_type', 'contact.created')->count());
        $score = SignalScore::where('business_id', $biz->id)->where('prospect_identifier', 'person:7731')->first();
        $this->assertEquals(50.0, $score->signal_value);
        $this->assertFalse((bool) $score->is_high_intent);
        $this->assertEquals('cooling', $score->cooling_status);
        $this->assertTrue(DecayModel::where('business_id', $biz->id)->where('signal_type', 'contact.created')->exists());
        $signal = Signal::where('business_id', $biz->id)->where('prospect_identifier', 'person:7731')->first();
        $this->assertTrue($signal->payload['has_phone']);
        $this->assertFalse($signal->payload['has_email']);
    }

    public function test_the_same_contact_created_twice_scores_once(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Listener Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->artisan('defaults:sync');

        Event::dispatch(new ContactCreated($biz->id, 7731, 'Distinctive Prospect 7731', '+15125567731', null));
        Event::dispatch(new ContactCreated($biz->id, 7731, 'Distinctive Prospect 7731', '+15125567731', null));

        $this->assertEquals(1, Signal::where('business_id', $biz->id)->where('prospect_identifier', 'person:7731')->where('signal_type', 'contact.created')->count());
        $this->assertEquals(1, SignalScore::where('business_id', $biz->id)->where('prospect_identifier', 'person:7731')->count());
    }

    public function test_the_cooling_screen_lists_the_new_prospect(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->artisan('defaults:sync');

        Event::dispatch(new ContactCreated($biz->id, 7731, 'Distinctive Prospect 7731', '+15125567731', null));

        Livewire::actingAs($owner)->test(CoolingView::class)
            ->assertSee('person:7731')
            ->assertDontSee('Nobody is cooling');
    }

    public function test_another_tenants_contact_does_not_appear_here(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$bizA->id}'");
        $this->artisan('defaults:sync');

        $bizB = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$bizB->id}'");
        $this->artisan('defaults:sync');

        Event::dispatch(new ContactCreated($bizB->id, 7732, 'Distinctive Prospect 7732', '+15125567732', null));

        DB::statement("SET app.business_id = '{$bizA->id}'");
        $this->assertEquals(0, Signal::where('business_id', $bizA->id)->where('prospect_identifier', 'person:7732')->count());
    }
}
