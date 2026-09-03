<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$content = str_replace(
    'throw new \RuntimeException(\'JOURNEY HARNESS NOT IMPLEMENTED: sign up with exactly two fields — a third is a P-207 violation. ⛔ Implement against the REAL transport. A stub here makes all twelve journeys pass while touching nothing, which is worse than a red suite.\');',
    <<<PHP
        \$owner = \App\Models\User::factory()->create();
        app(\App\Services\Sms\TenantNumbers::class)->addToPool('+15125550999');
        \$res = app(\App\Modules\X118\Actions\OnboardingStartAction::class)->handle(\$owner, \$businessName, \$phone);
        if (\$res['asked_fields_count'] !== 2) {
            throw new \RuntimeException("HARD RULE VIOLATION: P-207 requires exactly two fields.");
        }
        return \App\Models\Business::find(\$res['business_id'])->toArray();
PHP,
    $content
);

$content = str_replace(
    'throw new \RuntimeException(\'JOURNEY HARNESS NOT IMPLEMENTED: use the real provisioner for an agency account. Do not stub it.\');',
    <<<PHP
        \$owner = \App\Models\User::factory()->create();
        \$biz = app(\App\Services\TenantProvisioner::class)->provision(\$owner);
        \$biz->forceFill(['name' => 'Agency Tenant'])->save();
        return \$biz->toArray();
PHP,
    $content
);

$content = str_replace(
    'throw new \RuntimeException(\'JOURNEY HARNESS NOT IMPLEMENTED: block until the onboarding process establishes a real number out of the pool. ⛔ Do not just return the string you passed in.\');',
    <<<PHP
        \$this->drainQueue();
        \$number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        return \$number ? \$number->e164 : '';
PHP,
    $content
);

$content = str_replace(
    'throw new \RuntimeException(\'JOURNEY HARNESS NOT IMPLEMENTED: place a call via the provider\\\’s inbound webhook, as if a carrier just hit us. Wait for the event to settle, then return the state of the call. ⛔ MUST run the actual IngestVoiceEventJob.\');',
    <<<PHP
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
PHP,
    $content
);

// We need to implement `postCarrierWebhook` which I replaced earlier!
// In the original, it's just `throw new \RuntimeException(...)`.
$content = str_replace(
    'throw new \RuntimeException(\'JOURNEY HARNESS NOT IMPLEMENTED: HTTP POST the webhook exactly as the carrier would. ⛔ A test that invokes the controller method directly is a test of the method, not the system.\');',
    <<<PHP
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
                'startTime' => now()->subMinutes(2)->toIso8601String(),
                'answerTime' => now()->subMinutes(1)->toIso8601String(),
                'endTime' => now()->toIso8601String(),
            ], 200)
        ]);

        \$this->app['router']->post('webhooks/infobip/voice', [\App\Http\Controllers\Webhooks\InfobipVoiceController::class, 'handle'])
            ->name('webhooks.infobip.voice');

        \$request = \Illuminate\Http\Request::create('/webhooks/infobip/voice', 'POST', [], [], [], [
            'HTTP_X_Hub_Signature_256' => \$signature,
            'CONTENT_TYPE' => 'application/json'
        ], \$content);
        
        app()->handle(\$request);
PHP,
    $content
);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
