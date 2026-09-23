<?php

namespace Tests\Modules\X157;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EdgeProvisionRefusalTest extends TestCase
{
    public function test_edge_provisioning_is_never_reachable_from_production_code(): void
    {
        $appPath = base_path('app');
        $modulesPath = $appPath.'/Modules';

        $this->assertDirectoryExists($modulesPath);

        $files = File::allFiles($appPath);
        $foundInFiles = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'EdgeProvisionAction')) {
                    $foundInFiles[] = 'app/app/'.$file->getRelativePathname();
                }
            }
        }

        $expected = [
            'app/app/Modules/X-103/Ui/SiteBuild.php',
            'app/app/Modules/X-157/Actions/EdgeProvisionAction.php',
        ];

        $failureMessage = 'EdgeProvisionAction mints zone_id and ssl_certificate_id with Str::random() and takes has_valid_ssl as an argument, so any production caller would make J11\'s ssl element green off a fabricated certificate.';

        sort($expected);
        sort($foundInFiles);

        $this->assertEquals($expected, $foundInFiles, $failureMessage);
    }
}
