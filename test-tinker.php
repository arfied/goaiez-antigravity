<?php
require __DIR__.'/app/vendor/autoload.php';
$app = require_once __DIR__.'/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenant = \App\Models\Business::withoutGlobalScopes()->first();
if (!$tenant) die("No tenant\n");

\App\Support\Tenancy::actingAs($tenant->id, function() use ($tenant) {
    \Illuminate\Support\Facades\DB::transaction(function() use ($tenant) {
        $customer = \App\Models\Customer::create([
            'business_id' => $tenant->id,
            'phone' => '+15551234599'
        ]);
        try {
            \App\Models\ConsentRecord::create([
                'customer_id' => $customer->id,
                'channel' => \App\Enums\OutreachChannel::Sms->value,
                'consent_type' => \App\Enums\ConsentType::Express->value,
                'captured_by' => \App\Enums\CapturedBy::Tenant->value,
                'capture_surface' => \App\Enums\CaptureSurface::Call->value,
                'disclosure_version' => '1.0',
                'basis' => 'Inbound call',
            ]);
            echo "Success!\n";
        } catch (\Throwable $e) {
            echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        }
        \Illuminate\Support\Facades\DB::rollBack();
    });
});
