<?php

namespace Tests\Feature\Logging;

use App\Services\Sms\TenantNumbers;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class NoDebugLeftoversTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_a_number_lookup_writes_no_phone_number_to_the_log(): void
    {
        $biz = static::provisionTenant(['name' => 'Business Name']);
        app(TenantNumbers::class)->releaseFromTenant($biz->id);
        $number = app(TenantNumbers::class)->assign($biz->id, '+15555550100');
        app(TenantNumbers::class)->bringIntoService($number, 'test');

        Log::spy();

        $result = app(TenantNumbers::class)->tenantFor('+15555550100');

        Log::shouldNotHaveReceived('warning', [\Mockery::on(function ($message) {
            return is_string($message) && str_contains($message, 'tenantFor e164');
        })]);

        $this->assertEquals($biz->id, $result);
    }

    public function test_a_successful_text_back_is_not_logged_as_an_error(): void
    {
        $content = file_get_contents(app_path('Services/Voice/MissedCallTextBack.php'));
        $this->assertStringNotContainsString("Log::error('TextBack outcome", $content);
        $this->assertStringContainsString("Log::info('TextBack outcome", $content);
    }
}
