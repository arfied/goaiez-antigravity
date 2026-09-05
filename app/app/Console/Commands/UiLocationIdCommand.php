<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('ui:location-id')]
#[Description('Get location id for UI review rig')]
class UiLocationIdCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $owner = User::where('email', 'owner2@business.com')->first();
        if ($owner) {
            Tenancy::actingAsUser($owner->id, function () use ($owner) {
                $businessId = DB::table('businesses')->where('owner_user_id', $owner->id)->value('id');
                if ($businessId) {
                    Tenancy::actingAs($businessId, function () use ($businessId) {
                        $location = Location::withoutGlobalScopes()->where('business_id', $businessId)->first();
                        if ($location) {
                            $this->getOutput()->write((string) $location->id);
                        }
                    });
                }
            });
        }
    }
}
