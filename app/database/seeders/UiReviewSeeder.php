<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class UiReviewSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstWhere('email', 'owner2@business.com');
        if (! $owner) {
            $owner = User::factory()->create([
                'email' => 'owner2@business.com',
                'name' => 'Owner Review 2',
                'role' => 'owner',
            ]);
            
            $business = Business::factory()->create([
                'owner_user_id' => $owner->id,
                'name' => 'Review Business 2 LLC',
            ]);
            
            Tenancy::set((int) $business->id);
            
            Subscription::factory()->create([
                'business_id' => $business->id,
                'plan' => 'base',
                'status' => 'active',
            ]);
            
            Tenancy::forget();
        }
    }
}
