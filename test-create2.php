<?php
require __DIR__.'/app/vendor/autoload.php';
$app = require_once __DIR__.'/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\App\Support\Tenancy::actingAs(1, function () {
    \Illuminate\Support\Facades\DB::transaction(function () {
        try {
            $customer = \App\Models\Customer::create([
                'business_id' => 1,
                'phone' => '+15551234567'
            ]);

            $record = \App\Models\ConsentRecord::create([
                'customer_id' => $customer->id,
                'channel' => \App\Enums\OutreachChannel::Sms->value,
                'consent_type' => \App\Enums\ConsentType::Express->value,
                'captured_by' => \App\Enums\CapturedBy::Tenant->value,
                'capture_surface' => \App\Enums\CaptureSurface::Call->value,
                'disclosure_version' => '1.0',
                'basis' => 'Inbound call',
            ]);
            var_dump($record->id);
        } catch (\Throwable $e) {
            echo "Exception: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }
        \Illuminate\Support\Facades\DB::rollBack();
    });
});
