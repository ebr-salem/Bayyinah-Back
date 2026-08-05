<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approved_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_output_id')->constrained('ai_outputs')->cascadeOnDelete();
            $table->json('edited_content');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approved_versions');
    }
};
