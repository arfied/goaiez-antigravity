<?php

declare(strict_types=1);

namespace Tests\Modules\X173;

use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Ui\ConflictsListView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConflictsListScreenTest extends TestCase
{
    public function test_conflicts_list_resolve()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $conflict = AccountingSyncConflict::create([
            'business_id' => $biz->id,
            'transaction_ref' => 'TX-1',
            'confidence_rate' => 0.5,
            'assigned_category' => 'uncategorised',
            'status' => 'open'
        ]);

        Livewire::test(ConflictsListView::class)
            ->assertOk()
            ->set("resolutions.{$conflict->id}", 'uncategorised')
            ->call('resolve', $conflict->id)
            ->assertSee('is not a resolution')
            ->set("resolutions.{$conflict->id}", 'software')
            ->call('resolve', $conflict->id)
            ->assertSee('resolved')
            ->set("resolutions.{$conflict->id}", 'hardware')
            ->call('resolve', $conflict->id)
            ->assertSee('a resolved row is not overwritten');

        $this->assertSame('software', $conflict->fresh()->assigned_category);
        $this->assertSame('resolved', $conflict->fresh()->status);
    }
}
