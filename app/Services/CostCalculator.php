<?php

namespace App\Services;

/**
 * Service for calculating API costs for LLM providers.
 *
 * Provides methods to calculate costs based on token usage and model pricing
 * for various LLM providers. Currently supports Anthropic Claude models with
 * accurate pricing based on current API rates.
 */
class CostCalculator
{
    /**
     * Pricing configuration for different providers and models.
     * Prices are in USD per million tokens.
     *
     * Structure:
     * [
     *   'provider' => [
     *     'model_name' => [
     *       'input' => price_per_million_input_tokens,
     *       'output' => price_per_million_output_tokens,
     *       'tier' => 'tier_name'
     *     ]
     *   ]
     * ]
     */
    protected array $pricing = [
        'claude' => [
            // Claude 3.5 Sonnet - Latest and most capable
            'claude-3-5-sonnet-20241022' => [
                'input' => 3.00,
                'output' => 15.00,
                'tier' => 'sonnet',
            ],
            'claude-3-5-sonnet-latest' => [
                'input' => 3.00,
                'output' => 15.00,
                'tier' => 'sonnet',
            ],

            // Claude 3.5 Haiku - Fast and efficient
            'claude-3-5-haiku-20241022' => [
                'input' => 0.80,
                'output' => 4.00,
                'tier' => 'haiku',
            ],
            'claude-3-5-haiku-latest' => [
                'input' => 0.80,
                'output' => 4.00,
                'tier' => 'haiku',
            ],

            // Claude 3 Opus - Most powerful
            'claude-3-opus-20240229' => [
                'input' => 15.00,
                'output' => 75.00,
                'tier' => 'opus',
            ],
            'claude-3-opus-latest' => [
                'input' => 15.00,
                'output' => 75.00,
                'tier' => 'opus',
            ],

            // Claude 3 Sonnet (older)
            'claude-3-sonnet-20240229' => [
                'input' => 3.00,
                'output' => 15.00,
                'tier' => 'sonnet',
            ],

            // Claude 3 Haiku (older)
            'claude-3-haiku-20240307' => [
                'input' => 0.25,
                'output' => 1.25,
                'tier' => 'haiku',
            ],
        ],
    ];

    /**
     * Calculate the cost for an LLM query based on usage statistics.
     *
     * @param  string  $provider  The LLM provider (e.g., 'claude', 'ollama')
     * @param  string|null  $model  The specific model used
     * @param  array  $usageStats  Token usage statistics containing:
     *                              - input_tokens (int): Number of input tokens
     *                              - output_tokens (int): Number of output tokens
     *                              - total_tokens (int): Total tokens (optional)
     * @return array Cost breakdown containing:
     *               - input_cost_usd (float): Cost for input tokens
     *               - output_cost_usd (float): Cost for output tokens
     *               - total_cost_usd (float): Total cost in USD
     *               - pricing_tier (string|null): Pricing tier/model type
     *               - calculated (bool): Whether cost was calculated
     */
    public function calculateCost(string $provider, ?string $model, array $usageStats): array
    {
        // Default response for providers/models without pricing
        $defaultResponse = [
            'input_cost_usd' => 0.00,
            'output_cost_usd' => 0.00,
            'total_cost_usd' => 0.00,
            'pricing_tier' => null,
            'calculated' => false,
        ];

        // Check if provider has pricing configured
        if (! isset($this->pricing[$provider])) {
            return $defaultResponse;
        }

        // Check if model has pricing configured
        if (! $model || ! isset($this->pricing[$provider][$model])) {
            // Try to find a default model for the provider
            $defaultModel = $this->getDefaultModel($provider);
            if (! $defaultModel) {
                return $defaultResponse;
            }
            $pricingConfig = $this->pricing[$provider][$defaultModel];
        } else {
            $pricingConfig = $this->pricing[$provider][$model];
        }

        // Extract token counts from usage stats
        $inputTokens = $usageStats['input_tokens'] ?? $usageStats['prompt_tokens'] ?? 0;
        $outputTokens = $usageStats['output_tokens'] ?? $usageStats['completion_tokens'] ?? 0;

        // Calculate costs (prices are per million tokens)
        $inputCost = ($inputTokens / 1_000_000) * $pricingConfig['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricingConfig['output'];
        $totalCost = $inputCost + $outputCost;

        return [
            'input_cost_usd' => round($inputCost, 6),
            'output_cost_usd' => round($outputCost, 6),
            'total_cost_usd' => round($totalCost, 6),
            'pricing_tier' => $pricingConfig['tier'] ?? null,
            'calculated' => true,
        ];
    }

    /**
     * Get the default model for a provider when no specific model is specified.
     *
     * @param  string  $provider  The LLM provider
     * @return string|null The default model name or null if no default
     */
    protected function getDefaultModel(string $provider): ?string
    {
        $defaults = [
            'claude' => 'claude-3-5-sonnet-20241022',
        ];

        return $defaults[$provider] ?? null;
    }

    /**
     * Get pricing information for a specific provider and model.
     *
     * @param  string  $provider  The LLM provider
     * @param  string|null  $model  The specific model (optional)
     * @return array|null Pricing configuration or null if not found
     */
    public function getPricing(string $provider, ?string $model = null): ?array
    {
        if (! isset($this->pricing[$provider])) {
            return null;
        }

        if ($model && isset($this->pricing[$provider][$model])) {
            return $this->pricing[$provider][$model];
        }

        // Return default model pricing if no specific model provided
        $defaultModel = $this->getDefaultModel($provider);
        if ($defaultModel && isset($this->pricing[$provider][$defaultModel])) {
            return $this->pricing[$provider][$defaultModel];
        }

        return null;
    }

    /**
     * Get all available models for a provider with their pricing.
     *
     * @param  string  $provider  The LLM provider
     * @return array Array of models with their pricing configurations
     */
    public function getProviderModels(string $provider): array
    {
        return $this->pricing[$provider] ?? [];
    }

    /**
     * Estimate cost before making a query.
     *
     * Provides an estimated cost range based on estimated token counts.
     * Useful for budget warnings and cost awareness.
     *
     * @param  string  $provider  The LLM provider
     * @param  string|null  $model  The specific model
     * @param  int  $estimatedInputTokens  Estimated number of input tokens
     * @param  int  $estimatedOutputTokens  Estimated number of output tokens
     * @return array Cost estimate containing:
     *               - estimated_input_cost_usd (float)
     *               - estimated_output_cost_usd (float)
     *               - estimated_total_cost_usd (float)
     *               - pricing_tier (string|null)
     */
    public function estimateCost(
        string $provider,
        ?string $model,
        int $estimatedInputTokens,
        int $estimatedOutputTokens
    ): array {
        return $this->calculateCost($provider, $model, [
            'input_tokens' => $estimatedInputTokens,
            'output_tokens' => $estimatedOutputTokens,
            'total_tokens' => $estimatedInputTokens + $estimatedOutputTokens,
        ]);
    }

    /**
     * Format cost for display.
     *
     * Formats a cost value for user-friendly display with appropriate precision.
     *
     * @param  float  $cost  The cost in USD
     * @param  bool  $showCurrency  Whether to include currency symbol
     * @return string Formatted cost string
     */
    public function formatCost(float $cost, bool $showCurrency = true): string
    {
        $prefix = $showCurrency ? '$' : '';

        // For very small costs, show more precision
        if ($cost < 0.01) {
            return $prefix.number_format($cost, 4);
        }

        // For normal costs, show 2 decimal places
        return $prefix.number_format($cost, 2);
    }

    /**
     * Check if a cost exceeds a budget limit.
     *
     * @param  float  $cost  The cost to check
     * @param  float  $budget  The budget limit
     * @return bool True if cost exceeds budget
     */
    public function exceedsBudget(float $cost, float $budget): bool
    {
        return $cost > $budget;
    }

    /**
     * Calculate total cost for multiple queries.
     *
     * @param  array  $costs  Array of cost values
     * @return float Total cost
     */
    public function sumCosts(array $costs): float
    {
        return round(array_sum($costs), 6);
    }

    /**
     * Get all supported providers.
     *
     * @return array List of provider names with pricing configured
     */
    public function getSupportedProviders(): array
    {
        return array_keys($this->pricing);
    }

    /**
     * Check if a provider has pricing configured.
     *
     * @param  string  $provider  The provider to check
     * @return bool True if provider has pricing configured
     */
    public function hasProviderPricing(string $provider): bool
    {
        return isset($this->pricing[$provider]) && ! empty($this->pricing[$provider]);
    }
}
