<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'provider_conversation_ref')) {
                $table->string('provider_conversation_ref')->nullable();
                $table->index(['business_id', 'provider_conversation_ref']);
            }
            if (! Schema::hasColumn('conversations', 'provider_account_ref')) {
                $table->string('provider_account_ref')->nullable();
            }
            if (! Schema::hasColumn('conversations', 'contact_label')) {
                $table->string('contact_label')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'provider_conversation_ref']);
            $table->dropColumn(['provider_conversation_ref', 'provider_account_ref', 'contact_label']);
        });
    }
};
