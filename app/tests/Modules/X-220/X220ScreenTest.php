<?php

namespace Tests\Feature\Account;

use App\Jobs\PostReplyJob;
use App\Models\Reply;
use App\Models\Review;
use App\Modules\X220\Events\EvalCompleted;
use App\Modules\X220\Models\AiPrompt;
use App\Modules\X220\Models\GoldenSet;
use App\Modules\X220\Ui\EvalReport;
use App\Services\Reviews\ReviewReplies;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ReplyQueueScreenTest extends TestCase
{
    public function test_approving_a_reply_appends_to_golden_set_and_drops_oldest(): void
    {
        Bus::fake([PostReplyJob::class]);
        Event::fake([EvalCompleted::class]);

        $biz = self::provisionTenant(['name' => 'Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $prompt = AiPrompt::create([
            'business_id' => $biz->id,
            'prompt_key' => 'reply.generate',
            'version' => 1,
            'body' => 'Test',
        ]);

        $review = Review::factory()->create(['business_id' => $biz->id, 'comment' => 'Review 1']);
        $reply = Reply::factory()->create(['business_id' => $biz->id, 'review_id' => $review->id, 'text' => 'Draft 1']);

        $service = app(ReviewReplies::class);
        $service->approve($reply, 'Approved 1', 'Test Actor');

        $golden = GoldenSet::where('business_id', $biz->id)->where('prompt_id', $prompt->id)->first();
        $this->assertNotNull($golden);
        $this->assertCount(1, $golden->test_cases);
        $this->assertEquals('Review 1', $golden->test_cases[0]['input']);
        $this->assertEquals('Approved 1', $golden->expected_outputs[0]['expected']);

        // Cap drops oldest (seed 50)
        // Let's directly create 50 cases
        $cases = [];
        $outputs = [];
        for ($i = 0; $i < 50; $i++) {
            $cases[] = ['input' => "Old $i"];
            $outputs[] = ['expected' => "Old Out $i"];
        }
        $golden->test_cases = $cases;
        $golden->expected_outputs = $outputs;
        $golden->save();

        $review2 = Review::factory()->create(['business_id' => $biz->id, 'comment' => 'Review New']);
        $reply2 = Reply::factory()->create(['business_id' => $biz->id, 'review_id' => $review2->id, 'text' => 'Draft New']);
        $service->approve($reply2, 'Approved New', 'Test Actor');

        $golden->refresh();
        $this->assertCount(50, $golden->test_cases);
        $this->assertEquals('Old 1', $golden->test_cases[0]['input']);
        $this->assertEquals('Review New', $golden->test_cases[49]['input']);
        $this->assertEquals('Approved New', $golden->expected_outputs[49]['expected']);

        Event::assertNotDispatched(EvalCompleted::class);
    }

    public function test_screen_lists_set_and_count_and_runs_honest_eval(): void
    {
        $biz = self::provisionTenant(['name' => 'Biz2', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'fake completion']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]),
        ]);

        $prompt = AiPrompt::create(['business_id' => $biz->id, 'prompt_key' => 'reply.generate', 'version' => 1, 'body' => 'Test']);
        $golden = GoldenSet::create([
            'business_id' => $biz->id,
            'prompt_id' => $prompt->id,
            'prompt_version' => 1,
            'test_cases' => [['input' => '1', 'expected' => 'fake completion'], ['input' => '2', 'expected' => 'fake completion']],
            'expected_outputs' => [['expected' => 'fake completion'], ['expected' => 'fake completion']],
            'score_threshold' => 90,
        ]);

        Livewire::test(EvalReport::class, ['businessId' => $biz->id])
            ->assertSee('reply.generate v1')
            ->assertSee('Cases: 2')
            ->assertSee('No evaluation run yet')
            ->call('run', $golden->id)
            ->assertSee('100% (Passed)');
    }

    public function test_screen_403_no_tenant(): void
    {
        Livewire::test(EvalReport::class, ['businessId' => 0])
            ->assertForbidden();
    }

    public function test_other_tenant_invisible(): void
    {
        $biz1 = self::provisionTenant(['name' => 'Biz1', 'currency' => 'USD']);
        $biz2 = self::provisionTenant(['name' => 'Biz2', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz1->id}'");

        $prompt = AiPrompt::create(['business_id' => $biz1->id, 'prompt_key' => 'reply.generate', 'version' => 1, 'body' => 'Test']);
        GoldenSet::create(['business_id' => $biz1->id, 'prompt_id' => $prompt->id, 'prompt_version' => 1, 'test_cases' => [], 'score_threshold' => 90]);

        Livewire::test(EvalReport::class, ['businessId' => $biz2->id])
            ->assertDontSee('reply.generate');
    }
}
