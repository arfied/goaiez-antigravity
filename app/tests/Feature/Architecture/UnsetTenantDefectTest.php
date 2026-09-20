<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class UnsetTenantDefectTest extends TestCase
{
    public function test_unset_tenant_defect_count_is_pinned(): void
    {
        $count = 0;
        $files = File::glob(base_path('app/Modules/*/Ui/*.php'));

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'public int $businessId') && !str_contains($content, 'Tenancy::id(') && !str_contains($content, 'Tenancy::idOrFail(')) {
                $count++;
            }
        }

        $this->assertEquals(
            17,
            $count,
            'If it went UP, a new screen shipped that renders empty on a real GET. If it went DOWN, one was fixed — lower the number and record it.'
        );
    }
}
