<?php
$content = file_get_contents('app/app/Services/Voice/VoiceCalls.php');
$target = <<<PHP
                \App\Models\ConsentRecord::create([
                    'customer_id' => \$customer->id,
                    'channel' => \App\Enums\OutreachChannel::Sms->value,
                    'consent_type' => \App\Enums\ConsentType::Express->value,
                    'captured_by' => \App\Enums\CapturedBy::Tenant->value,
                    'capture_surface' => \App\Enums\CaptureSurface::Call->value,
                    'disclosure_version' => '1.0',
                    'basis' => 'Inbound call',
                ]);
PHP;
$replacement = <<<PHP
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
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/app/Services/Voice/VoiceCalls.php', $content);
