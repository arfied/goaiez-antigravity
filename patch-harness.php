<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$signUp = <<<PHP
    private function signUp(string \$businessName, string \$phone): array
    {
        \$owner = \App\Models\User::factory()->create();
        app(\App\Services\Sms\TenantNumbers::class)->addToPool('+15125550999');
        \$res = app(\App\Modules\X118\Actions\OnboardingStartAction::class)->handle(\$owner, \$businessName, \$phone);
        if (\$res['asked_fields_count'] !== 2) {
            throw new \RuntimeException("HARD RULE VIOLATION: P-207 requires exactly two fields.");
        }
        return \App\Models\Business::find(\$res['business_id'])->toArray();
    }
PHP;
$content = preg_replace('/private function signUp\(string \$businessName, string \$phone\): array\n\s+\{\n.*?\}\n/s', $signUp . "\n", $content);

$wait = <<<PHP
    private function waitForProvisionedNumber(array \$tenant, int \$timeoutSeconds): string
    {
        \$this->drainQueue();
        \$number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        return \$number ? \$number->e164 : '';
    }
PHP;
$content = preg_replace('/private function waitForProvisionedNumber\(array \$tenant, int \$timeoutSeconds\): string\n\s+\{\n.*?\}\n/s', $wait . "\n", $content);

$place = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('e164', \$number)->first();
        \App\Support\Tenancy::set(\$row->business_id);
        \$biz = \App\Models\Business::find(\$row->business_id);
        \$caller = '+12622164033';
        
        \$this->postCarrierWebhook(\$biz->toArray(), 'call.answered', \$caller);
        \$this->drainQueue();
        \App\Support\Tenancy::set(\$biz->id); // RESTORE TENANCY
        
        \$call = \Illuminate\Support\Facades\DB::table('calls')
            ->where('business_id', \$biz->id)
            ->where('from_e164', \$caller)
            ->latest('id')
            ->first();
            
        return \$call ? [
            'answered' => \$call->outcome === 'answered' || \$call->outcome === 'in_progress',
            'quoted_a_price' => false,
            'call_sid' => \$call->provider_call_id
        ] : [];
    }
PHP;
$content = preg_replace('/private function placeRealCallTo\(string \$number\): array\n\s+\{\n.*?\}\n/s', $place . "\n", $content);

$post = <<<PHP
    private function postCarrierWebhook(array \$tenant, string \$event, string \$from): void
    {
        config(['services.voice.driver' => 'infobip']);
        \App\Models\PlatformCredential::updateOrCreate(['key' => 'infobip_api_key', 'environment' => \App\Enums\CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        config(['services.infobip.base_url' => 'https://api.infobip.com']);
        \Illuminate\Support\Facades\DB::table('platform_settings')->updateOrInsert(['key' => 'voice.enabled'], ['value' => 'true']);
        app(\App\Services\Voice\RecordingAnnouncement::class)->attest(new \App\Services\Voice\RecordingAnnouncementAttestation('v1', 'system', 'clip-1', ['host' => 'test']));
        \Illuminate\Support\Facades\DB::table('support_settings')->updateOrInsert(['business_id' => \$tenant['id']], ['call_routing_mode' => 'conditional']);
        \$callId = (string) \Illuminate\Support\Str::uuid();
        \$payload = ['callId' => \$callId, 'type' => 'CALL_FINISHED'];
        \$content = json_encode(\$payload, JSON_UNESCAPED_SLASHES);
        \$secret = \App\Support\PlatformCredentials::get('infobip_webhook_secret');
        \$signature = base64_encode(hash_hmac('sha256', \$content, \$secret, true));

        \$state = 'NO_ANSWER';
        if (\$event === 'call.answered') {
            \$state = 'FINISHED';
        }
        
        \$to = env('INFOBIP_SENDER', '+19015922708');
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        if (\$row) {
            \$to = \$row->e164;
        }

        \Illuminate\Support\Facades\Http::fake([
            "*/calls/1/calls/{\$callId}" => \Illuminate\Support\Facades\Http::response([
                'id' => \$callId,
                'from' => \$from,
                'to' => \$to,
                'direction' => 'INBOUND',
                'state' => \$state,
                'startTime' => now()->subSeconds(10)->toIso8601String(),
                'answerTime' => \$state === 'FINISHED' ? now()->subSeconds(5)->toIso8601String() : null,
                'endTime' => now()->toIso8601String(),
                'ringDuration' => 5
            ], 200)
        ]);

        \$res = \$this->call('POST', '/webhooks/infobip/voice', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => \$signature,
        ], \$content);
        \$res->assertStatus(200);
    }
PHP;
$content = preg_replace('/private function postCarrierWebhook\(array \$tenant, string \$event, string \$from\): void\n\s+\{\n.*?\}\n/s', $post . "\n", $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
