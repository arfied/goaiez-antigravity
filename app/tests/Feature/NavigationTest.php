<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class NavigationTest extends TestCase
{
    public function test_eighteen_entries_in_order(): void
    {
        $features = require __DIR__.'/../../config/features.php';
        $this->assertCount(18, $features['entries']);

        $surfaces = require __DIR__.'/../../config/surfaces.generated.php';
        $entries = [
            ['surface' => 'tenant', 'label' => 'Today'],
            ['surface' => 'tenant', 'label' => 'Inbox'],
            ['surface' => 'tenant', 'label' => 'Calls & Voice'],
            ['surface' => 'tenant', 'label' => 'Customers'],
            ['surface' => 'tenant', 'label' => 'Jobs & Field'],
            ['surface' => 'tenant', 'label' => 'Pricebook'],
            ['surface' => 'tenant', 'label' => 'Money'],
            ['surface' => 'tenant', 'label' => 'Reviews'],
            ['surface' => 'tenant', 'label' => 'Marketing'],
            ['surface' => 'tenant', 'label' => 'Website'],
            ['surface' => 'tenant', 'label' => 'Visibility'],
            ['surface' => 'tenant', 'label' => 'Prospecting'],
            ['surface' => 'tenant', 'label' => 'Visitors & Attribution'],
            ['surface' => 'tenant', 'label' => 'Automations & Assistant'],
            ['surface' => 'tenant', 'label' => 'Settings'],
            ['surface' => 'other', 'label' => 'Operator console'],
            ['surface' => 'other', 'label' => 'Agency console'],
            ['surface' => 'other', 'label' => 'Technician mobile'],
        ];

        $expectedTenantKeys = [];
        foreach ($entries as $entry) {
            if ($entry['surface'] === 'tenant') {
                $expectedTenantKeys[] = $entry['label'];
            }
        }

        $actualTenantKeys = array_values(array_intersect(array_keys($surfaces['tenant']), $expectedTenantKeys));
        $this->assertSame($expectedTenantKeys, $actualTenantKeys);
    }

    public function test_no_deferred_in_navigation(): void
    {
        $features = require __DIR__.'/../../config/features.php';
        $surfaces = require __DIR__.'/../../config/surfaces.generated.php';

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
        $surfaces = require __DIR__.'/../../config/surfaces.generated.php';
        foreach (['tenant', 'operator', 'agency', 'tech'] as $surf) {
            $this->assertArrayNotHasKey('Unplaced', $surfaces[$surf]);
        }
    }

    public function test_no_other_surface_group_in_tenant_navigation(): void
    {
        $features = require __DIR__.'/../../config/features.php';
        $surfaces = require __DIR__.'/../../config/surfaces.generated.php';

        $otherGroups = [];
        foreach ($features['entries'] as $entry) {
            if (($entry['surface'] ?? 'tenant') === 'other') {
                $otherGroups[] = $entry['label'];
            }
        }

        foreach ($otherGroups as $group) {
            $this->assertArrayNotHasKey($group, $surfaces['tenant'], "Group '{$group}' marked as 'other' should not be in 'tenant' surface.");
        }
    }

    public function test_operator_entries_in_order(): void
    {
        $surfaces = require __DIR__.'/../../config/surfaces.generated.php';
        $entries = [
            ['surface' => 'tenant', 'label' => 'Today'],
            ['surface' => 'tenant', 'label' => 'Inbox'],
            ['surface' => 'tenant', 'label' => 'Calls & Voice'],
            ['surface' => 'tenant', 'label' => 'Customers'],
            ['surface' => 'tenant', 'label' => 'Jobs & Field'],
            ['surface' => 'tenant', 'label' => 'Pricebook'],
            ['surface' => 'tenant', 'label' => 'Money'],
            ['surface' => 'tenant', 'label' => 'Reviews'],
            ['surface' => 'tenant', 'label' => 'Marketing'],
            ['surface' => 'tenant', 'label' => 'Website'],
            ['surface' => 'tenant', 'label' => 'Visibility'],
            ['surface' => 'tenant', 'label' => 'Prospecting'],
            ['surface' => 'tenant', 'label' => 'Visitors & Attribution'],
            ['surface' => 'tenant', 'label' => 'Automations & Assistant'],
            ['surface' => 'tenant', 'label' => 'Settings'],
            ['surface' => 'other', 'label' => 'Operator console'],
            ['surface' => 'other', 'label' => 'Agency console'],
            ['surface' => 'other', 'label' => 'Technician mobile'],
        ];

        $expectedOperatorKeys = [];
        foreach ($entries as $entry) {
            if (isset($surfaces['operator'][$entry['label']])) {
                $expectedOperatorKeys[] = $entry['label'];
            }
        }

        $actualOperatorKeys = array_keys($surfaces['operator']);
        $this->assertSame($expectedOperatorKeys, $actualOperatorKeys);
    }
}
