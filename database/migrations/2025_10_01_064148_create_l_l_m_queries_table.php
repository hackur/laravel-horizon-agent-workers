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
        Schema::create('l_l_m_queries', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // claude, ollama, lmstudio, claude-code
            $table->string('model')->nullable();
            $table->text('prompt');
            $table->longText('response')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('error')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('l_l_m_queries');
    }
};
