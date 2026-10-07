<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webstudio_template_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('template_id', 64)->unique();
            $table->string('project_id', 64);
            $table->string('label', 255);
            $table->string('builder_origin', 255);
            $table->timestamp('imported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webstudio_template_projects');
    }
};
