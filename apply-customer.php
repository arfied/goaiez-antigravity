<?php
$content = file_get_contents('app/app/Services/Voice/VoiceCalls.php');
$target = <<<PHP
            \$customer = \$this->contactFor(\$facts->from);

            \$attributes = [
PHP;
$replacement = <<<PHP
            \$customer = \$this->contactFor(\$facts->from);

            if (\$customer === null) {
                // TODO(Q-045): An inbound call establishes express consent, captured by the tenant, allowing a transactional missed-call text-back.
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

            \$attributes = [
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/app/Services/Voice/VoiceCalls.php', $content);
