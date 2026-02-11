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
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->text('task');
            $table->string('working_directory');
            $table->string('agent_model')->default('sonnet');
            $table->string('reviewer_model')->default('opus');
            $table->unsignedInteger('max_iterations')->default(5);
            $table->unsignedInteger('iterations_used')->nullable();
            $table->string('session_key')->nullable()->index();
            $table->enum('status', ['running', 'completed', 'failed', 'max_iterations_reached'])->default('running');
            $table->longText('final_output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('agent_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('iteration');
            $table->boolean('approved')->default(false);
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('score')->nullable(); // 1-10
            $table->string('model');
            $table->timestamps();

            $table->unique(['agent_run_id', 'iteration']);
            $table->index('approved');
        });

        Schema::create('agent_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('iteration');
            $table->enum('type', ['agent', 'reviewer']);
            $table->longText('content');
            $table->string('model');
            $table->unsignedInteger('tokens_used')->nullable();
            $table->timestamps();

            $table->index(['agent_run_id', 'iteration', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_outputs');
        Schema::dropIfExists('agent_reviews');
        Schema::dropIfExists('agent_runs');
    }
};
