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
            $shortPath = str_replace(base_path().'/', '', $file);
            $this->analyzeEmptyStateContent($content, $shortPath, $failures);
        }

        $this->assertEmpty($failures, "Found undeclared props in x-ui.empty-state calls:\n".implode("\n", $failures));
    }

    public function test_empty_state_lint_catches_multi_line_violation(): void
    {
        $fixture = <<<'HTML'
<div>
    <x-ui.empty-state
        title="Broken"
        class="mt-4">
        Slot content
    </x-ui.empty-state>
</div>
HTML;

        $failures = [];
        $this->analyzeEmptyStateContent($fixture, 'fixture', $failures);

        $this->assertCount(1, $failures);
        $this->assertSame('fixture:2 uses title= or description=. Use heading= for titles, and put descriptions in the component slot.', $failures[0]);
    }

    private function analyzeEmptyStateContent(string $content, string $shortPath, array &$failures): void
    {
        if (preg_match_all('/<x-ui\.empty-state\b[^>]*>/s', $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $match) {
                $tagContent = $match[0];
                $offset = $match[1];
                if (preg_match('/\btitle\s*=/', $tagContent) || preg_match('/\bdescription\s*=/', $tagContent)) {
                    $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $failures[] = "{$shortPath}:{$lineNumber} uses title= or description=. Use heading= for titles, and put descriptions in the component slot.";
                }
            }
        }
    }
}
