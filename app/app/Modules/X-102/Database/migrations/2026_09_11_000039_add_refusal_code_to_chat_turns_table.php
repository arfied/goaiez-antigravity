<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chat_turns', 'refusal_code')) {
            Schema::table('chat_turns', function (Blueprint $table): void {
                // The refusal code C-Agent returned for this agent turn, recorded so the capture write can honour it (P-148).
                $table->string('refusal_code')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_turns', 'refusal_code')) {
            Schema::table('chat_turns', function (Blueprint $table): void {
                $table->dropColumn('refusal_code');
            });
        }
    }
};
