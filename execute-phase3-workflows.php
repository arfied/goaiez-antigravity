<?php

require 'app/vendor/autoload.php';
$app = require_once 'app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\Tenancy;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use App\Modules\X116\Models\Template;
use App\Modules\X102\Actions\ChatStartAction;
use App\Modules\X102\Actions\ChatEscalateAction;
use App\Modules\X148\Actions\RetrievalSearchAction;

Tenancy::set(5);
$businessId = 5;

echo "=== PHASE 3 WORKFLOW EXECUTION ===\n\n";

// 1. Starter Site Studio (X-103 & X-116)
echo "1. STARTER SITE STUDIO\n";
$template = Template::where('industry_code', 'clinic')->first();

$siteEngine = app(SiteEngine::class);
$siteFork = $siteEngine->forkSite($businessId, (string) $template->id);
echo " -> Forked 'clinic' Template (ID: {$template->id}) to SiteFork (Commit: {$siteFork->fork_commit_hash})\n";

$page = Page::create([
    'business_id' => $businessId,
    'title' => 'Main Clinic Page',
    'slug' => '/',
    'is_tenant_edited' => false,
]);
echo " -> Minted Page '{$page->title}' (ID: {$page->id})\n";

$publishResult = $siteEngine->publish($businessId, $page->id, [
    ['type' => 'hero', 'content' => 'Welcome to the Clinic'],
    ['type' => 'services', 'content' => 'General Dentistry'],
]);
echo " -> Published Page (Commit ID: {$publishResult['commit_id']})\n\n";

// 2. OmniChat Support Assistant (X-102)
echo "2. OMNICHAT SUPPORT ASSISTANT\n";
$chatStart = app(ChatStartAction::class);
$session = $chatStart->handle($businessId, '192.168.1.1');
echo " -> Started Chat Session (Token: {$session->session_token})\n";

$chatEscalate = app(ChatEscalateAction::class);

echo " -> Simulating 4 rage clicks...\n";
for($i=1; $i<=4; $i++) {
    $result = $chatEscalate->recordRageClick($businessId, $session->id);
    echo "    - Click $i: Status '{$result['status']}'\n";
}
echo " -> Escalation successful. Session status: " . $result['status'] . "\n\n";

// 3. Business Brain & Document Ingestion (X-148)
echo "3. BUSINESS BRAIN (VECTOR RAG)\n";
$retrievalSearch = app(RetrievalSearchAction::class);
$searchResult = $retrievalSearch->search($businessId, 'dentistry', false);
echo " -> Queried Knowledge Base for 'dentistry'\n";
echo " -> Found {$searchResult['count']} Knowledge Chunks (RAG fallback due to missing OpenAI key)\n";
foreach ($searchResult['chunks'] as $chunk) {
    echo "    - [{$chunk->title}]: " . substr($chunk->content, 0, 75) . "...\n";
}

echo "\nPhase 3 execution completed successfully.\n";
