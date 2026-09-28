<?php

declare(strict_types=1);

namespace Tests\Modules\X155;

use App\Modules\X110\Actions\PixelEventsAction;
use App\Modules\X110\Domain\PixelEngine;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Actions\FormAbandonPointAction;
use App\Modules\X155\Actions\FormAdaptiveStepsAction;
use App\Modules\X155\Actions\FormCaptureAction;
use App\Modules\X155\Actions\FormCreateAction;
use App\Modules\X155\Actions\FormGenerateAction;
use App\Modules\X155\Actions\FormReadAction;
use App\Modules\X155\Actions\FormReleaseAction;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;
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
        // 2a: no X-155 CODE file contains staging or pending_leads
        $path = app_path('Modules/X-155');
        $files = Finder::create()
            ->files()
            ->in(array_filter([
                $path.'/Actions',
                $path.'/Models',
                $path.'/Domain',
                $path.'/Database',
                $path.'/Events',
                $path.'/Ui',
            ], 'is_dir'))
            ->append([new \SplFileInfo($path.'/ModuleServiceProvider.php')])
            // G13-35's prose contains 'staging' while asserting there is no staging table.
            ->notName('capabilities.php')
            ->name('*.php');

        $scannedCount = 0;
        foreach ($files as $file) {
            $fileContent = file_get_contents($file->getRealPath());
            $this->assertStringNotContainsString('staging', $fileContent, "File {$file->getFilename()} contains 'staging'");
            $this->assertStringNotContainsString('pending_leads', $fileContent, "File {$file->getFilename()} contains 'pending_leads'");
            $scannedCount++;
        }
        $this->assertGreaterThan(0, $scannedCount);

        // 2b: Form submission references a Person OF THE SAME BUSINESS
        $biz = TestCase::provisionTenant(['name' => 'Anchor Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Anchor Form',
            'slug' => 'anchor-form',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: ['first_name' => 'Anchor', 'phone' => '+15551112222'],
            ipAddress: '127.0.0.1',
            userTimezone: 'America/Chicago'
        );

        $submission = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($submission);
        // clause 2 is enforced at 2026_08_30_000038:32 (person_id NOT NULL FK); ruling 47
        $this->assertNotNull($submission->person_id);
        $person = Person::find($submission->person_id);
        $this->assertEquals($biz->id, $person->business_id);
    }

    /**
     * [G13-05] the tenant can see and release it
     */
    public function test_g13_05_tenant_can_release_it(): void
    {

        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'G13-05 Release']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Release Form',
            'slug' => 'release-form',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Bot',
                'phone' => '+15553330001',
                'website_url' => 'http://spam.ru',
            ],
            ipAddress: '194.55.22.1'
        );

        $this->assertEquals('rejected', $res['status']);
        $submissionId = $res['submission_id'];

        $row = FormSubmission::find($submissionId);
        $this->assertTrue($row->is_spam);

        Event::assertNotDispatched(FormCaptured::class);

        // the release
        $releaseAction = new FormReleaseAction;
        $releaseRes = $releaseAction->handle($biz->id, $submissionId);

        $this->assertEquals('released', $releaseRes['status']);

        $rowFresh = FormSubmission::find($submissionId);
        $this->assertFalse($rowFresh->is_spam);
        $this->assertNull($rowFresh->spam_reason);

        // event emitted; downstream consumption is external to this lane
        Event::assertDispatched(FormCaptured::class, function ($event) use ($submissionId) {
            return $event->submissionId === $submissionId;
        });
    }

    public function test_g13_05_releasing_clean_submission_is_noop(): void
    {
        Event::fake([FormCaptured::class]);
        $biz = TestCase::provisionTenant(['name' => 'G13-05 Noop']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Noop Form',
            'slug' => 'noop-form',
            'steps' => [],
            'schema' => [],
        ]);

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Clean',
            'phone' => '+15550000001',
        ]);

        $sub = FormSubmission::create([
            'business_id' => $biz->id,
            'form_definition_id' => $form->id,
            'person_id' => $person->id,
            'is_spam' => false,
            'payload' => [],
        ]);

        $releaseAction = new FormReleaseAction;
        $releaseRes = $releaseAction->handle($biz->id, $sub->id);

        $this->assertEquals('already_released', $releaseRes['status']);

        $rowFresh = FormSubmission::find($sub->id);
        $this->assertFalse($rowFresh->is_spam);

        Event::assertNotDispatched(FormCaptured::class);
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

    public function test_a_honeypot_containing_zero_is_spam(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'Zero Spam Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Zero Spam Form',
            'slug' => 'zero-spam',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'website_url' => '0',
            ],
            ipAddress: '194.55.22.1'
        );

        $row = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($row);
        $this->assertTrue($row->is_spam);
        $this->assertEquals('honeypot_triggered', $row->spam_reason);

        Event::assertDispatched(FormSpamRejected::class);
        Event::assertNotDispatched(FormCaptured::class);
    }

    public function test_a_honeypot_containing_only_whitespace_is_spam(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'Whitespace Spam Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Whitespace Spam Form',
            'slug' => 'whitespace-spam',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'website_url' => '   ',
            ],
            ipAddress: '194.55.22.1'
        );

        $row = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($row);
        $this->assertTrue($row->is_spam);
        $this->assertEquals('honeypot_triggered', $row->spam_reason);

        Event::assertDispatched(FormSpamRejected::class);
        Event::assertNotDispatched(FormCaptured::class);
    }

    public function test_a_form_with_a_blank_honeypot_field_still_rejects_a_bot(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'Whitespace Spam Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Whitespace Spam Form',
            'slug' => 'whitespace-spam',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => '   ',
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'website_url' => 'http://spam-link.ru',
            ],
            ipAddress: '194.55.22.1'
        );

        $row = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($row);
        $this->assertTrue($row->is_spam);
        $this->assertEquals('honeypot_triggered', $row->spam_reason);

        Event::assertDispatched(FormSpamRejected::class);
        Event::assertNotDispatched(FormCaptured::class);
    }

    public function test_a_form_whose_honeypot_field_is_named_zero_still_rejects_a_bot(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'Whitespace Spam Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Whitespace Spam Form',
            'slug' => 'whitespace-spam',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => '0',
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                '0' => 'http://spam-link.ru',
            ],
            ipAddress: '194.55.22.1'
        );

        $row = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($row);
        $this->assertTrue($row->is_spam);
        $this->assertEquals('honeypot_triggered', $row->spam_reason);

        Event::assertDispatched(FormSpamRejected::class);
        Event::assertNotDispatched(FormCaptured::class);
    }

    /**
     * [G5-07] describing the form is the wizard
     */
    public function test_g5_07_wizard(): void
    {
        Http::fake();

        $gen = new FormGenerateAction;

        // ④ the field set, from ③ the description
        $plain = $gen->handle('I need their name, phone and email, and a preferred date');
        $this->assertEquals(
            ['first_name', 'phone', 'email', 'preferred_date'],
            array_column($plain['fields'], 'name')
        );
        $this->assertEquals([], $plain['refused']);

        // ⑤ a regulated ask never becomes a field
        $regulated = $gen->handle('their name and their social security number');
        $this->assertEquals(['first_name'], array_column($regulated['fields'], 'name'));
        $this->assertEquals(['regulated_ask'], array_column($regulated['refused'], 'reason'));

        // ⑤ and the platform never asks for the age it rejects on
        $aged = $gen->handle('their name and how old they are');
        $this->assertEquals(['first_name'], array_column($aged['fields'], 'name'));
        $this->assertEquals(['under_18_gate'], array_column($aged['refused'], 'reason'));

        // a legitimate ask is not a regulated one because it contains 'age'
        $msg = $gen->handle('their name and a message about the job');
        $this->assertEquals([], $msg['refused']);
        $this->assertEquals(['first_name', 'message'], array_column($msg['fields'], 'name'));

        $still = $gen->handle('their name and their age');
        $this->assertEquals(['under_18_gate'], array_column($still['refused'], 'reason'));

        Http::assertNothingSent();

        $biz = TestCase::provisionTenant(['name' => 'G5-07 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Quote Request',
            'slug' => 'quote-request',
            'steps' => [],
            'schema' => [],
        ]);

        $minor = $this->captureAction->handle($biz->id, $form->id, [
            'first_name' => 'Kid',
            'phone' => '+15550001111',
            'age' => 15,
        ]);

        $this->assertEquals('rejected', $minor['status']);
        $this->assertEquals('under_18', $minor['reason']);
        $this->assertEquals(0, Person::where('business_id', $biz->id)->where('phone', '+15550001111')->count());
        $this->assertEquals(0, FormSubmission::where('business_id', $biz->id)->count());

        // a date of birth carries the same signal
        $dob = $this->captureAction->handle($biz->id, $form->id, [
            'first_name' => 'Kid',
            'phone' => '+15550002222',
            'date_of_birth' => now()->subYears(15)->toDateString(),
        ]);
        $this->assertEquals('under_18', $dob['reason']);
        $this->assertEquals(0, Person::where('business_id', $biz->id)->where('phone', '+15550002222')->count());

        // and an adult still gets through — the gate is not a blanket refusal
        $adult = $this->captureAction->handle($biz->id, $form->id, [
            'first_name' => 'Grown',
            'phone' => '+15550003333',
            'age' => 40,
        ]);
        $this->assertEquals('captured', $adult['status']);
        $this->assertEquals(1, Person::where('business_id', $biz->id)->where('phone', '+15550003333')->count());
    }

    public function test_a_whitespace_date_of_birth_is_not_an_age_signal(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Whitespace DOB Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Whitespace DOB Form',
            'slug' => 'whitespace-dob-form',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle($biz->id, $form->id, [
            'first_name' => 'Adult',
            'phone' => '+15550004444',
            'date_of_birth' => '   ',
        ]);

        $this->assertEquals('captured', $res['status']);
    }

    public function test_an_unreadable_date_of_birth_is_refused_and_named(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Whitespace DOB Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Whitespace DOB Form',
            'slug' => 'whitespace-dob-form',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle($biz->id, $form->id, [
            'first_name' => 'Adult',
            'phone' => '+15550004444',
            'date_of_birth' => 'Distinctive nonsense 4926',
        ]);

        $this->assertEquals('rejected', $res['status']);
        $this->assertEquals('dob_unreadable', $res['reason']);
        $this->assertEquals(0, Person::where('business_id', $biz->id)->where('phone', '+15550004444')->count());
        $this->assertEquals(0, FormSubmission::where('business_id', $biz->id)->count());
    }

    public function test_an_array_date_of_birth_is_not_an_age_signal(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Array DOB Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Array DOB Form',
            'slug' => 'array-dob-form',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle($biz->id, $form->id, [
            'first_name' => 'Adult',
            'phone' => '+15550004444',
            'date_of_birth' => ['1990-01-01'],
        ]);

        $this->assertEquals('captured', $res['status']);
    }

    public function test_a_real_under_eighteen_date_of_birth_is_still_rejected(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Minor DOB Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Minor DOB Form',
            'slug' => 'minor-dob-form',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle($biz->id, $form->id, [
            'first_name' => 'Kid',
            'phone' => '+15550005555',
            'date_of_birth' => now()->subYears(15)->toDateString(),
        ]);

        $this->assertEquals('rejected', $res['status']);
        $this->assertEquals('under_18', $res['reason']);
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

        $numeric = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Table Booking',
            'slug' => 'table-booking',
            'steps' => [
                ['step' => 1, 'required' => ['party_size']],
                ['step' => 2, 'show_if' => ['party_size' => 4], 'required' => ['high_chairs']],
            ],
            'schema' => [],
        ]);

        $applicable = (new FormAdaptiveStepsAction)->handle($numeric, ['party_size' => '4']);
        $this->assertEquals([1, 2], array_column($applicable, 'step'));

        $result = $this->validateAction->handle($biz->id, $numeric->id, ['party_size' => '4']);
        $this->assertFalse($result['is_valid']);
        $this->assertEquals('incomplete_step', $result['reason']);
        $this->assertEquals(2, $result['step']);
        $this->assertEquals(['high_chairs'], $result['missing']);

        $applicable = (new FormAdaptiveStepsAction)->handle($numeric, ['party_size' => '2']);
        $this->assertEquals([1], array_column($applicable, 'step'));
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

    public function test_a_short_form_keeps_the_name_and_email_the_business_already_has(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Short Form Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Short Form',
            'slug' => 'short',
            'steps' => [],
            'schema' => [],
        ]);

        $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Alice',
                'email' => 'alice@example.com',
                'phone' => '+15551234567',
            ]
        );

        $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'phone' => '+15551234567',
                'message' => 'Call me back',
            ]
        );

        $alice = Person::where('business_id', $biz->id)->where('phone', '+15551234567')->firstOrFail();
        $this->assertEquals('Alice', $alice->first_name, 'a phone only submission renamed a known contact to the placeholder');
        $this->assertEquals('alice@example.com', $alice->email);

        $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'phone' => '+15550009999',
                'message' => 'New number',
            ]
        );

        $visitor = Person::where('business_id', $biz->id)->where('phone', '+15550009999')->firstOrFail();
        $this->assertEquals('Visitor', $visitor->first_name, 'a first submission with no name must still record the visitor placeholder');
        $this->assertNull($visitor->email);
    }

    public function test_two_phone_less_spam_submissions_get_their_own_contacts(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'Spam Guard Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Guard Form',
            'slug' => 'guard',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $sub1 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Alice',
                'website_url' => 'http://spam-link.ru',
            ]
        );

        $sub2 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Bob',
                'website_url' => 'http://spam-link.ru',
            ]
        );

        $sub3 = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Charlie',
                'phone' => '   ',
                'website_url' => 'http://spam-link.ru',
            ]
        );

        $this->assertNotSame($sub1['person_id'], $sub2['person_id'], 'two phone-less spam submissions were funnelled into one contact');
        $this->assertSame(0, Person::where('business_id', $biz->id)->where('phone', '+15550000000')->count(), 'the reserved fallback number was written to a contact row');
        $this->assertSame(0, Person::where('business_id', $biz->id)->where('phone', '')->count());
        $this->assertSame(0, Person::where('business_id', $biz->id)->where('phone', '   ')->count());

        foreach ([$sub1, $sub2, $sub3] as $sub) {
            $this->assertSame('rejected', $sub['status']);
            $dbSub = FormSubmission::where('business_id', $biz->id)->findOrFail($sub['submission_id']);
            $this->assertTrue($dbSub->is_spam);
            $this->assertNotNull($dbSub->person_id);
        }

        $this->assertSame(3, FormSubmission::where('business_id', $biz->id)->where('is_spam', true)->count());
    }

    public function test_a_spam_submission_never_rewrites_a_known_contact(): void
    {
        Event::fake([FormCaptured::class, FormSpamRejected::class]);
        $biz = TestCase::provisionTenant(['name' => 'Spam Guard Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Guard Form',
            'slug' => 'guard',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Alice',
                'email' => 'alice@example.com',
                'phone' => '+15551110001',
            ]
        );

        $spam = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Bot Spammer',
                'email' => 'bot@spam.ru',
                'phone' => '+15551110001',
                'website_url' => 'http://spam-link.ru',
            ]
        );

        $this->assertEquals('rejected', $spam['status']);
        $this->assertEquals('honeypot_triggered', $spam['reason']);

        $alice = Person::where('business_id', $biz->id)->where('phone', '+15551110001')->firstOrFail();
        $this->assertEquals('Alice', $alice->first_name, 'a spam submission rewrote a known contact with the bot payload');
        $this->assertEquals('alice@example.com', $alice->email);
        $this->assertEquals($alice->id, FormSubmission::find($spam['submission_id'])->person_id, 'a spam submission must still reference the contact it matched');
        $this->assertEquals(1, Person::where('business_id', $biz->id)->where('phone', '+15551110001')->count());

        $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'phone' => '+15551110002',
                'issue_description' => 'Help me',
                'website_url' => 'http://spam-link.ru',
            ]
        );

        $unknown = Person::where('business_id', $biz->id)->where('phone', '+15551110002')->firstOrFail();
        $this->assertEquals('Visitor', $unknown->first_name, 'a spam submission from an unknown number must still record the visitor placeholder');
    }

    public function test_a_phone_less_submission_gets_its_own_contact(): void
    {
        Event::fake([FormCaptured::class]);
        $biz = TestCase::provisionTenant(['name' => 'Spam Guard Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Guard Form',
            'slug' => 'guard',
            'steps' => [],
            'schema' => [],
        ]);

        $resA = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Alice',
                'email' => 'alice@example.com',
            ]
        );

        $resB = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Bob',
                'email' => 'bob@example.com',
            ]
        );

        $resC = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Carol',
                'phone' => '+15556660001',
            ]
        );

        $resD = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Carol Updated',
                'phone' => '+15556660001',
            ]
        );

        $this->assertNotSame($resA['person_id'], $resB['person_id'], 'two phone less submissions were funnelled into one contact');
        $this->assertSame('Alice', Person::findOrFail($resA['person_id'])->first_name, 'the first submitters name was overwritten by the second');
        $this->assertSame(0, Person::where('business_id', $biz->id)->where('phone', '+15550000000')->count(), 'the reserved fallback number was written to a contact row');
        $this->assertNotNull($resA['person_id']);
        $this->assertSame('Bob', Person::findOrFail($resB['person_id'])->first_name);
        $this->assertSame($resC['person_id'], $resD['person_id'], 'two submissions on the same phone must resolve to one contact');
    }

    public function test_a_blank_phone_submission_gets_its_own_contact(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Blank Phone Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Guard Form',
            'slug' => 'guard',
            'steps' => [],
            'schema' => [],
        ]);
        Event::fake([FormCaptured::class]);

        $resA = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Dana',
                'email' => 'dana@example.com',
                'phone' => '',
            ]
        );

        $resB = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Erin',
                'email' => 'erin@example.com',
                'phone' => '',
            ]
        );

        $resC = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Gail',
                'phone' => '   ',
            ]
        );

        $resD = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Frank',
                'phone' => '+15557770001',
            ]
        );

        $resE = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Frank Updated',
                'phone' => '+15557770001',
            ]
        );

        $this->assertNotSame($resA['person_id'], $resB['person_id'], 'two blank phone submissions were funnelled into one contact');
        $this->assertSame('Dana', Person::findOrFail($resA['person_id'])->first_name, 'the first submitters name was overwritten by the second');
        $this->assertSame(0, Person::where('business_id', $biz->id)->where('phone', '')->count(), 'the empty string was stored as a phone number');
        $this->assertNull(Person::findOrFail($resC['person_id'])->phone, 'a whitespace only phone was stored as a phone number');
        $this->assertNotNull($resA['person_id']);
        $this->assertSame('Erin', Person::findOrFail($resB['person_id'])->first_name);
        $this->assertSame($resD['person_id'], $resE['person_id'], 'two submissions on the same phone must resolve to one contact');
    }

    public function test_a_blank_payload_value_is_not_a_value_the_visitor_gave(): void
    {
        Event::fake([FormCaptured::class]);
        $biz = TestCase::provisionTenant(['name' => 'Blank Value Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Guard Form',
            'slug' => 'guard',
            'steps' => [],
            'schema' => [],
        ]);

        $resA = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Dana',
                'phone' => '+15557770001',
                'email' => 'dana@example.com',
            ]
        );

        $resB = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => '',
                'phone' => '+15557770001',
            ]
        );

        $person = Person::where('business_id', $biz->id)->where('phone', '+15557770001')->firstOrFail();
        $this->assertSame('Dana', $person->first_name, 'a submission with a blank name erased the stored name');

        $resC = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'phone' => '+15557770001',
                'email' => '',
            ]
        );

        $person = Person::where('business_id', $biz->id)->where('phone', '+15557770001')->firstOrFail();
        $this->assertSame('dana@example.com', $person->email, 'a submission with a blank email erased the stored email');

        $resD = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Dana Updated',
                'phone' => '+15557770001',
                'email' => 'dana.new@example.com',
            ]
        );

        $person = Person::where('business_id', $biz->id)->where('phone', '+15557770001')->firstOrFail();
        $this->assertSame('Dana Updated', $person->first_name, 'a visitor must be able to correct their own name');
        $this->assertSame('dana.new@example.com', $person->email, 'a visitor must be able to correct their own email');

        $resE = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => '   ',
                'phone' => '+15557770002',
            ]
        );

        $second = Person::where('business_id', $biz->id)->where('phone', '+15557770002')->firstOrFail();
        $this->assertSame('Visitor', $second->first_name, 'a new contact whose name is whitespace must get the visitor placeholder');

        $resF = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => '',
                'phone' => '+15557770003',
                'website_url' => 'http://spam-link.ru',
            ]
        );

        $spam = Person::where('business_id', $biz->id)->where('phone', '+15557770003')->firstOrFail();
        $this->assertSame('Visitor', $spam->first_name, 'a spam submission with a blank name must still record the visitor placeholder');

        $this->assertSame($resA['person_id'], $resD['person_id'], 'four submissions on one phone must resolve to one contact');
        $this->assertNotNull($resA['person_id']);
    }

    public function test_an_array_phone_is_not_a_contact_detail_and_the_submission_is_captured(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Array Phone Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Array Phone Form',
            'slug' => 'array-phone-form',
            'steps' => [],
            'schema' => [],
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'first_name' => 'Alice',
                'phone' => ['+15550001111'],
            ]
        );

        $this->assertEquals('captured', $res['status']);

        $submission = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($submission);
        $this->assertEquals(['+15550001111'], $submission->payload['phone']);

        $person = Person::find($submission->person_id);
        $this->assertNotNull($person);
        $this->assertNull($person->phone);
    }

    public function test_an_array_phone_on_a_spam_submission_is_still_stored_and_flagged(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Array Phone Spam Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Array Phone Spam Form',
            'slug' => 'array-phone-spam-form',
            'steps' => [],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: [
                'website_url' => 'http://spam.ru',
                'phone' => ['+15550002222'],
            ],
            ipAddress: '194.55.22.1'
        );

        $this->assertEquals('rejected', $res['status']);
        $this->assertEquals('honeypot_triggered', $res['reason']);

        $submission = FormSubmission::find($res['submission_id']);
        $this->assertNotNull($submission);
        $this->assertTrue($submission->is_spam);
    }

    public function test_form_create_makes_a_one_step_lead_form_with_name_and_phone_required(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Creator Tenant']);
        Tenancy::set((int) $biz->id);
        $action = app(FormCreateAction::class);
        $form = $action->handle($biz->id, 'Contact us');

        $this->assertEquals('Contact us', $form->form_name);
        $this->assertEquals('contact-us', $form->slug);
        $this->assertIsArray($form->steps);
        $this->assertCount(1, $form->steps);
        $this->assertEquals(['name', 'phone'], $form->steps[0]['required']);
    }

    public function test_form_create_refuses_a_duplicate_name_and_returns_the_existing_row(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Duplicate Tenant']);
        Tenancy::set((int) $biz->id);
        $action = app(FormCreateAction::class);
        $form1 = $action->handle($biz->id, 'Duplicate Form');
        $form2 = $action->handle($biz->id, 'Duplicate Form');

        $this->assertSame($form1->id, $form2->id);
        $this->assertEquals(1, FormDefinition::where('business_id', $biz->id)->count());
    }

    public function test_form_create_is_tenant_scoped(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Biz A']);
        $bizB = TestCase::provisionTenant(['name' => 'Biz B']);

        Tenancy::set((int) $bizA->id);
        $action = app(FormCreateAction::class);
        $action->handle($bizA->id, 'Shared Name');

        Tenancy::set((int) $bizB->id);

        $this->assertEquals(0, FormDefinition::where('business_id', $bizB->id)->count());

        $formB = $action->handle($bizB->id, 'Shared Name');
        $this->assertEquals('shared-name', $formB->slug);
    }

    public function test_form_read_returns_the_oldest_definition_shape(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Form Read Shape Tenant']);
        $action = new FormReadAction;

        $this->assertNull($action->firstDefinitionForBusiness($biz->id));

        $form1 = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Oldest Form',
            'slug' => 'oldest',
            'schema' => ['fields' => [['name' => 'first_name']]],
            'steps' => [['step' => 1, 'required' => ['first_name']]],
            'honeypot_field' => 'bot_trap',
        ]);

        $form2 = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Newer Form',
            'slug' => 'newer',
            'schema' => ['fields' => [['name' => 'phone']]],
            'steps' => [['step' => 1, 'required' => ['phone']]],
            'honeypot_field' => 'bot_trap2',
        ]);

        $shape = $action->firstDefinitionForBusiness($biz->id);

        $this->assertNotNull($shape);
        $this->assertEquals($form1->id, $shape['id']);
        $this->assertEquals([['name' => 'first_name']], $shape['fields']);
        $this->assertEquals(['first_name'], $shape['required']);
        $this->assertEquals('bot_trap', $shape['honeypot']);
    }

    public function test_an_incomplete_step_names_its_real_position_for_a_form_the_owner_created(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'X155 Test 4651']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = app(FormCreateAction::class)->handle($biz->id, 'Distinctive form 4651');

        $res = $this->captureAction->handle(
            businessId: $biz->id,
            formDefinitionId: $form->id,
            payload: ['name' => 'Distinctive 4652']
        );

        $this->assertEquals('rejected', $res['status'] ?? null);
        $this->assertEquals('incomplete_step', $res['reason']);
        $this->assertEquals(1, $res['step']);
        $this->assertEquals(['phone'], $res['missing']);
    }

    public function test_form_read_prefers_the_oldest_definition_with_fields(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Form Read Shape Tenant']);
        $action = new FormReadAction;

        $empty = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Empty Form',
            'slug' => 'empty-4901',
            'schema' => ['fields' => []],
            'steps' => [['step' => 1, 'required' => []]],
        ]);

        $withFields = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Real Form',
            'slug' => 'real-4902',
            'schema' => ['fields' => [['name' => 'phone', 'label' => 'Phone', 'type' => 'tel']]],
            'steps' => [['step' => 1, 'required' => ['phone']]],
        ]);

        $this->assertEquals($withFields->id, $action->firstDefinitionForBusiness($biz->id)['id']);

        $biz2 = TestCase::provisionTenant(['name' => 'Form Read Shape Tenant 2']);
        $empty2 = FormDefinition::create([
            'business_id' => $biz2->id,
            'form_name' => 'Empty Form 2',
            'slug' => 'empty-4901-2',
            'schema' => ['fields' => []],
            'steps' => [['step' => 1, 'required' => []]],
        ]);

        $this->assertEquals($empty2->id, $action->firstDefinitionForBusiness($biz2->id)['id']);
    }
}
