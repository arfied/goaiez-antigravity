<?php

declare(strict_types=1);

namespace App\Jobs\Webstudio;

use App\Models\WebstudioSite;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Modules\X157\Actions\StaticSiteDeployAction;
use App\Services\Webstudio\WebstudioSites;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class PublishWebstudioSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly int $siteId,
        public readonly int $businessId
    ) {}

    public function handle(WebstudioSites $sites, StaticSiteDeployAction $deploy, PlatformSiteAddressAction $address): void
    {
        Tenancy::actingAs($this->businessId, function () use ($deploy, $address) {
            $site = WebstudioSite::find($this->siteId);
            if (! $site || $site->publish_status !== WebstudioSite::PUBLISHING) {
                return;
            }

            $hash = StaticSiteDeployAction::newHash();
            $zone = $address->handle($this->businessId);

            $pubDir = rtrim((string) config('site_clone.root'), '/').'/'.$this->businessId.'/publish/'.$hash;

            $env = [
                'WS_SHARE_LINK' => WebstudioSites::editorUrlFor($site->project_id, $site->editor_token),
                'PUBLISH_BASE' => "/sites/{$this->businessId}/{$hash}/",
                'WS_INSECURE_TLS' => config('site_clone.builder_insecure_tls') ? '1' : '0',
                'HOME' => getenv('HOME'),
                'PATH' => getenv('PATH'),
            ];

            try {
                $process = new Process(['bash', config('site_clone.publisher'), $pubDir], base_path('..'), $env, null, 900);

                $process->run(function ($type, $buffer) use ($site) {
                    $lines = explode("\n", rtrim($buffer, "\n"));
                    foreach ($lines as $line) {
                        if (str_starts_with($line, '## step ')) {
                            $step = substr($line, 8);
                            // Some lines like 'done pages=1...' we just match by first word.
                            $stepName = explode(' ', $step)[0];
                            if (isset(WebstudioSites::MESSAGES[$stepName])) {
                                $site->update(['publish_message' => WebstudioSites::MESSAGES[$stepName]]);
                            }
                        }
                    }
                });

                $ok = $process->getExitCode() === 0 && is_file($pubDir.'/dist/client/index.html');

                if ($ok) {
                    $site->update(['publish_message' => WebstudioSites::MESSAGES['deploy']]);
                    $r = $deploy->handle($this->businessId, $zone->id, $pubDir.'/dist/client', $hash);
                    if ($r['status'] === 'deployed') {
                        $site->update([
                            'publish_status' => WebstudioSite::PUBLISHED,
                            'publish_message' => WebstudioSites::MESSAGES['published'],
                            'published_at' => now(),
                            'deployment_id' => $r['deployment_id'],
                            'deploy_hash' => $r['deploy_hash'],
                            'publish_error' => null,
                        ]);
                    } else {
                        $site->update([
                            'publish_status' => WebstudioSite::FAILED,
                            'publish_message' => WebstudioSites::MESSAGES['failed'],
                            'publish_error' => 'refused: '.($r['refusal_code'] ?? 'unknown'),
                        ]);
                    }
                } else {
                    $outAll = $process->getOutput();
                    $errAll = $process->getErrorOutput();
                    $combined = $errAll.$outAll;
                    if (strlen($combined) > 2000) {
                        $combined = substr($combined, -2000);
                    }
                    $site->update([
                        'publish_status' => WebstudioSite::FAILED,
                        'publish_message' => WebstudioSites::MESSAGES['failed'],
                        'publish_error' => $combined,
                    ]);
                }
            } finally {
                File::deleteDirectory($pubDir);
            }
        });
    }
}
