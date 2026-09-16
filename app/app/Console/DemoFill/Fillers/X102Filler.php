<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X102\Models\ChatLead;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Models\ChatTurn;

class X102Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-102';
    }

    public function fill(Business $business): int
    {
        $count = ChatSession::where('business_id', $business->id)
            ->where('session_token', 'like', self::MARKER.'%')
            ->count();

        if ($count > 0) {
            return 0;
        }

        $rows = 0;

        $session1 = ChatSession::create([
            'business_id' => $business->id,
            'session_token' => self::MARKER.'sess_active_'.$business->id,
            'visitor_ip' => '203.0.113.51',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        $rows++;

        $session2 = ChatSession::create([
            'business_id' => $business->id,
            'session_token' => self::MARKER.'sess_rage_'.$business->id,
            'visitor_ip' => '203.0.113.52',
            'status' => 'closed',
            'rage_clicks_count' => 3,
            'is_ai_capped' => false,
        ]);
        $rows++;

        ChatLead::create([
            'business_id' => $business->id,
            'chat_session_id' => $session2->id,
            'name' => self::MARKER.'John Doe',
            'phone' => '+15125550000',
            'form_type' => 'offline_capped_form',
        ]);
        $rows++;

        ChatTurn::create([
            'business_id' => $business->id,
            'chat_session_id' => $session1->id,
            'author_type' => 'visitor',
            'message' => self::MARKER.'Hello, I need some help.',
        ]);
        $rows++;

        ChatTurn::create([
            'business_id' => $business->id,
            'chat_session_id' => $session1->id,
            'author_type' => 'agent',
            'message' => self::MARKER.'Sure, what do you need help with?',
        ]);
        $rows++;

        return $rows;
    }

    public function purge(Business $business): int
    {
        $count = ChatSession::where('business_id', $business->id)
            ->where('session_token', 'like', self::MARKER.'%')
            ->delete(); // Cascading deletes on ChatLead and ChatTurn usually, but if not we can delete explicitly.

        // In Laravel, without cascade, we should delete children first.
        // Let's delete children explicitly just in case to be safe, although deleting parent first might fail if there's foreign key constraints.
        // Wait, the delete() method on Eloquent query doesn't cascade by itself unless configured in DB or events.

        $turnsDeleted = ChatTurn::where('business_id', $business->id)
            ->where('message', 'like', self::MARKER.'%')
            ->delete();

        $leadsDeleted = ChatLead::where('business_id', $business->id)
            ->where('name', 'like', self::MARKER.'%')
            ->delete();

        $sessionsDeleted = ChatSession::where('business_id', $business->id)
            ->where('session_token', 'like', self::MARKER.'%')
            ->delete();

        return $turnsDeleted + $leadsDeleted + $sessionsDeleted;
    }
}
