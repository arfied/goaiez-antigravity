<?php

declare(strict_types=1);

namespace Tests\Modules\X184;

use App\Modules\X184\Models\ContentPlan;
use App\Modules\X184\Models\PlanItem;
use App\Modules\X184\Ui\ContentWeek;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ContentWeekTest extends TestCase
{
    use DatabaseTransactions;

    public function test_renders_content_week(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Content Tenant']);
        $foreignBiz = TestCase::provisionTenant(['name' => 'Foreign Tenant']);

        Tenancy::set((int) $biz->id);

        $plan = ContentPlan::create([
            'business_id' => $biz->id,
            'week_label' => 'VALID TENANT WEEK',
            'posts_per_week_cadence' => 3,
            'is_cadence_approved' => false,
        ]);

        $item = PlanItem::create([
            'business_id' => $biz->id,
            'plan_id' => $plan->id,
            'channel' => 'Facebook',
            'topic_theme' => 'Holiday Special',
            'source_event' => 'transcript.captured',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'is_scheduled' => false,
        ]);

        $item2 = PlanItem::create([
            'business_id' => $biz->id,
            'plan_id' => $plan->id,
            'channel' => 'Instagram',
            'topic_theme' => 'Winter Updates',
            'source_event' => 'season.turned',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'is_scheduled' => false,
        ]);

        Tenancy::set((int) $foreignBiz->id);
        ContentPlan::create([
            'business_id' => $foreignBiz->id,
            'week_label' => 'FOREIGN TENANT WEEK',
            'posts_per_week_cadence' => 5,
            'is_cadence_approved' => false,
        ]);

        Tenancy::set((int) $biz->id);

        Livewire::test(ContentWeek::class, ['businessId' => $biz->id])
            ->assertSee('VALID TENANT WEEK')
            ->assertSee('transcript.captured')
            ->assertSee('season.turned')
            ->assertDontSee('FOREIGN TENANT WEEK')
            ->call('approveCadence', $plan->id)
            ->assertSee('Approved')
            ->call('scheduleItem', $item->id)
            ->assertSee('Planned');

        $this->assertTrue($plan->fresh()->is_cadence_approved);
        $this->assertTrue($item->fresh()->is_scheduled);

        $plan->items()->delete();
        $plan->delete();

        Livewire::test(ContentWeek::class, ['businessId' => $biz->id])
            ->assertSee('No content week planned yet');
    }
}
