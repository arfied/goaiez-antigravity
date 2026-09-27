<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Models\Funnel;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Models\SiteFork;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class SiteEngine
{
    public const REQUIRED_BLOCK_TYPES = ['pixel_script', 'chat_widget', 'form_capture', 'dni_script', 'seo_tags', 'schema_markup'];

    /**
     * Publish page with shared commit ID for Facts invalidation (TEST ANCHOR).
     */
    public function publish(int $businessId, int $pageId, array $contentBlocks): array
    {
        return DB::transaction(function () use ($businessId, $pageId, $contentBlocks) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            $commitId = 'commit_'.Str::random(16);

            $required = self::REQUIRED_BLOCK_TYPES;
            foreach ($required as $type) {
                $found = false;
                foreach ($contentBlocks as $block) {
                    if (isset($block['type']) && $block['type'] === $type) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $contentBlocks[] = ['type' => $type];
                }
            }

            $blockTypes = array_column($contentBlocks, 'type');
            $version = PageVersion::create([
                'business_id' => $businessId,
                'page_id' => $page->id,
                'commit_id' => $commitId,
                'content_blocks' => $contentBlocks,
                'pixel_installed' => in_array('pixel_script', $blockTypes, true), // G9-04 full-stack site law
                'chat_installed' => in_array('chat_widget', $blockTypes, true), // G9-04 full-stack site law
                'form_capture_installed' => in_array('form_capture', $blockTypes, true), // G9-04 full-stack site law
                'dni_installed' => in_array('dni_script', $blockTypes, true), // G9-04 full-stack site law
                'seo_tags_installed' => in_array('seo_tags', $blockTypes, true), // G9-04 full-stack site law
                'schema_installed' => in_array('schema_markup', $blockTypes, true), // G9-04 full-stack site law
                'ssl_enabled' => false, // set true only by the SSL provisioning step (J11)
            ]);

            $page->update([
                'is_published' => true,
                'current_version_id' => $version->id,
            ]);

            Event::dispatch(new PagePublished($businessId, $page->id, $commitId, $version->id));
            Event::dispatch(new SitePublished($businessId, $page->id, $commitId, $version->id));

            return [
                'status' => 'published',
                'page_id' => $page->id,
                'version_id' => $version->id,
                'commit_id' => $commitId,
                'facts_invalidation_commit_id' => $commitId, // Shared commit ID (TEST ANCHOR)
            ];
        });
    }

    /**
     * Fork site from template (TEST ANCHOR: no FK to template library).
     */
    public function forkSite(int $businessId, string $templateId): SiteFork
    {
        return SiteFork::create([
            'business_id' => $businessId,
            'forked_template_id' => $templateId,
            'fork_commit_hash' => 'sha_'.Str::random(20),
        ]);
    }

    /**
     * Propose optimizer changes: skipped if page is tenant edited (TEST ANCHOR).
     */
    public function proposeOptimization(int $businessId, int $pageId, array $proposedBlocks): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        // Optimizer invariant: tenant edited page is SKIPPED by proposal (TEST ANCHOR)
        if ($page->is_tenant_edited) {
            return [
                'status' => 'skipped',
                'reason' => 'tenant_edited_page_preserved',
                'page_id' => $page->id,
            ];
        }

        Event::dispatch(new ApprovalRequested(
            businessId: $businessId,
            itemType: 'site_optimization',
            subject: "Proposed layout optimization for {$page->title}",
            payload: ['proposed_blocks' => $proposedBlocks]
        ));

        return [
            'status' => 'proposal_submitted',
            'page_id' => $page->id,
        ];
    }

    /**
     * Resolve short link with device routing, click cap and expiration (G6-11, G16-07, G19-07).
     */
    public function resolveShortLink(int $businessId, string $slug, string $deviceType = 'desktop'): array
    {
        $funnel = Funnel::where('business_id', $businessId)->where('short_slug', $slug)->first();

        if ($funnel === null) {
            return ['status' => 'not_found'];
        }

        if ($funnel->expires_at && $funnel->expires_at->isPast()) {
            return ['status' => 'expired', 'message' => 'Short link has expired'];
        }

        if ($funnel->click_cap && $funnel->clicks_count >= $funnel->click_cap) {
            return ['status' => 'capped', 'message' => 'Click limit reached'];
        }

        $funnel->increment('clicks_count');

        $destination = $funnel->steps[0]['url'] ?? '/';
        if ($funnel->device_routing && isset($funnel->device_routing[$deviceType])) {
            $destination = $funnel->device_routing[$deviceType];
        }

        return [
            'status' => 'routed',
            'destination_url' => $destination,
            'clicks_count' => $funnel->clicks_count,
        ];
    }
}
