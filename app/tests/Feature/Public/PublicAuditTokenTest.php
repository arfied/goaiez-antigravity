<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\PublicAudit;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicAuditTokenTest extends TestCase
{
    public function test_live_token_returns_200_with_matching_token(): void
    {
        $audit = PublicAudit::factory()->create([
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->getJson("/api/public/audit/{$audit->token}")
            ->assertOk()
            ->assertJsonPath('data.token', $audit->token);
    }

    public function test_unknown_token_returns_404_with_exact_message(): void
    {
        $response = $this->getJson('/api/public/audit/unknown_token')
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'That audit link has expired. Run a new one — it takes a few seconds.',
            ]);
    }

    public function test_not_live_token_returns_404_with_same_message(): void
    {
        $audit = PublicAudit::factory()->create([
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $response = $this->getJson("/api/public/audit/{$audit->token}")
            ->assertNotFound();

        $unknownResponse = $this->getJson('/api/public/audit/unknown_token')
            ->assertNotFound();

        $this->assertSame($unknownResponse->json(), $response->json());
        $response->assertExactJson([
            'message' => 'That audit link has expired. Run a new one — it takes a few seconds.',
        ]);
    }
}
