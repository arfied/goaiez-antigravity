<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignRecipientStatus;
use App\Jobs\RunCampaignJob;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

it('reads max_deferral_days from settings', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'campaigns.run.max_deferral_days'],
        ['value' => json_encode(42), 'updated_at' => now()]
    );

    $job = new RunCampaignJob(1, null, 1);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('deferralIsExhausted');
    $method->setAccessible(true);

    $recipient = new CampaignRecipient(['first_deferred_at' => CarbonImmutable::now()->subDays(40)]);
    expect($method->invokeArgs($job, [$recipient]))->toBeFalse();

    $recipient2 = new CampaignRecipient(['first_deferred_at' => CarbonImmutable::now()->subDays(44)]);
    expect($method->invokeArgs($job, [$recipient2]))->toBeTrue();
});

it('reads barren_pass_minutes from settings', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'campaigns.run.barren_pass_minutes'],
        ['value' => json_encode(42), 'updated_at' => now()]
    );

    Bus::fake();

    $business = Business::factory()->create();
    $campaign = Campaign::factory()->create(['business_id' => $business->id]);
    Tenancy::set($business->id);

    $job = new RunCampaignJob($business->id, null, $campaign->id);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('closeIfFinished');
    $method->setAccessible(true);

    CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => CampaignRecipientStatus::Pending,
    ]);

    $method->invokeArgs($job, [$campaign, true]);

    Bus::assertDispatched(RunCampaignJob::class, function ($dispatchedJob) {
        // Just checking if it has a delay set. Testing the exact delay can be tricky due to seconds difference.
        return $dispatchedJob->delay !== null;
    });
});
