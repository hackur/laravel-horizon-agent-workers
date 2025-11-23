<?php

namespace App\Http\Controllers;

use App\Models\LLMQuery;
use App\Services\CostCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostController extends Controller
{
    /**
     * Constructor for CostController.
     *
     * @param  CostCalculator  $costCalculator  Service for cost calculations
     */
    public function __construct(
        protected CostCalculator $costCalculator
    ) {}

    /**
     * Display the cost tracking dashboard.
     *
     * Shows comprehensive cost analytics including total costs, costs by provider,
     * costs by model, daily/monthly trends, and budget alerts.
     *
     * @param  Request  $request  HTTP request with optional filters
     * @return \Illuminate\View\View The cost dashboard view
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        // Date range filter (default to last 30 days)
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        // Overall cost statistics
        $totalStats = DB::table('l_l_m_queries')
            ->select([
                DB::raw('COUNT(*) as total_queries'),
                DB::raw('SUM(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE 0 END) as total_cost'),
                DB::raw('AVG(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE NULL END) as avg_cost'),
                DB::raw('MAX(cost_usd) as max_cost'),
                DB::raw('SUM(CASE WHEN over_budget = 1 THEN 1 ELSE 0 END) as over_budget_queries'),
            ])
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->first();

        // Cost by provider
        $costByProvider = DB::table('l_l_m_queries')
            ->select([
                'provider',
                DB::raw('COUNT(*) as query_count'),
                DB::raw('SUM(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE 0 END) as total_cost'),
                DB::raw('AVG(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE NULL END) as avg_cost'),
            ])
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->groupBy('provider')
            ->orderByDesc('total_cost')
            ->get();

        // Cost by model
        $costByModel = DB::table('l_l_m_queries')
            ->select([
                'provider',
                'model',
                'pricing_tier',
                DB::raw('COUNT(*) as query_count'),
                DB::raw('SUM(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE 0 END) as total_cost'),
                DB::raw('AVG(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE NULL END) as avg_cost'),
            ])
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('cost_usd')
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->groupBy('provider', 'model', 'pricing_tier')
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get();

        // Daily cost trend
        $dailyCosts = DB::table('l_l_m_queries')
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as query_count'),
                DB::raw('SUM(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE 0 END) as total_cost'),
            ])
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        // Most expensive queries
        $expensiveQueries = LLMQuery::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('cost_usd')
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->orderByDesc('cost_usd')
            ->limit(10)
            ->get();

        // Budget alerts
        $budgetLimit = config('llm.budget_limit_usd', null);
        $budgetAlerts = null;
        if ($budgetLimit) {
            $budgetAlerts = [
                'limit' => $budgetLimit,
                'total_cost' => $totalStats->total_cost ?? 0,
                'percentage_used' => $totalStats->total_cost ? ($totalStats->total_cost / $budgetLimit) * 100 : 0,
                'over_budget' => ($totalStats->total_cost ?? 0) > $budgetLimit,
            ];
        }

        return view('costs.index', [
            'totalStats' => $totalStats,
            'costByProvider' => $costByProvider,
            'costByModel' => $costByModel,
            'dailyCosts' => $dailyCosts,
            'expensiveQueries' => $expensiveQueries,
            'budgetAlerts' => $budgetAlerts,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'costCalculator' => $this->costCalculator,
        ]);
    }

    /**
     * Get cost statistics for API endpoint.
     *
     * Returns JSON cost data for charts and AJAX requests.
     *
     * @param  Request  $request  HTTP request with filters
     * @return \Illuminate\Http\JsonResponse JSON response with cost data
     */
    public function stats(Request $request)
    {
        $userId = auth()->id();

        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $stats = DB::table('l_l_m_queries')
            ->select([
                DB::raw('COUNT(*) as total_queries'),
                DB::raw('SUM(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE 0 END) as total_cost'),
                DB::raw('AVG(CASE WHEN cost_usd IS NOT NULL THEN cost_usd ELSE NULL END) as avg_cost'),
                DB::raw('MAX(cost_usd) as max_cost'),
                DB::raw('SUM(CASE WHEN over_budget = 1 THEN 1 ELSE 0 END) as over_budget_queries'),
            ])
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->first();

        return response()->json([
            'total_queries' => $stats->total_queries ?? 0,
            'total_cost' => round($stats->total_cost ?? 0, 6),
            'avg_cost' => round($stats->avg_cost ?? 0, 6),
            'max_cost' => round($stats->max_cost ?? 0, 6),
            'over_budget_queries' => $stats->over_budget_queries ?? 0,
        ]);
    }
}
