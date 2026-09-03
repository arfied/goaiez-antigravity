<?php

namespace Database\Seeders;

use App\Enums\ReviewDestination;
use App\Models\AuditLogEntry;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Message;
use App\Models\OutreachMessage;
use App\Models\Review;
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

        $staffUser = User::updateOrCreate(['email' => 'staff@business.com'], [
            'email' => 'staff@business.com',
            'name' => 'Staff Review',
            'role' => 'super_admin',
            'two_factor_secret' => encrypt('dummy_secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['12345-67890', '09876-54321'])),
            'two_factor_confirmed_at' => now(),
        ]);

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
            $location = Location::factory()->create(['business_id' => $businessId, 'name' => 'Review Location 2']);
        } else {
            $location->update(['name' => 'Review Location 2']);
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

        if (Customer::count() < 12) {
            $customers = Customer::factory()->count(12)->create([
                'location_id' => $location->id,
            ]);

            // 3 with feedback (first-party review)
            $customers->take(3)->each(function ($c) use ($location) {
                Review::factory()->create([
                    'location_id' => $location->id,
                    'customer_id' => $c->id,
                ]);
            });
        }

        if (Conversation::count() < 2) {
            $conversations = Conversation::factory()->count(2)->create([
                'location_id' => $location->id,
                'customer_id' => Customer::first()->id ?? null,
            ]);

            foreach ($conversations as $conv) {
                Message::factory()->count(3)->create([
                    'conversation_id' => $conv->id,
                ]);
            }
        }

        if (OutreachMessage::count() < 6) {
            OutreachMessage::factory()->count(6)->sent()->create([
                'customer_id' => Customer::first()->id ?? null,
            ]);
        }

        if (Review::where('is_platform', false)->count() < 4) {
            // 4 reviews (one unhappy)
            Review::factory()->fromGoogle()->count(3)->create([
                'location_id' => $location->id,
                'rating' => 5,
            ]);
            Review::factory()->fromGoogle()->create([
                'location_id' => $location->id,
                'rating' => 1,
            ]);
        }

        if (! DB::table('directory_memberships')->where('business_id', $businessId)->exists()) {
            DB::table('directory_memberships')->insert([
                ['business_id' => $businessId, 'directory_name' => 'Google', 'directory_url' => 'https://google.com', 'is_noindex' => false, 'directory_index' => 1, 'is_purchased' => false, 'created_at' => now(), 'updated_at' => now()],
                ['business_id' => $businessId, 'directory_name' => 'Yelp', 'directory_url' => 'https://yelp.com', 'is_noindex' => false, 'directory_index' => 2, 'is_purchased' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (! DB::table('citations')->where('business_id', $businessId)->exists()) {
            DB::table('citations')->insert([
                ['business_id' => $businessId, 'membership_id' => null, 'nap_business_name' => 'Test Business', 'nap_phone' => '1234567890', 'nap_address' => '123 Test St', 'is_verified' => true, 'directory' => 'Google', 'created_at' => now(), 'updated_at' => now(), 'nap_status' => 'correct'],
                ['business_id' => $businessId, 'membership_id' => null, 'nap_business_name' => 'Test Business', 'nap_phone' => '1234567890', 'nap_address' => '123 Test St', 'is_verified' => true, 'directory' => 'Yelp', 'created_at' => now(), 'updated_at' => now(), 'nap_status' => 'correct'],
                ['business_id' => $businessId, 'membership_id' => null, 'nap_business_name' => 'Test Business', 'nap_phone' => '1234567890', 'nap_address' => '123 Test St', 'is_verified' => true, 'directory' => 'Bing', 'created_at' => now(), 'updated_at' => now(), 'nap_status' => 'missing'],
            ]);
        }

        if (AuditLogEntry::count() < 2) {
            AuditLogEntry::factory()->create([
                'action' => 'settings.updated',
                'metadata' => ['field' => 'automation_mode'],
                'created_at' => now()->subDays(1),
            ]);
            AuditLogEntry::factory()->create([
                'action' => 'user.invited',
                'metadata' => ['role' => 'staff'],
                'created_at' => now(),
            ]);
        }

        if (! DB::table('invoices')->where('business_id', $businessId)->exists()) {
            DB::table('invoices')->insert([
                ['business_id' => $businessId, 'invoice_number' => 'INV-001', 'total_cents' => 10000, 'paid_cents' => 10000, 'status' => 'paid', 'due_date' => now()->subDays(10), 'created_at' => now(), 'updated_at' => now()],
                ['business_id' => $businessId, 'invoice_number' => 'INV-002', 'total_cents' => 5000, 'paid_cents' => 0, 'status' => 'overdue', 'due_date' => now()->subDays(5), 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
