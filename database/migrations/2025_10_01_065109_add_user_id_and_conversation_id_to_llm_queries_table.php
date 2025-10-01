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
        Schema::table('l_l_m_queries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->index('user_id');
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('l_l_m_queries', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['conversation_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['conversation_id']);
            $table->dropColumn(['user_id', 'conversation_id']);
        });
    }
};
