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
use App\Models\TriageConversation;
use App\Models\User;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Modules\X124\Models\AssistantRecommendation;
use App\Modules\X124\Models\AssistantSession;
use App\Services\Proof\ProofNumbers;
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
            'password' => bcrypt('password'),
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
            Tenancy::actingAsUser($setupUser->id, function () use ($setupUser) {
                app(TenantProvisioner::class)->provision($setupUser);
            });
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
            $ratings = [5, 4, 2];
            $customers->take(3)->values()->each(function ($c, $index) use ($location, $ratings) {
                $days = rand(1, 30);
                $review = Review::factory()->create([
                    'location_id' => $location->id,
                    'customer_id' => $c->id,
                    'rating' => $ratings[$index],
                    'created_at' => now()->subDays($days),
                    'updated_at' => now()->subDays($days),
                ]);

                if ($ratings[$index] <= 3) {
                    TriageConversation::factory()->create([
                        'review_id' => $review->id,
                        'status' => 'resolved',
                        'resolution' => 'Customer is happy now',
                        'updated_at' => now()->subDays($days),
                    ]);
                }
            });
        }

        if (Conversation::count() < 2) {
            $convCustomers = Customer::where('location_id', $location->id)->take(2)->get();
            foreach ($convCustomers as $c) {
                $days = rand(1, 30);
                $conv = Conversation::factory()->create([
                    'location_id' => $location->id,
                    'customer_id' => $c->id,
                    'channel' => 'sms',
                    'created_at' => now()->subDays($days),
                    'updated_at' => now()->subDays($days),
                ]);
                for ($j = 0; $j < 3; $j++) {
                    Message::factory()->create([
                        'conversation_id' => $conv->id,
                        'created_at' => now()->subDays($days)->addMinutes($j * 5),
                    ]);
                }
            }
        }

        if (OutreachMessage::count() < 6) {
            $messageCustomers = Customer::where('location_id', $location->id)->inRandomOrder()->take(3)->get();
            $bodies = [
                'Hi, thanks for visiting us!',
                'Don\'t forget to leave a review.',
                'Your appointment is confirmed for tomorrow.',
            ];

            for ($i = 0; $i < 6; $i++) {
                $c = $messageCustomers[$i % 3] ?? Customer::first();
                OutreachMessage::factory()->sent()->create([
                    'customer_id' => $c->id,
                    'body' => $bodies[$i % 3],
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        if (Review::where('location_id', $location->id)->where('status', 'pending')->count() < 4) {
            // 4 reviews (one unhappy)
            for ($i = 0; $i < 3; $i++) {
                $days = rand(1, 30);
                Review::factory()->create([
                    'location_id' => $location->id,
                    'rating' => 5,
                    'created_at' => now()->subDays($days),
                    'updated_at' => now()->subDays($days),
                ]);
            }
            $days = rand(1, 30);
            Review::factory()->create([
                'location_id' => $location->id,
                'rating' => 1,
                'created_at' => now()->subDays($days),
                'updated_at' => now()->subDays($days),
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

        if (AssistantRecommendation::where('business_id', $businessId)->count() === 0) {
            $asess = AssistantSession::create(['business_id' => $businessId, 'session_token' => 'asess_1']);
            AssistantRecommendation::create([
                'business_id' => $businessId,
                'session_id' => $asess->id,
                'title' => '14 missed calls, no text-back template — turn it on?',
                'action_key' => 'enable_text_back',
                'status' => 'active',
            ]);
        }

        if (Visit::where('business_id', $businessId)->count() === 0) {
            for ($v = 1; $v <= 3; $v++) {
                $visit = Visit::create([
                    'business_id' => $businessId,
                    'visitor_id' => 'vis_'.$v,
                    'ip_hash' => 'hash'.$v,
                    'user_agent' => 'Mozilla',
                    'landing_page' => '/',
                ]);
                $session = Session::create([
                    'business_id' => $businessId,
                    'visit_id' => $visit->id,
                    'session_token' => 'sess_tok_'.$v,
                    'started_at' => now()->startOfDay(),
                    'ended_at' => now()->startOfDay()->addMinutes(5),
                ]);
                PixelEvent::create([
                    'business_id' => $businessId,
                    'session_id' => $session->id,
                    'event_name' => 'pageview',
                    'payload' => ['url' => '/'],
                    'created_at' => now(),
                ]);
            }
        }

        app(ProofNumbers::class)->recompute(ProofNumbers::monthOf());
        app(ProofNumbers::class)->recompute(ProofNumbers::ALL);
    }
}
