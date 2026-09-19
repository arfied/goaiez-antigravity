<?php

declare(strict_types=1);

namespace Tests\Modules\X195;

use App\Modules\X195\Actions\FlagSetAction;
use App\Modules\X195\Actions\MarketInstallAction;
use App\Modules\X195\Actions\MarketPublishAction;
use App\Modules\X195\Events\FlagChanged;
use App\Modules\X195\Events\ManifestInstalled;
use App\Modules\X195\Events\ManifestPublished;
use App\Modules\X195\Models\FeatureFlag;
use App\Modules\X195\Models\Install;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X195Test extends TestCase
{
    private MarketPublishAction $publishAction;

    private MarketInstallAction $installAction;

    private FlagSetAction $flagAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAction = new MarketPublishAction;
        $this->installAction = new MarketInstallAction;
        $this->flagAction = new FlagSetAction;
    }

    

    private function scanDirectory(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[$file->getPathname()] = $file->getMTime();
            }
        }
        ksort($files);

        return $files;
    }
}
