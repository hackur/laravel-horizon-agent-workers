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
            // Cost tracking fields
            $table->decimal('cost_usd', 10, 6)->nullable()->after('usage_stats');
            $table->decimal('input_cost_usd', 10, 6)->nullable()->after('cost_usd');
            $table->decimal('output_cost_usd', 10, 6)->nullable()->after('input_cost_usd');

            // Pricing tier information
            $table->string('pricing_tier')->nullable()->after('output_cost_usd');

            // Budget tracking
            $table->boolean('over_budget')->default(false)->after('pricing_tier');

            // Add index for cost queries
            $table->index('cost_usd');
            $table->index(['provider', 'cost_usd']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('l_l_m_queries', function (Blueprint $table) {
            $table->dropIndex(['provider', 'cost_usd']);
            $table->dropIndex(['cost_usd']);
            $table->dropColumn([
                'cost_usd',
                'input_cost_usd',
                'output_cost_usd',
                'pricing_tier',
                'over_budget',
            ]);
        });
    }
};
