<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X182\Models\Comment;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;

class X182Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-182';
    }

    public function fill(Business $business): int
    {
        if (SocialAccount::where('business_id', $business->id)->where('account_handle', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $acc = SocialAccount::create(['business_id' => $business->id, 'platform' => 'facebook', 'account_handle' => self::MARKER.'Handle', 'is_connected' => true]);

        $post = SocialPost::create(['business_id' => $business->id, 'account_id' => $acc->id, 'content_text' => self::MARKER.'Text', 'has_branded_overlay' => false, 'is_published' => false]);

        Comment::create(['business_id' => $business->id, 'post_id' => $post->id, 'author_name' => self::MARKER.'Auth', 'comment_text' => self::MARKER.'Com', 'sentiment' => 'positive']);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = 0;
        $accs = SocialAccount::where('business_id', $business->id)->where('account_handle', 'like', self::MARKER.'%')->get();
        foreach ($accs as $acc) {
            $posts = SocialPost::where('account_id', $acc->id)->get();
            foreach ($posts as $post) {
                $count += Comment::where('post_id', $post->id)->delete();
                $count += $post->delete();
            }
            $count += $acc->delete();
        }

        return $count;
    }
}
