<?php

namespace Tests\Feature\Architecture;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class UndefinedButtonClassTest extends TestCase
{
    public function test_no_blade_uses_the_undefined_btn_classes(): void
    {
        $hits = [];
        foreach ([base_path('resources/views'), base_path('app/Modules')] as $root) {
            foreach ((new Finder)->files()->in($root)->name('*.blade.php') as $file) {
                if (preg_match('/class="[^"]*\bbtn\b/', $file->getContents())) {
                    $hits[] = $file->getRelativePathname();
                }
            }
        }
        $this->assertSame([], $hits, 'These blades use `btn` classes that no stylesheet defines — use <x-ui.button>: '.implode(', ', $hits));
    }
}
