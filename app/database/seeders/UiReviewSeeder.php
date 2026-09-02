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
        $owner = User::firstWhere('email', 'owner@business.com');
        if (! $owner) {
            $owner = User::factory()->create([
                'email' => 'owner@business.com',
                'name' => 'Owner Review',
                'role' => 'owner',
            ]);
            
            $business = Business::factory()->create([
                'owner_user_id' => $owner->id,
                'name' => 'Review Business LLC',
                'advanced_dashboard_enabled' => true,
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
