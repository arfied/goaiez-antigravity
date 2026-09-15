<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csat_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qa_ticket_id')->unique()->constrained('qa_tickets')->cascadeOnDelete();
            $table->tinyInteger('score')->unsigned()->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_valid');
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csat_answers');
    }
};
