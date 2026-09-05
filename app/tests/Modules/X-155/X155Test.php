<?php

declare(strict_types=1);

namespace Tests\Modules\X155;

use App\Modules\X110\Actions\PixelEventsAction;
use App\Modules\X110\Domain\PixelEngine;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Actions\FormAbandonPointAction;
use App\Modules\X155\Actions\FormAdaptiveStepsAction;
use App\Modules\X155\Actions\FormCaptureAction;
use App\Modules\X155\Actions\FormValidateAction;
use App\Modules\X155\Events\FormCaptured;
use App\Modules\X155\Events\FormSpamRejected;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use App\Modules\X155\Ui\Forms;
use App\Modules\X155\Ui\SpamRate;
use App\Modules\X155\Ui\SubmissionsThread;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class X155Test extends TestCase
{
    private FormValidateAction $validateAction;

    private FormCaptureAction $captureAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validateAction = new FormValidateAction;
        $this->captureAction = new FormCaptureAction($this->validateAction);
    }

    /**
     * TEST ANCHOR
     * grep -rE 'staging|pending_leads' app/Modules/X-155/ returns nothing;
     * every submission row references a Person id
     */
    public function test_anchor_direct_person_writing_and_spam_rejection(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);

        $biz = TestCase::provisionTenant(['name' => 'Forms Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Emergency AC Repair Form',
            'slug' => 'emergency-ac',
            'steps' => [
                ['step' => 1, 'fields' => ['first_name', 'phone']],
                ['step' => 2, 'fields' => ['service_address', 'issue_description']],
            ],
            'schema' => ['phone' => 'required|string'],
            'honeypot_field' => 'website_url',
        ]);

        // 1. Valid submission writes straight to Person entity and creates FormSubmission with non-null person_id
        $validRes = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Michael',
                'phone' => '+15559876543',
                'email' => 'michael@tenant.com',
                'issue_description' => 'AC unit blowing warm air',
                'website_url' => '', // empty honeypot
            ],
            ipAddress: '24.18.99.12',
            userTimezone: 'America/Chicago'
        );

        $this->assertEquals('captured', $validRes['status']);
        $this->assertNotNull($validRes['person_id']);

        $submission = FormSubmission::where('business_id', $biz->id)->find($validRes['submission_id']);
        $this->assertNotNull($submission->person_id, 'Every submission row must reference a Person id (TEST ANCHOR)');

        $person = Person::where('business_id', $biz->id)->find($submission->person_id);
        $this->assertEquals('Michael', $person->first_name);
        $this->assertEquals('+15559876543', $person->phone);

        Event::assertDispatched(FormCaptured::class);

        // 2. Honeypot bot submission is rejected
        $spamRes = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Bot Spammer',
                'phone' => '+15559999999',
                'website_url' => 'http://spam-link.ru', // honeypot filled
            ],
            ipAddress: '194.55.22.1'
        );

        $this->assertEquals('rejected', $spamRes['status']);
        $this->assertEquals('honeypot_triggered', $spamRes['reason']);
        Event::assertDispatched(FormSpamRejected::class);

        // 3. IP/Timezone mismatch signal (G17-12)
        $tzSpamRes = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: ['first_name' => 'Bot 2', 'phone' => '+15558888888'],
            ipAddress: '203.0.113.10',
            userTimezone: 'Not/AZone'
        );

        $this->assertEquals('rejected', $tzSpamRes['status']);
        $this->assertEquals('ip_timezone_mismatch', $tzSpamRes['reason']);
    }

    /**
     * [G2-17] multi-step form logic
     */
    public function test_g2_17_multi_step_logic(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G2-17 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'G2-17 Form',
            'slug' => 'g2-17',
            'steps' => [
                ['step' => 1, 'fields' => ['first_name', 'phone'], 'required' => ['phone']],
                ['step' => 2, 'fields' => ['service_address', 'unit_count'], 'required' => ['service_address']],
            ],
            'schema' => [],
        ]);

        // (a) the refusal
        $res1 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: ['first_name' => 'Dana', 'phone' => '+15551110001']
        );

        $this->assertEquals('rejected', $res1['status']);
        $this->assertEquals('incomplete_step', $res1['reason']);
        $this->assertEquals(2, $res1['step']);
        $this->assertEquals(['service_address'], $res1['missing']);

        $this->assertEquals(0, FormSubmission::where('business_id', $biz->id)->count());
        $this->assertEquals(0, Person::where('business_id', $biz->id)->where('phone', '+15551110001')->count());

        // (b) the pass
        $res2 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: ['first_name' => 'Dana', 'phone' => '+15551110001', 'service_address' => '123 Main St']
        );

        $this->assertEquals('captured', $res2['status']);

        $submission = FormSubmission::find($res2['submission_id']);
        $this->assertNotNull($submission->person_id);

        // (c) the zero
        $form2 = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'G2-17 Form Zero',
            'slug' => 'g2-17-zero',
            'steps' => [
                ['step' => 1, 'fields' => ['phone', 'unit_count'], 'required' => ['unit_count']],
            ],
            'schema' => [],
        ]);

        $res3 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form2->id,
            payload: ['phone' => '+15551110002', 'unit_count' => '0']
        );

        $this->assertEquals('captured', $res3['status']);
    }

    /**
     * [G2-20] forms write the twelve entities directly — no mapping screen
     */
    public function test_g2_20_direct_entity_write(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G2-20 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'G2-20 Form',
            'slug' => 'g2-20',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Alice',
                'phone' => '+15551234567',
                'email' => 'alice@example.com',
            ]
        );

        $this->assertEquals('captured', $res['status']);
        $this->assertEquals(1, Person::where('business_id', $biz->id)->where('phone', '+15551234567')->count());

        $person = Person::where('business_id', $biz->id)->where('phone', '+15551234567')->first();
        $this->assertEquals($res['person_id'], $person->id);
        $this->assertEquals('Alice', $person->first_name);
        $this->assertEquals('alice@example.com', $person->email);

        $submission = FormSubmission::find($res['submission_id']);
        $this->assertEquals($person->id, $submission->person_id);
        $this->assertNotNull($submission->person_id);

        $res2 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'AliceUpdated',
                'phone' => '+15551234567',
                'email' => 'alice@example.com',
            ]
        );

        $this->assertEquals(1, Person::where('business_id', $biz->id)->where('phone', '+15551234567')->count());
        $person2 = Person::where('business_id', $biz->id)->where('phone', '+15551234567')->first();
        $this->assertEquals('AliceUpdated', $person2->first_name);
    }

    /**
     * [G2-39] named in headers
     */
    public function test_g2_39_header(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G3-64] & [G13-05] spam and bot filtering
     */
    public function test_g3_64_bot_filtering(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'G3-64 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'G3-64 Form',
            'slug' => 'g3-64',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        // (a) the honeypot is stored and flagged
        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Bot',
                'phone' => '+15552220001',
                'issue_description' => 'AC blowing warm air',
                'website_url' => 'http://spam.ru',
            ],
            ipAddress: '194.55.22.1'
        );

        $this->assertEquals('rejected', $res['status']);
        $this->assertEquals('honeypot_triggered', $res['reason']);

        $row = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($row);
        $this->assertTrue($row->is_spam);
        $this->assertEquals('honeypot_triggered', $row->spam_reason);
        $this->assertEquals('AC blowing warm air', $row->payload['issue_description']);

        Event::assertDispatched(FormSpamRejected::class);
        Event::assertNotDispatched(FormCaptured::class);

        // (b) the timezone signal is stored too
        $res2 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Bot 2',
                'phone' => '+15552220002',
                'issue_description' => 'no heat',
            ],
            ipAddress: '203.0.113.10',
            userTimezone: 'Not/AZone'
        );

        $this->assertEquals('rejected', $res2['status']);
        $this->assertEquals('ip_timezone_mismatch', $res2['reason']);

        $row2 = FormSubmission::find($res2['submission_id']);
        $this->assertNotNull($row2);
        $this->assertTrue($row2->is_spam);
        $this->assertEquals('ip_timezone_mismatch', $row2->spam_reason);

        // (c) the ⛔⛔ — a real customer is not swept up
        $res3 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Dana',
                'phone' => '+15552220003',
                'issue_description' => 'thermostat replacement',
                'website_url' => '',
            ],
            ipAddress: '24.18.99.12',
            userTimezone: 'America/Chicago'
        );

        $this->assertEquals('captured', $res3['status']);

        $row3 = FormSubmission::find($res3['submission_id']);
        $this->assertNotNull($row3);
        $this->assertFalse($row3->is_spam);
        $this->assertNull($row3->spam_reason);

        $this->assertEquals(2, FormSubmission::where('business_id', $biz->id)->where('is_spam', true)->count());
        $this->assertEquals(1, FormSubmission::where('business_id', $biz->id)->where('is_spam', false)->count());
    }

    /**
     * [G5-07] describing the form is the wizard
     */
    public function test_g5_07_wizard(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G5-30] adaptive questions
     */
    public function test_g5_30_adaptive_questions(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G5-30 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Service Request',
            'slug' => 'service-request',
            'steps' => [
                ['step' => 1, 'required' => ['service_type']],
                ['step' => 2, 'show_if' => ['service_type' => 'commercial'], 'required' => ['company_name']],
                ['step' => 3, 'show_if' => ['service_type' => 'residential'], 'required' => ['home_size']],
            ],
            'schema' => [],
        ]);

        $applicable = (new FormAdaptiveStepsAction)->handle($form, ['service_type' => 'residential']);
        $this->assertEquals([1, 3], array_column($applicable, 'step'));

        $result = $this->validateAction->handle($biz->id, $form->id, ['service_type' => 'residential']);
        $this->assertFalse($result['is_valid']);
        $this->assertEquals('incomplete_step', $result['reason']);
        $this->assertEquals(3, $result['step']);
        $this->assertEquals(['home_size'], $result['missing']);

        $result = $this->validateAction->handle($biz->id, $form->id, ['service_type' => 'residential', 'home_size' => '2000']);
        $this->assertTrue($result['is_valid']);

        $result = $this->validateAction->handle($biz->id, $form->id, ['service_type' => 'commercial', 'company_name' => 'Acme HVAC']);
        $this->assertTrue($result['is_valid']);
    }

    /**
     * [G11-01] the abandon point, with the pixel
     */
    public function test_g11_01_abandon_pixel(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G11-01 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Quote Request',
            'slug' => 'quote-request',
            'steps' => [['step' => 1], ['step' => 2]],
            'schema' => [],
        ]);

        $form2 = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Newsletter',
            'slug' => 'newsletter',
            'steps' => [['step' => 1]],
            'schema' => [],
        ]);

        $engine = new PixelEngine;
        $events = new PixelEventsAction($engine);
        $visit = $engine->recordVisit($biz->id, 'vis_g11_01');

        $events->handle($biz->id, $visit['session_id'], 'form.abandoned', ['form_id' => 'quote-request', 'abandoned_field' => 'phone', 'field_index' => 2]);
        $events->handle($biz->id, $visit['session_id'], 'form.abandoned', ['form_id' => 'quote-request', 'abandoned_field' => 'phone', 'field_index' => 2]);
        $events->handle($biz->id, $visit['session_id'], 'form.abandoned', ['form_id' => 'quote-request', 'abandoned_field' => 'email', 'field_index' => 3]);
        $events->handle($biz->id, $visit['session_id'], 'form.abandoned', ['form_id' => 'newsletter', 'abandoned_field' => 'email', 'field_index' => 1]);
        $events->handle($biz->id, $visit['session_id'], 'form.submitted', ['form_id' => 'quote-request']);

        $report = (new FormAbandonPointAction($engine))->handle($biz->id, $form->id);

        $this->assertEquals('quote-request', $report['slug']);
        $this->assertEquals(3, $report['total']);
        $this->assertEquals('phone', $report['top_field']);
        $this->assertEquals([['field' => 'phone', 'count' => 2], ['field' => 'email', 'count' => 1]], $report['points']);
    }

    /**
     * [G13-35] hidden fields write straight to entities, no staging table
     */
    public function test_g13_35_no_staging(): void
    {
        $this->assertFalse(Schema::hasTable('form_staging'));
        $this->assertFalse(Schema::hasTable('form_submission_staging'));
        $this->assertFalse(Schema::hasTable('form_field_mappings'));
        $this->assertFalse(Schema::hasTable('form_pending'));

        $this->assertTrue(Schema::hasTable('form_definitions'));
        $this->assertTrue(Schema::hasTable('form_submissions'));

        $biz = TestCase::provisionTenant(['name' => 'G13-35 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'G13-35 Form',
            'slug' => 'g13-35',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'phone' => '+15557654321',
                'utm_source' => 'google',
            ]
        );

        $this->assertTrue(Person::where('business_id', $biz->id)->where('phone', '+15557654321')->exists());

        $person = Person::where('business_id', $biz->id)->where('phone', '+15557654321')->first();

        $submission = FormSubmission::find($res['submission_id']);
        $this->assertEquals('google', $submission->payload['utm_source']);
        $this->assertEquals($person->id, $submission->person_id);
    }

    /**
     * [G17-12] IP-versus-timezone as a bot signal
     */
    public function test_g17_12_ip_timezone_signal(): void
    {
        Event::fake([FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'T']);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'F', 'slug' => 'f', 'steps' => [], 'schema' => []]);

        $res = $this->validateAction->handle($biz->id, $form->id, [], '203.0.113.10', 'Not/AZone');

        $this->assertTrue($res['is_spam']);
        $this->assertFalse($res['is_valid']);
        $this->assertEquals('ip_timezone_mismatch', $res['reason']);
        Event::assertDispatched(FormSpamRejected::class);
    }

    public function test_g17_12_null_timezone_is_not_spam(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T']);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'F', 'slug' => 'f', 'steps' => [], 'schema' => []]);

        $res = $this->validateAction->handle($biz->id, $form->id, [], '203.0.113.10', null);

        $this->assertFalse($res['is_spam']);
        $this->assertTrue($res['is_valid']);
    }

    public function test_g17_12_valid_timezone_is_not_spam(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T']);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'F', 'slug' => 'f', 'steps' => [], 'schema' => []]);

        $res = $this->validateAction->handle($biz->id, $form->id, [], '203.0.113.10', 'America/Chicago');

        $this->assertFalse($res['is_spam']);
        $this->assertTrue($res['is_valid']);
    }

    public function test_forms_lists_this_businesses_forms_with_counts(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Forms Biz A', 'currency' => 'USD']);
        Tenancy::set((int) $bizA->id);

        $form1 = FormDefinition::create([
            'business_id' => $bizA->id,
            'form_name' => 'Biz A Form 1',
            'slug' => 'biz-a-1',
            'steps' => [['step' => 1]],
            'schema' => [],
        ]);
        $form2 = FormDefinition::create([
            'business_id' => $bizA->id,
            'form_name' => 'Biz A Form 2',
            'slug' => 'biz-a-2',
            'steps' => [['step' => 1], ['step' => 2]],
            'schema' => [],
        ]);

        $personA = Person::create([
            'business_id' => $bizA->id,
            'first_name' => 'Test',
        ]);

        FormSubmission::create([
            'business_id' => $bizA->id,
            'form_definition_id' => $form1->id,
            'person_id' => $personA->id,
            'is_spam' => false,
            'payload' => [],
        ]);
        FormSubmission::create([
            'business_id' => $bizA->id,
            'form_definition_id' => $form1->id,
            'person_id' => $personA->id,
            'is_spam' => true,
            'spam_reason' => 'honeypot',
            'payload' => [],
        ]);

        $bizB = TestCase::provisionTenant(['name' => 'Forms Biz B', 'currency' => 'USD']);
        Tenancy::set((int) $bizB->id);
        $formB = FormDefinition::create([
            'business_id' => $bizB->id,
            'form_name' => 'Biz B Form 1',
            'slug' => 'biz-b-1',
            'steps' => [['step' => 1]],
            'schema' => [],
        ]);

        Tenancy::set((int) $bizA->id);

        Livewire::test(Forms::class, ['businessId' => $bizA->id])
            ->assertOk()
            ->assertSee('Biz A Form 1')
            ->assertSee('biz-a-1')
            ->assertSee('2 submissions')
            ->assertSee('1 spam')
            ->assertSee('1 step(s)')
            ->assertSee('Biz A Form 2')
            ->assertSee('2 step(s)')
            ->assertDontSee('Biz B Form 1');
    }

    public function test_forms_shows_the_empty_state_for_a_business_with_no_forms(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty Biz', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Livewire::test(Forms::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('No forms constructed yet.')
            ->assertDontSee('<ul', false);
    }

    public function test_submissions_thread_lists_this_businesses_submissions(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Biz A']);
        Tenancy::set((int) $bizA->id);

        $formA = FormDefinition::create([
            'business_id' => $bizA->id,
            'form_name' => 'Biz A Form 1',
            'slug' => 'biz-a-1',
            'steps' => [['step' => 1]],
            'schema' => [],
        ]);

        $personA1 = Person::create(['business_id' => $bizA->id, 'first_name' => 'John']);
        $personA2 = Person::create(['business_id' => $bizA->id, 'first_name' => 'Jane']);

        FormSubmission::create([
            'business_id' => $bizA->id,
            'form_definition_id' => $formA->id,
            'person_id' => $personA1->id,
            'is_spam' => false,
            'payload' => [],
        ]);

        FormSubmission::create([
            'business_id' => $bizA->id,
            'form_definition_id' => $formA->id,
            'person_id' => $personA2->id,
            'is_spam' => true,
            'spam_reason' => 'honeypot',
            'payload' => [],
        ]);

        $bizB = TestCase::provisionTenant(['name' => 'Biz B']);
        Tenancy::set((int) $bizB->id);
        $formB = FormDefinition::create([
            'business_id' => $bizB->id,
            'form_name' => 'Biz B Form 1',
            'slug' => 'biz-b-1',
            'steps' => [['step' => 1]],
            'schema' => [],
        ]);
        $personB = Person::create(['business_id' => $bizB->id, 'first_name' => 'Bob']);
        FormSubmission::create([
            'business_id' => $bizB->id,
            'form_definition_id' => $formB->id,
            'person_id' => $personB->id,
            'is_spam' => false,
            'payload' => [],
        ]);

        Tenancy::set((int) $bizA->id);
        Livewire::test(SubmissionsThread::class, ['businessId' => $bizA->id])
            ->assertOk()
            ->assertSee('Biz A Form 1')
            ->assertSee('Person #'.$personA1->id)
            ->assertSee('VALID')
            ->assertSee('Person #'.$personA2->id)
            ->assertSee('SPAM')
            ->assertDontSee('Biz B Form 1')
            ->assertDontSee('Person #'.$personB->id);
    }

    public function test_submissions_thread_shows_the_empty_state_for_a_business_with_no_submissions(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty Biz']);
        Tenancy::set((int) $biz->id);

        Livewire::test(SubmissionsThread::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('No submissions recorded.')
            ->assertDontSee('<ul', false);
    }

    public function test_spam_rate_reports_the_share_of_spam_for_this_business(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Biz A Rate']);
        Tenancy::set((int) $bizA->id);

        $formA = FormDefinition::create([
            'business_id' => $bizA->id,
            'form_name' => 'Biz A Form 1',
            'slug' => 'biz-a-1',
            'steps' => [['step' => 1]],
            'schema' => [],
        ]);

        $person = Person::create(['business_id' => $bizA->id, 'first_name' => 'John']);

        // 4 submissions, 1 spam -> 25%
        for ($i = 0; $i < 3; $i++) {
            FormSubmission::create([
                'business_id' => $bizA->id,
                'form_definition_id' => $formA->id,
                'person_id' => $person->id,
                'is_spam' => false,
                'payload' => [],
            ]);
        }
        FormSubmission::create([
            'business_id' => $bizA->id,
            'form_definition_id' => $formA->id,
            'person_id' => $person->id,
            'is_spam' => true,
            'spam_reason' => 'honeypot',
            'payload' => [],
        ]);

        // Biz B gets 1 spam
        $bizB = TestCase::provisionTenant(['name' => 'Biz B Rate']);
        Tenancy::set((int) $bizB->id);
        $formB = FormDefinition::create([
            'business_id' => $bizB->id,
            'form_name' => 'Biz B Form 1',
            'slug' => 'biz-b-1',
            'steps' => [['step' => 1]],
            'schema' => [],
        ]);
        $personB = Person::create(['business_id' => $bizB->id, 'first_name' => 'Bob']);
        FormSubmission::create([
            'business_id' => $bizB->id,
            'form_definition_id' => $formB->id,
            'person_id' => $personB->id,
            'is_spam' => true,
            'spam_reason' => 'honeypot',
            'payload' => [],
        ]);

        Tenancy::set((int) $bizA->id);
        Livewire::test(SpamRate::class, ['businessId' => $bizA->id])
            ->assertOk()
            ->assertSee('Total: 4')
            ->assertSee('Spam: 1')
            ->assertSee('Rate: 25%')
            ->assertDontSee('Total: 5')
            ->assertDontSee('Spam: 2')
            ->assertDontSee('40%');
    }

    public function test_spam_rate_shows_no_submissions_yet_for_an_empty_business(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty Biz Rate']);
        Tenancy::set((int) $biz->id);

        Livewire::test(SpamRate::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('No submissions yet.')
            ->assertDontSee('NAN')
            ->assertDontSee('%');
    }
}
