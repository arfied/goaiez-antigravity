<?php

declare(strict_types=1);

namespace App\Jobs\Webstudio;

use App\Models\WebstudioSite;
use App\Models\WebstudioTemplateProject;
use App\Services\Webstudio\WebstudioSites;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

final class CreateWebstudioSiteFromTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $siteId,
        public readonly int $businessId
    ) {}

    public function handle(WebstudioSites $sites): void
    {
        Tenancy::actingAs($this->businessId, function () {
            $site = WebstudioSite::find($this->siteId);
            if (! $site || $site->creation_status !== WebstudioSite::CREATING) {
                return;
            }

            $template = WebstudioTemplateProject::where('template_id', $site->template_id)->first();
            if (! $template) {
                $site->update([
                    'creation_status' => WebstudioSite::CREATION_FAILED,
                    'creation_message' => WebstudioSites::MESSAGES['creation_failed'],
                    'creation_error' => 'no template',
                ]);

                return;
            }

            if (! PlatformCredentials::has('webstudio_auth_secret')) {
                $site->update([
                    'creation_status' => WebstudioSite::CREATION_FAILED,
                    'creation_message' => WebstudioSites::MESSAGES['creation_failed'],
                    'creation_error' => 'no credential',
                ]);

                return;
            }

            $env = [
                'WS_BUILDER_ORIGIN' => config('site_clone.builder_origin'),
                'WS_AUTH_SECRET' => PlatformCredentials::get('webstudio_auth_secret'),
                'WS_INSECURE_TLS' => config('site_clone.builder_insecure_tls') ? '1' : '0',
                'WS_PROJECT_TITLE' => $site->title,
                'WS_CLONE_FROM' => $template->project_id,
                'HOME' => getenv('HOME'),
                'PATH' => getenv('PATH'),
            ];

            $process = new Process(['node', config('site_clone.project_script')], base_path('..'), $env, null, 120);
            $process->run();

            if ($process->getExitCode() === 0) {
                $output = $process->getOutput();
                $data = @json_decode(trim($output), true);
                if (is_array($data) && isset($data['projectId'], $data['token'])) {
                    $site->update([
                        'project_id' => $data['projectId'],
                        'editor_token' => $data['token'],
                        'creation_status' => WebstudioSite::READY,
                        'creation_message' => WebstudioSites::MESSAGES['ready'],
                        'creation_error' => null,
                    ]);
                } else {
                    $site->update([
                        'creation_status' => WebstudioSite::CREATION_FAILED,
                        'creation_message' => WebstudioSites::MESSAGES['creation_failed'],
                        'creation_error' => 'invalid output from script',
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
                    'creation_status' => WebstudioSite::CREATION_FAILED,
                    'creation_message' => WebstudioSites::MESSAGES['creation_failed'],
                    'creation_error' => $combined,
                ]);
            }
        });
    }
}
