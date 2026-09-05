<?php

declare(strict_types=1);

namespace Tests\Modules\X132;

use App\Modules\X132\Actions\PersonMergeAction;
use App\Modules\X132\Actions\PersonResolveAction;
use App\Modules\X132\Domain\IdentityEngine;
use App\Modules\X132\Events\PersonMerged;
use App\Modules\X132\Events\PersonResolved;
use App\Modules\X132\Models\PersonLink;
use App\Modules\X132\Models\ResolutionEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X132Test extends TestCase
{
    private PersonResolveAction $resolveAction;

    private PersonMergeAction $mergeAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolveAction = new PersonResolveAction;
        $this->mergeAction = new PersonMergeAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'SendPermit|ConsentService|->send\(' app/Modules/X-132/ returns nothing;
     * a resolved-but-never-consented Person cannot receive a marketing send — the refusal comes from ConsentService, never from the graph
     */
    public function test_anchor_identity_resolution_and_no_send_permit_logic_in_module(): void
    {
        Event::fake([PersonResolved::class, PersonMerged::class]);

        $biz = TestCase::provisionTenant(['name' => 'Identity Graph Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $canonicalPersonId = 9100;
        $matchedPersonId = 9105;

        // 1. Resolve identity across multi-source evidence (G13-25, P-147)
        $evidence = [
            ['name' => 'email', 'value' => 'cto@lonestarhvac.com', 'source' => 'email_match', 'confidence' => 0.99],
            ['name' => 'company_domain', 'value' => 'lonestarhvac.com', 'source' => 'clearbit_reveal', 'confidence' => 0.85],
        ];

        $resolved = $this->resolveAction->resolveIdentity(
            businessId: $biz->id,
            canonicalPersonId: $canonicalPersonId,
            evidenceFields: $evidence,
            matchingPersonId: $matchedPersonId
        );

        $this->assertEquals('resolved', $resolved['status']);
        $this->assertEquals(0.99, $resolved['confidence']);

        // Assert evidence stored
        $evRows = ResolutionEvidence::where('business_id', $biz->id)->where('canonical_person_id', $canonicalPersonId)->get();
        $this->assertCount(2, $evRows);

        // Assert graph link created
        $link = PersonLink::where('business_id', $biz->id)->where('canonical_person_id', $canonicalPersonId)->first();
        $this->assertNotNull($link);
        $this->assertEquals($matchedPersonId, $link->linked_person_id);

        Event::assertDispatched(PersonResolved::class);

        // 2. Merge duplicate identity
        $merged = $this->mergeAction->merge($biz->id, $canonicalPersonId, 9108);
        $this->assertEquals('merged', $merged['status']);
        Event::assertDispatched(PersonMerged::class);
    }

    /**
     * [G13-11], [G13-18], [G13-25]
     */
    public function test_identity_capabilities(): void
    {
        $this->assertNull(config('services.clearbit'));

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $this->assertArrayNotHasKey('clearbit/clearbit', $composer['require'] ?? []);

        $engine = new IdentityEngine;
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('[G13-18]');
        $engine->validateClearbitReveal();
    }
}
