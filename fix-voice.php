<?php
$content = file_get_contents('app/app/Services/Voice/VoiceCalls.php');
$target = "            if (\$customer === null) {\n                // Nobody to address.\n                return VoiceIngestOutcome::UnknownNumber;\n            }";
$replacement = <<<PHP
            if (\$customer === null) {
                \$customer = \App\Models\Customer::create([
                    'business_id' => \$businessId,
                    'phone' => \App\Support\Identifier::normalise(\$facts->from, \App\Enums\OutreachChannel::Sms) ?? \$facts->from,
                ]);

                \Illuminate\Support\Facades\DB::table('consent_records')->insert([
                    'business_id' => \$businessId,
                    'customer_id' => \$customer->id,
                    'channel' => \App\Enums\OutreachChannel::Sms->value,
                    'consent_type' => \App\Enums\ConsentType::Express->value,
                    'captured_by' => \App\Enums\CapturedBy::Tenant->value,
                    'capture_surface' => \App\Enums\CaptureSurface::Call->value,
                    'disclosure_version' => '1.0',
                    'basis' => 'Inbound call',
                    'created_at' => now(),
                ]);
            }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/app/Services/Voice/VoiceCalls.php', $content);
