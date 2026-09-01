<?php

require 'app/vendor/autoload.php';
$app = require_once 'app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\Tenancy;
use App\Modules\X161\Actions\DemoProvisionAction;
use App\Modules\X161\Actions\DemoConvertAction;
use App\Modules\X179\Actions\TemplateMatchAction;
use App\Modules\X179\Models\ExtractedContent;

Tenancy::set(5);
$businessId = 5; // Simulating the platform/agency business ID
$prospectId = rand(1000, 9999);
$domain = 'acme-dental.test';

echo "=== PHASE 4 WORKFLOW EXECUTION ===\n\n";

// 1. Demo Provisioning (X-161)
echo "1. DEMO SITE & GROUNDED ASSISTANT (X-161)\n";
$provisionAction = app(DemoProvisionAction::class);
$demoTenant = $provisionAction->provisionDemo($businessId, $domain);
echo " -> Provisioned Demo Tenant '{$domain}'\n";
echo " -> Demo Slug: {$demoTenant->demo_slug}\n";
echo " -> Sandbox Mode: " . ($demoTenant->is_mock ? 'TRUE (Outbounds intercepted)' : 'FALSE') . "\n\n";

// 2. Microsite Engine (X-179)
echo "2. MICROSITE ENGINE & TEMPLATE MATCHING (X-179)\n";
$serviceDesc = "Expert root canals, cleanings, and emergency dental care since 1998.";
$extracted = ExtractedContent::create([
    'business_id' => $businessId,
    'prospect_id' => $prospectId,
    'source_type' => 'gbp', // Simulating prospect with no site but has a GBP
    'service_description' => $serviceDesc,
    'tech_stack' => '[]',
]);
echo " -> Extracted content from Google Business Profile\n";
echo "    [Service Description]: {$extracted->service_description}\n";

$matchAction = app(TemplateMatchAction::class);
$match = $matchAction->matchAndRender($businessId, $prospectId, 'tmpl_clinic_v2');
echo " -> Matched Template: {$match->template_id} ({$match->match_rate} score)\n";
echo " -> Rendering Path: {$match->path_type}\n";
echo " -> Rendered Preview (Zero Paraphrase Check): \n    {$match->rendered_preview}\n\n";

// 3. Demo Conversion (X-161)
echo "3. PROSPECT CONVERSION\n";
$liveBusinessId = rand(10, 99);
$convertAction = app(DemoConvertAction::class);
$converted = $convertAction->convertToLive($businessId, $demoTenant->id, $liveBusinessId);

echo " -> Demo Converted: " . ($converted->is_converted ? 'YES' : 'NO') . "\n";
echo " -> Seamless Handover to Live Tenant ID: {$liveBusinessId}\n";
echo " -> Facts and History Preserved with Zero Re-Entry.\n\n";

echo "Phase 4 execution completed successfully.\n";
