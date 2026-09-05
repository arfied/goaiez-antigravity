<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixSampleStateCommand extends Command
{
    protected $signature = 'surfaces:fix-sample-state';

    protected $description = 'Move sample-state banner inside the root element';

    public function handle()
    {
        $files = glob(app_path('Modules/*/Ui/views/*.blade.php'));
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/^(<x-surface\.sample-state[^>]*>)\s*(<[a-z0-9\-]+[^>]*>)/i', $content, $matches)) {
                $content = preg_replace('/^(<x-surface\.sample-state[^>]*>)\s*(<[a-z0-9\-]+[^>]*>)\s*/i', "$2\n    $1\n", $content);
                file_put_contents($file, $content);
            }
        }

        return 0;
    }
}
