    /**
     * [N-217-01] an accepted recruit lands in X-205 with the EXACT terms offered
     * [N-217-02] no recruitment offer exceeds the confirmed commission ceiling
     * [N-217-03] ⛔ REFUSED: `php artisan why N-217-03` reports it is never DEFINED.
     *   Furthermore, this is a structural boundary rule (P-163). X-217 relies on
     *   AffiliateCreateAction from X-205 rather than writing directly, which satisfies P-163
     *   but is tested structurally rather than via an explicit behavioral assertion here.
     */
    public function test_recruitment_capabilities(): void
    {
        Event::fake([\App\Modules\X217\Events\SendRequested::class, \App\Modules\X217\Events\AffiliateRecruited::class, \App\Modules\X217\Events\AffiliateDeclined::class]);

        $biz = TestCase::provisionTenant(['name' => 'Cap Test Tenant', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        // [N-217-02] no recruitment offer exceeds the confirmed commission ceiling
        $prospect = $this->recruitAction->recruitProspect($biz->id, 'Cap Tester', 'cap@tester.com');
        try {
            $this->offerAction->makeOffer(
                businessId: $biz->id,
                prospectId: $prospect->id,
                offeredRateBps: 2600, // Exceeds ceiling
                ceilingRateBps: 2500
            );
            $this->fail('Expected InvalidArgumentException when offer exceeds ceiling [N-217-02]');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds ceiling', $e->getMessage(), 'Offer exceeding ceiling is rejected [N-217-02]');
        }

        // [N-217-01] offer row and program row asserted EQUAL
        $offer = $this->offerAction->makeOffer($biz->id, $prospect->id, 2000, 2500, '20% revshare');
        $affiliateCode = $this->pipelineAction->acceptOffer($biz->id, $offer->id);
        
        $affiliate = \App\Modules\X205\Models\Affiliate::where('business_id', $biz->id)->where('affiliate_code', $affiliateCode)->first();
        
        $this->assertEquals(
            $offer->offered_rate_bps,
            $affiliate->commission_rate_bps,
            '[N-217-01] Accepted recruit lands in X-205 with EXACT terms offered'
        );
    }
