<?php

namespace App\Console\Commands;

use App\Models\WebstudioTemplateProject;
use App\Modules\X103\Domain\SiteTemplates;
use App\Support\PlatformCredentials;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WebstudioImportTemplates extends Command
{
    protected $signature = 'webstudio:import-templates {--map= : record an existing map.json instead of running the import} {--work= : work directory}';

    protected $description = 'Render the site templates and import each as a Webstudio template project, recording the project ids';

    public function handle(): int
    {
        if ($this->option('map')) {
            $mapFile = $this->option('map');
            if (! file_exists($mapFile)) {
                $this->error("Map file not found: {$mapFile}");

                return 1;
            }

            $json = json_decode(file_get_contents($mapFile), true);
            if (! is_array($json)) {
                $this->error('Invalid JSON in map file');

                return 1;
            }

            $unknownIds = [];
            foreach ($json as $templateId => $data) {
                if (! array_key_exists($templateId, SiteTemplates::TEMPLATES)) {
                    $unknownIds[] = $templateId;
                }
            }

            if (! empty($unknownIds)) {
                $this->error('Unknown template ids: '.implode(', ', $unknownIds));

                return 1;
            }

            $count = 0;
            foreach ($json as $templateId => $data) {
                WebstudioTemplateProject::updateOrCreate(
                    ['template_id' => $templateId],
                    [
                        'project_id' => $data['projectId'],
                        'label' => $data['label'],
                        'builder_origin' => config('site_clone.builder_origin'),
                        'imported_at' => $data['imported_at'] ?? now(),
                    ]
                );
                $count++;
            }

            $this->info("recorded {$count} template projects");

            return 0;
        }

        if (! PlatformCredentials::has('webstudio_auth_secret')) {
            $this->error('Missing webstudio_auth_secret credential');

            return 1;
        }

        $work = $this->option('work') ?: storage_path('app/private/webstudio/import-'.now()->format('Ymd-His'));
        if (! is_dir($work)) {
            mkdir($work, 0755, true);
        }

        $this->call('webstudio:render-templates', ['--out' => "{$work}/templates"]);

        $env = [
            'WS_AUTH_SECRET' => PlatformCredentials::get('webstudio_auth_secret'),
            'WS_BUILDER_ORIGIN' => config('site_clone.builder_origin'),
            'WS_INSECURE_TLS' => config('site_clone.builder_insecure_tls') ? '1' : '0',
            'HOME' => getenv('HOME'),
            'PATH' => getenv('PATH'),
        ];

        $process = new Process(
            ['bash', base_path('../webstudio-bridge/bin/templates-import.sh'), "{$work}/templates", "{$work}/work", "{$work}/map.json"],
            base_path('..'),
            $env,
            null,
            3600
        );

        $process->run(function ($type, $buffer) {
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
                if (str_starts_with($line, '## step')) {
                    $this->info($line);
                }
            }
        });

        if (! $process->isSuccessful()) {
            $output = $process->getErrorOutput().$process->getOutput();
            if (strlen($output) > 2000) {
                $output = substr($output, -2000);
            }
            $this->error("Import failed:\n{$output}");

            return 1;
        }

        $this->call('webstudio:import-templates', ['--map' => "{$work}/map.json"]);

        return 0;
    }
}
