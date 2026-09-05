<?php

declare(strict_types=1);

namespace Tests\Modules\X121;

use Tests\TestCase;
use App\Modules\X121\Actions\JobCreateAction;
use App\Modules\X121\Events\JobCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use App\Support\Tenancy;
use App\Models\Business;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class JobCreateActionTest extends TestCase
{
    public function test_it_creates_a_job_and_emits_event(): void
    {
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $personId = DB::table('people')->insertGetId([
            'business_id' => $business->id,
            'first_name' => 'John',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Event::fake();

        $action = new JobCreateAction();
        $res = $action->handle($business->id, $personId, 'Test Job', 15000);

        $this->assertDatabaseHas('work_orders', [
            'id' => $res['job_id'],
            'business_id' => $business->id,
            'person_id' => $personId,
            'title' => 'Test Job',
            'status' => 'pending',
            'price_cents' => 15000,
        ]);

        Event::assertDispatched(JobCreated::class, function ($event) use ($business, $res) {
            return $event->businessId === $business->id
                && $event->jobId === (int) $res['job_id']
                && $event->title === 'Test Job'
                && $event->priceCents === 15000;
        });
    }

    public function test_it_refuses_unknown_person(): void
    {
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $action = new JobCreateAction();

        try {
            $action->handle($business->id, 99999, 'Test Job');
            $this->fail('Expected an exception');
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('PERSON_UNKNOWN', $e->getMessage());
        }
    }
}
