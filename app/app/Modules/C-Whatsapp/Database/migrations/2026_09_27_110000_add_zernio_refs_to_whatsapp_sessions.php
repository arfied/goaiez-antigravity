<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('zernio_conversation_id')->nullable()->index();
            $table->string('zernio_participant_ref')->nullable()->index();
            $table->string('last_inbound_ref')->nullable();

            $table->unique(['business_id', 'zernio_conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'zernio_conversation_id']);
            $table->dropColumn([
                'zernio_conversation_id',
                'zernio_participant_ref',
                'last_inbound_ref',
            ]);
        });
    }
};
