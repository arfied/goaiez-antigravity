<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class EmptyStateContractTest extends TestCase
{
    public function test_empty_state_call_sites_do_not_use_undeclared_props(): void
    {
        $directories = [
            base_path('app/Modules'),
            base_path('resources/views'),
        ];

        $files = [];
        foreach ($directories as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $failures = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                if (str_contains($line, '<x-ui.empty-state')) {
                    if (preg_match('/\btitle=/', $line) || preg_match('/\bdescription=/', $line)) {
                        $lineNumber = $index + 1;
                        $shortPath = str_replace(base_path().'/', '', $file);
                        $failures[] = "{$shortPath}:{$lineNumber} uses title= or description=. Use heading= for titles, and put descriptions in the component slot.";
                    }
                }
            }
        }

        $this->assertEmpty($failures, "Found undeclared props in x-ui.empty-state calls:\n".implode("\n", $failures));
    }
}
