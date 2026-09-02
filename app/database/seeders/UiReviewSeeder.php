<?php

namespace Database\Seeders;

use App\Enums\ReviewDestination;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\ReviewDestinationSetting;
use App\Models\ReviewHubPage;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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

        $staffUser = User::firstWhere('email', 'staff@business.com');
        if (! $staffUser) {
            $staffUser = User::factory()->create([
                'email' => 'staff@business.com',
                'name' => 'Staff Review',
                'role' => 'super_admin',
            ]);
        }

        $setupUser = User::firstWhere('email', 'setup@business.com');
        if (! $setupUser) {
            $setupUser = User::factory()->create([
                'email' => 'setup@business.com',
                'name' => 'Setup Wizard User',
                'role' => 'owner',
            ]);
        }
        if (! DB::table('businesses')->where('owner_user_id', $setupUser->id)->exists()) {
            app(TenantProvisioner::class)->provision($setupUser);
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
            try {
                FeedbackPage::factory()->forLocation($location)->create([
                    'slug' => 'review-business-2',
                    'is_published' => true,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // Already exists for another tenant or this tenant
            }
        } else {
            if (empty($page->slug) || Str::startsWith($page->slug, 'review-business-2')) {
                try {
                    $page->update(['slug' => 'review-business-2']);
                } catch (UniqueConstraintViolationException $e) {
                    // Slug taken
                }
            }
        }

        $hubPage = ReviewHubPage::firstWhere('location_id', $location->id);
        if (! $hubPage) {
            try {
                ReviewHubPage::factory()->create([
                    'location_id' => $location->id,
                    'business_id' => $businessId,
                    'slug' => 'review-business-2',
                    'is_published' => true,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // Already exists
            }
        } else {
            if (empty($hubPage->slug) || Str::startsWith($hubPage->slug, 'review-business-2')) {
                try {
                    $hubPage->update(['slug' => 'review-business-2']);
                } catch (UniqueConstraintViolationException $e) {
                    // Slug taken
                }
            }
        }

        $autopilot = AutopilotSettings::firstWhere('location_id', $location->id);
        if (! $autopilot) {
            AutopilotSettings::factory()->create([
                'location_id' => $location->id,
                'business_id' => $businessId,
                'update_review_hub' => true,
            ]);
        } else {
            $autopilot->update(['update_review_hub' => true]);
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
