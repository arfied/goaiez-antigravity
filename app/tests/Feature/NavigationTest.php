<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NavigationTest extends TestCase
{
    public function test_eighteen_entries_in_order(): void
    {
        $features = require __DIR__ . '/../../config/features.php';
        $this->assertCount(18, $features['entries']);
    }

    public function test_no_deferred_in_navigation(): void
    {
        $features = require __DIR__ . '/../../config/features.php';
        $surfaces = require __DIR__ . '/../../config/surfaces.generated.php';
        
        $deferred = $features['deferred'];
        
        foreach (['tenant', 'operator', 'agency', 'tech'] as $surf) {
            foreach ($surfaces[$surf] as $group => $items) {
                foreach ($items as $item) {
                    $this->assertFalse(in_array($item['module'], $deferred), "Deferred module {$item['module']} found in navigation");
                }
            }
        }
    }

    public function test_unplaced_is_empty(): void
    {
        $surfaces = require __DIR__ . '/../../config/surfaces.generated.php';
        foreach (['tenant', 'operator', 'agency', 'tech'] as $surf) {
            $this->assertArrayNotHasKey('Unplaced', $surfaces[$surf]);
        }
    }
}
