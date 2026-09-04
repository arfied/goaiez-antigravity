<?php
namespace Tests\Modules\X124;
use Tests\TestCase;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Modules\X124\Models\AssistantRecommendation;
use App\Modules\X124\Models\AssistantSession;

class TestRenderTest extends TestCase {
    use DatabaseTransactions;
    public function test_dump() {
        $biz = self::provisionTenant(['name' => 'Dump Tenant']);
        Tenancy::set($biz->id);
        $sess = AssistantSession::create(['business_id' => $biz->id, 'session_token' => 'tok1']);
        AssistantRecommendation::create([
            'business_id' => $biz->id,
            'session_id' => $sess->id,
            'title' => '14 missed calls',
            'action_key' => 'enable_text_back',
            'status' => 'active',
        ]);
        $user = \App\Models\User::find($biz->owner_user_id);
        $res = $this->actingAs($user)->get('/home');
        file_put_contents('dump3.txt', $res->getContent());
        $this->assertTrue(true);
    }
}
