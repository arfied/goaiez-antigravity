<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CReviews\Models\ReviewReply;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X121\Models\Person;

class CReviewsFiller implements DemoFiller
{
    public function module(): string
    {
        return 'C-Reviews';
    }

    public function fill(Business $business): int
    {
        if (ReviewRequest::where('business_id', $business->id)->where('review_text', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $person = Person::firstOrCreate([
            'business_id' => $business->id,
            'first_name' => self::MARKER.'Dana',
        ], [
            'last_name' => 'Customer',
            'phone' => '+15125554601',
        ]);

        $rows = [
            ['rating' => 5, 'platform' => 'google', 'status' => 'published_public', 'review_text' => self::MARKER.'Fast, friendly and fixed first time'],
            ['rating' => 4, 'platform' => 'google', 'status' => 'published_public', 'review_text' => self::MARKER.'Good service, a little pricey'],
            ['rating' => 3, 'platform' => 'yelp', 'status' => 'triaged_internal', 'review_text' => self::MARKER.'Average experience overall'],
            ['rating' => 2, 'platform' => 'google', 'status' => 'triaged_internal', 'review_text' => self::MARKER.'Tech arrived late and tracking was off'],
            ['rating' => 1, 'platform' => 'facebook', 'status' => 'triaged_internal', 'review_text' => self::MARKER.'Terrible communication and messy'],
            ['rating' => 5, 'platform' => 'google', 'status' => 'sent', 'review_text' => self::MARKER.'sent'],
        ];

        $count = 0;
        foreach ($rows as $index => $row) {
            $req = ReviewRequest::create(array_merge([
                'business_id' => $business->id,
                'customer_id' => $person->id,
                'gbp_suspended' => false,
            ], $row));
            $count++;

            if ($row['rating'] === 5) {
                if ($index === 0) {
                    ReviewReply::create([
                        'business_id' => $business->id,
                        'review_request_id' => $req->id,
                        'reply_text' => self::MARKER.'Thank you, Dana!',
                        'status' => 'published',
                        'published_at' => now(),
                        'is_public' => true,
                    ]);
                    $count++;
                } elseif ($index === 5) {
                    ReviewReply::create([
                        'business_id' => $business->id,
                        'review_request_id' => $req->id,
                        'reply_text' => self::MARKER.'Thank you, Dana, we appreciate it!',
                        'status' => 'draft',
                        'is_public' => true,
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function purge(Business $business): int
    {
        $count = ReviewRequest::where('business_id', $business->id)
            ->where('review_text', 'like', self::MARKER.'%')
            ->delete();

        Person::where('business_id', $business->id)
            ->where('first_name', self::MARKER.'Dana')
            ->delete();

        return $count;
    }
}
