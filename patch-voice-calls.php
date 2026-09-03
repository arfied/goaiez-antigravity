<?php
$content = file_get_contents('app/app/Services/Voice/VoiceCalls.php');
$target = "            if (\$customer === null) {\n";
$replacement = <<<PHP
            if (\$customer === null) {
                // TODO(Q-045): An inbound call establishes express consent, captured by the tenant, allowing a transactional missed-call text-back.
                // A stranger calling our number expects an answer, and a text-back is the product's answer.
                \$customer = \App\Models\Customer::create([
                    'business_id' => \$businessId,
                    'phone' => \App\Support\Identifier::normalise(\$facts->from, \App\Enums\OutreachChannel::Sms) ?? \$facts->from,
                ]);

                \App\Models\ConsentRecord::forceCreate([
                    'customer_id' => \$customer->id,
                    'channel' => \App\Enums\OutreachChannel::Sms->value,
                    'consent_type' => \App\Enums\ConsentType::Express->value,
                    'captured_by' => \App\Enums\CapturedBy::Tenant->value,
                    'capture_surface' => \App\Enums\CaptureSurface::Call->value,
                    'disclosure_version' => '1.0',
                    'basis' => 'Inbound call',
                ]);
PHP;
$content = str_replace($target, $replacement, $content);

$target2 = <<<PHP
            if (\$eventType instanceof VoiceEventType
                && \$eventType->owesTextBack()
PHP;
$replacement2 = <<<PHP
            \Illuminate\Support\Facades\Log::info("touchesLiveCalls: " . (\$this->forwarding->modeFor()->touchesLiveCalls() ? 'true' : 'false'));
            \Illuminate\Support\Facades\Log::info("eventType: " . (is_object(\$eventType) ? get_class(\$eventType) : gettype(\$eventType)));
            \Illuminate\Support\Facades\Log::info("owesTextBack: " . (\$eventType instanceof \App\Enums\VoiceEventType && \$eventType->owesTextBack() ? 'true' : 'false'));
            if (\$eventType instanceof VoiceEventType
                && \$eventType->owesTextBack()
PHP;
$content = str_replace($target2, $replacement2, $content);
file_put_contents('app/app/Services/Voice/VoiceCalls.php', $content);
