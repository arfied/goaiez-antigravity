<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVariant;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageVariantStartAction
{
    public function __construct(
        private EdgeDeployAction $edgeDeployAction,
        private LatestDeploymentForPageAction $latestDeploymentForPageAction
    ) {}

    public function handle(int $businessId, int $pageId, string $variantHeadline): array
    {
        if (PageVariant::where('business_id', $businessId)->where('page_id', $pageId)->where('status', 'running')->exists()) {
            return ['status' => 'refused', 'reason' => 'variant_running'];
        }

        $page = Page::where('business_id', $businessId)->find($pageId);
        if (! $page || ! $page->is_published) {
            return ['status' => 'refused', 'reason' => 'not_deployed'];
        }

        $controlDeploy = $this->latestDeploymentForPageAction->handle($businessId, $pageId);
        if (! $controlDeploy) {
            return ['status' => 'refused', 'reason' => 'not_deployed'];
        }

        if ($page->is_tenant_edited) {
            return ['status' => 'refused', 'reason' => 'tenant_wording'];
        }

        $controlVersion = PageVersion::where('business_id', $businessId)->find($page->current_version_id);
        if (! $controlVersion) {
            return ['status' => 'refused', 'reason' => 'not_deployed'];
        }

        $blocks = $controlVersion->content_blocks ?? [];
        $heroIndex = -1;
        $controlHeadline = '';
        foreach ($blocks as $i => $block) {
            if (($block['type'] ?? '') === 'hero') {
                $heroIndex = $i;
                $controlHeadline = $block['headline'] ?? '';
                break;
            }
        }

        if ($heroIndex === -1) {
            return ['status' => 'refused', 'reason' => 'no_hero'];
        }

        $variantHeadline = trim($variantHeadline);
        if ($variantHeadline === '' || mb_strlen($variantHeadline) > 120) {
            return ['status' => 'refused', 'reason' => 'bad_headline'];
        }

        if ($variantHeadline === $controlHeadline) {
            return ['status' => 'refused', 'reason' => 'same_as_control'];
        }

        $businessName = DB::table('businesses')->where('id', $businessId)->value('name');
        if ($variantHeadline === $businessName) {
            return ['status' => 'refused', 'reason' => 'brand_name'];
        }

        $variantBlocks = $blocks;
        $variantBlocks[$heroIndex]['headline'] = $variantHeadline;

        $variantId = PageVariant::insertGetId([
            'business_id' => $businessId,
            'page_id' => $pageId,
            'control_headline' => $controlHeadline,
            'variant_headline' => $variantHeadline,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitId = 'commit_'.Str::random(16);

        PageVersion::create([
            'business_id' => $businessId,
            'page_id' => $pageId,
            'commit_id' => $commitId,
            'content_blocks' => $variantBlocks,
            'pixel_installed' => $controlVersion->pixel_installed,
            'chat_installed' => $controlVersion->chat_installed,
            'form_capture_installed' => $controlVersion->form_capture_installed,
            'dni_installed' => $controlVersion->dni_installed,
            'seo_tags_installed' => $controlVersion->seo_tags_installed,
            'schema_installed' => $controlVersion->schema_installed,
            'ssl_enabled' => $controlVersion->ssl_enabled,
            'ssl_installed' => $controlVersion->ssl_installed,
        ]);

        try {
            $deployResult = $this->edgeDeployAction->handle(
                businessId: $businessId,
                edgeZoneId: $controlDeploy->edge_zone_id,
                measuredTtfbMs: $controlDeploy->measured_ttfb_ms ?? 120,
                speedBudgetMs: $controlDeploy->speed_budget_ms ?? 300,
                pageId: $pageId,
                commitId: $commitId,
                businessName: $businessName,
                pageVariantId: $variantId,
            );
        } catch (\Throwable $e) {
            PageVariant::where('id', $variantId)->update(['status' => 'stopped']);
            throw $e;
        }

        if (($deployResult['status'] ?? '') !== 'deployed') {
            PageVariant::where('id', $variantId)->update(['status' => 'stopped']);

            // If it is just a refusal and didn't throw, we can either return refusal or throw.
            // The spec says "on a deploy exception, mark the row stopped and rethrow", not sure if it means result or Exception.
            // But we should return a failed start if it wasn't deployed.
            return ['status' => 'refused', 'reason' => 'deploy_failed'];
        }

        $variantDeployHash = $deployResult['deploy_hash'];

        PageVariant::where('id', $variantId)->update([
            'control_deploy_hash' => $controlDeploy->deploy_hash,
            'variant_deploy_hash' => $variantDeployHash,
            'control_commit_id' => $controlVersion->commit_id,
            'variant_commit_id' => $commitId,
        ]);

        return [
            'status' => 'started',
            'variant_id' => $variantId,
            'variant_hash' => $variantDeployHash,
        ];
    }
}
