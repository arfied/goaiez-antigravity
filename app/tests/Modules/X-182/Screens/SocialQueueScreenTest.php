<?php

declare(strict_types=1);

namespace Tests\Modules\X182\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X182\Models\Comment;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use App\Modules\X182\Ui\SocialQueue;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SocialQueueScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-182.social-queue'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('not built here yet');

        Tenancy::set((int) $biz->id);
        $account = SocialAccount::create(['business_id' => $biz->id, 'platform' => 'facebook', 'account_handle' => 'distinctive-handle-4471']);
        $post = SocialPost::create(['business_id' => $biz->id, 'account_id' => $account->id, 'content_text' => 'Distinctive Post Body 4471']);
        Comment::create(['business_id' => $biz->id, 'post_id' => $post->id, 'author_name' => 'A Reader', 'comment_text' => 'nice', 'sentiment' => 'positive']);
        Tenancy::forget();

        $this->get(route('x-182.social-queue'))
            ->assertOk()
            ->assertSee('Distinctive Post Body 4471')
            ->assertSee('1 comments')
            ->assertDontSee('not built here yet');

        Livewire::actingAs($owner)->test(SocialQueue::class, ['businessId' => $biz->id])->assertOk();
    }
}
