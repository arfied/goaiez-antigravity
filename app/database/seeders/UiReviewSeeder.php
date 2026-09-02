<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Location;
use App\Models\FeedbackPage;
use App\Models\ReviewDestinationSetting;
use App\Models\Customer;
use App\Enums\ReviewDestination;
use Illuminate\Support\Str;

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
        }

        $setupUser = User::firstWhere('email', 'setup@business.com');
        if (! $setupUser) {
            User::factory()->create([
                'email' => 'setup@business.com',
                'name' => 'Setup Wizard User',
                'role' => 'owner',
            ]);
        }

        Tenancy::actingAsUser($owner->id, function () use ($owner) {
            $businessId = DB::table('businesses')
                ->where('owner_user_id', $owner->id)
                ->value('id');

            if (! $businessId) {
                $business = Business::factory()->create([
                    'owner_user_id' => $owner->id,
                    'name' => 'Review Business 2 LLC',
                    'advanced_dashboard_enabled' => true,
                ]);

                Tenancy::set((int) $business->id);

                Subscription::factory()->create([
                    'business_id' => $business->id,
                    'plan' => 'base',
                    'status' => 'active',
                ]);
                $this->ensureBusinessDependencies($business->id);

                Tenancy::forget();
            } else {
                Tenancy::actingAs($businessId, function () use ($businessId) {
                    DB::table('businesses')
                        ->where('id', $businessId)
                        ->update(['advanced_dashboard_enabled' => true]);
                    $this->ensureBusinessDependencies($businessId);
                });
            }
        });
    }

    private function ensureBusinessDependencies(int $businessId): void
    {
        $location = Location::firstWhere('business_id', $businessId);
        if (! $location) {
            $location = Location::factory()->create(['business_id' => $businessId]);
        }

        $page = FeedbackPage::firstWhere('location_id', $location->id);
        if (! $page) {
            FeedbackPage::factory()->forLocation($location)->create([
                'slug' => 'review-business-2-' . Str::random(6),
                'is_published' => true,
            ]);
        }

        $setting = ReviewDestinationSetting::where('location_id', $location->id)->where('destination', ReviewDestination::Google->value)->first();
        if (! $setting) {
            ReviewDestinationSetting::factory()->create([
                'location_id' => $location->id,
                'destination' => ReviewDestination::Google,
                'enabled' => true,
                'invite_threshold' => 4,
                'link_url' => 'https://google.com',
            ]);
        }

        $customer = Customer::firstWhere('email', 'customer2@reviewbusiness2.com');
        if (! $customer) {
            Customer::factory()->create([
                'email' => 'customer2@reviewbusiness2.com',
                'name' => 'Test Customer',
                'business_id' => $businessId,
                'location_id' => $location->id,
            ]);
        }
    }
}
