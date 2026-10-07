<?php

declare(strict_types=1);

namespace App\Services\Webstudio;

use App\Jobs\Webstudio\CreateWebstudioSiteFromTemplateJob;
use App\Jobs\Webstudio\PublishWebstudioSiteJob;
use App\Models\SiteCloneJob;
use App\Models\WebstudioSite;
use App\Models\WebstudioTemplateProject;
use Illuminate\Support\Collection;

final class WebstudioSites
{
    public const MESSAGES = [
        'never' => 'Not published yet', 'queued' => 'Publishing…', 'cache' => 'Publishing…', 'workdir' => 'Publishing…',
        'link' => 'Connecting to the editor…', 'sync' => 'Fetching your latest changes…', 'template' => 'Preparing the build…',
        'scaffold' => 'Preparing the build…', 'deps' => 'Preparing the build…', 'build' => 'Building your site…',
        'flatten' => 'Building your site…', 'deploy' => 'Putting your site online…', 'published' => 'Published.',
        'failed' => 'Publishing failed. Please try again.', 'interrupted' => 'Publishing was interrupted. Please try again.',
        'creating' => 'Preparing your site in the editor…', 'ready' => 'Ready', 'creation_failed' => 'We could not prepare the site. Please try again.',
    ];

    public const REFUSALS = [
        'no_site' => 'That site was not found.',
        'no_project' => 'This site has no editor project yet.',
        'already_publishing' => 'This site is already being published.',
        'no_template' => 'That template is not available in the editor yet.',
        'already_creating' => 'A site is already being prepared.',
    ];

    public static function editorUrlFor(string $projectId, string $token): string
    {
        $origin = rtrim(config('site_clone.builder_origin', 'https://wstd.dev:5174'), '/');
        $parsed = parse_url($origin);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        return $scheme.'://p-'.$projectId.'.'.$host.$port.'/?authToken='.$token;
    }

    public function fromCloneJob(SiteCloneJob $job): ?WebstudioSite
    {
        if ($job->status !== SiteCloneJob::DONE || $job->webstudio_project_id === null || $job->editor_token === null) {
            return null;
        }

        return WebstudioSite::updateOrCreate(
            ['project_id' => $job->webstudio_project_id],
            [
                'business_id' => $job->business_id,
                'source' => WebstudioSite::SOURCE_CLONE,
                'site_clone_job_id' => $job->id,
                'title' => $job->host,
                'editor_token' => $job->editor_token,
            ]
        );
    }

    public function fromTemplate(int $businessId, string $templateId, string $title): array
    {
        $template = WebstudioTemplateProject::where('template_id', $templateId)->first();
        if (! $template) {
            return ['status' => 'refused', 'reason' => 'no_template'];
        }

        $existing = WebstudioSite::where('business_id', $businessId)
            ->where('creation_status', WebstudioSite::CREATING)
            ->first();
        if ($existing) {
            return ['status' => 'refused', 'reason' => 'already_creating'];
        }

        $site = WebstudioSite::create([
            'business_id' => $businessId,
            'source' => WebstudioSite::SOURCE_TEMPLATE,
            'template_id' => $templateId,
            'title' => $title,
            'creation_status' => WebstudioSite::CREATING,
            'creation_message' => self::MESSAGES['creating'],
        ]);

        CreateWebstudioSiteFromTemplateJob::dispatch($site->id, $businessId)->onQueue('clone');

        return ['status' => 'queued', 'site_id' => $site->id];
    }

    public function all(int $businessId): Collection
    {
        return WebstudioSite::where('business_id', $businessId)->latest('id')->get();
    }

    public function requestPublish(int $businessId, int $siteId): array
    {
        $site = WebstudioSite::where('business_id', $businessId)->find($siteId);

        if (! $site) {
            return ['status' => 'refused', 'reason' => 'no_site'];
        }

        if ($site->publish_status === WebstudioSite::PUBLISHING) {
            return ['status' => 'refused', 'reason' => 'already_publishing'];
        }

        $site->update([
            'publish_status' => WebstudioSite::PUBLISHING,
            'publish_message' => self::MESSAGES['queued'],
            'publish_error' => null,
            'publish_started_at' => now(),
        ]);

        PublishWebstudioSiteJob::dispatch($site->id, $businessId)->onQueue('clone');

        return ['status' => 'queued', 'site_id' => $site->id];
    }

    public function recoverStale(int $businessId): void
    {
        WebstudioSite::where('business_id', $businessId)
            ->where('publish_status', WebstudioSite::PUBLISHING)
            ->where('publish_started_at', '<', now()->subMinutes(20))
            ->update([
                'publish_status' => WebstudioSite::FAILED,
                'publish_message' => self::MESSAGES['interrupted'],
            ]);

        WebstudioSite::where('business_id', $businessId)
            ->where('creation_status', WebstudioSite::CREATING)
            ->where('created_at', '<', now()->subMinutes(20))
            ->update([
                'creation_status' => WebstudioSite::CREATION_FAILED,
                'creation_message' => self::MESSAGES['creation_failed'],
            ]);
    }

    public function editorUrl(WebstudioSite $site): ?string
    {
        if ($site->project_id === null || $site->editor_token === null) {
            return null;
        }

        return self::editorUrlFor($site->project_id, $site->editor_token);
    }

    public function siteUrl(WebstudioSite $site): ?string
    {
        if ($site->publish_status === WebstudioSite::PUBLISHED && $site->deploy_hash) {
            return route('x-157.site', ['business' => $site->business_id, 'deploy_hash' => $site->deploy_hash]);
        }

        return null;
    }

    public function ownerSentence(string $reason): string
    {
        return self::REFUSALS[$reason] ?? 'Unknown error.';
    }
}
