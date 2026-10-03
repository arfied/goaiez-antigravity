<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\TenantNotResolved;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A signed-in account with no business that opens a tenant screen is shown the 403 page, the answer SetupController and
 * every other tenant-only surface already give — not "Something went wrong on our end".
 */
class TenantlessRequestTest extends TestCase
{
    private const PROBE = '/__tenantless-probe-8401';

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get(self::PROBE, fn () => throw new TenantNotResolved);
    }

    public function test_a_signed_in_browser_request_with_no_business_gets_the_403_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(self::PROBE)
            ->assertStatus(403)
            ->assertSee('permission to access this page')
            ->assertDontSee('Something went wrong on our end');
    }

    public function test_a_json_caller_and_a_signed_out_request_keep_the_old_answer(): void
    {
        $this->get(self::PROBE)->assertStatus(500);

        $this->actingAs(User::factory()->create());
        $this->getJson(self::PROBE)->assertStatus(500);
    }

    public function test_a_real_tenant_screen_answers_a_tenantless_account_with_403(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('x-103.site-inventory'))->assertStatus(403);
    }
}
