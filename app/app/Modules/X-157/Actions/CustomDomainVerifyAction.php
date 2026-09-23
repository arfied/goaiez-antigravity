<?php

namespace App\Modules\X157\Actions;

use App\Modules\X157\Domain\DnsResolver;
use App\Modules\X157\Models\CustomDomainRequest;

class CustomDomainVerifyAction
{
    public function __construct(
        private DnsResolver $resolver,
        private PlatformSiteAddressAction $addressAction
    ) {}

    public function handle(int $businessId): array
    {
        $request = CustomDomainRequest::where('business_id', $businessId)->first();
        if (! $request) {
            return ['status' => 'no_request'];
        }

        $platformHost = $this->addressAction->handle($businessId)->domain_name;
        $target = $this->resolver->cname($request->domain);

        $cleanTarget = $target ? rtrim(strtolower($target), '.') : null;
        $cleanPlatform = rtrim(strtolower($platformHost), '.');

        if ($cleanTarget === $cleanPlatform) {
            $request->status = 'verified';
            $request->verified_at = now();
            $request->failure_reason = null;
        } elseif ($cleanTarget === null) {
            $request->status = 'unverified';
            $request->failure_reason = 'no_cname';
        } else {
            $request->status = 'unverified';
            $request->failure_reason = 'points_elsewhere:'.$target;
        }

        $request->last_checked_at = now();
        $request->save();

        return [
            'status' => $request->status,
            'domain' => $request->domain,
            'expected' => $platformHost,
            'found' => $target,
        ];
    }
}
