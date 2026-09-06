<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Events\JobCreated;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

final class JobCreateAction
{
    public function handle(
        int $businessId,
        int $personId,
        string $title,
        ?int $priceCents = null,
        ?int $techId = null,
        ?int $locationId = null
    ): array {
        if (! Tenancy::id()) {
            abort(403, 'Missing tenant');
        }

        $exists = DB::table('people')
            ->where('id', $personId)
            ->where('business_id', $businessId)
            ->exists();

        if (! $exists) {
            abort(400, 'PERSON_UNKNOWN');
        }

        $data = [
            'business_id' => $businessId,
            'person_id' => $personId,
            'title' => $title,
            'status' => 'pending',
            'price_cents' => $priceCents ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('work_orders')->insertGetId($data);

        event(new JobCreated(
            businessId: $businessId,
            jobId: (int) $id,
            title: $title,
            priceCents: $priceCents ?? 0
        ));

        return ['job_id' => (int) $id];
    }
}
