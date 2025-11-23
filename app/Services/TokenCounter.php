<?php

namespace App\Services;

/**
 * Token Counter Service
 *
 * Provides accurate token counting for various LLM models, with special support
 * for Claude (Anthropic) models. Uses character-based estimation with model-specific
 * multipliers for accurate token counting without requiring external libraries.
 *
 * This service is essential for managing conversation context windows and preventing
 * context overflow when sending messages to LLMs.
 */
class TokenCounter
{
    /**
     * Model-specific token limits (context windows)
     */
    private const MODEL_LIMITS = [
        // Claude 3.5 family
        'claude-3-5-sonnet-20241022' => 200000,
        'claude-3-5-sonnet-20240620' => 200000,
        'claude-3-5-haiku-20241022' => 200000,

        // Claude 3 family
        'claude-3-opus-20240229' => 200000,
        'claude-3-sonnet-20240229' => 200000,
        'claude-3-haiku-20240307' => 200000,

        // Generic fallbacks by provider
        'claude' => 200000,
        'ollama' => 8192,
        'lmstudio' => 4096,
        'gpt-4' => 128000,
        'gpt-3.5-turbo' => 16385,

        // Default fallback
        'default' => 4096,
    ];

    /**
     * Characters per token ratio for different model families
     * Based on empirical testing and Claude documentation
     */
    private const CHARS_PER_TOKEN = [
        'claude' => 3.5,      // Claude models: ~3.5 chars per token
        'gpt' => 4.0,         // GPT models: ~4 chars per token
        'ollama' => 4.0,      // Ollama: ~4 chars per token
        'lmstudio' => 4.0,    // LM Studio: ~4 chars per token
        'default' => 4.0,     // Default conservative estimate
    ];

    /**
     * Count tokens in a text string
     *
     * @param  string  $text  The text to count tokens for
     * @param  string|null  $model  Optional model name for model-specific counting
     * @return int Estimated token count
     */
    public function count(string $text, ?string $model = null): int
    {
        if (empty($text)) {
            return 0;
        }

        // Get the appropriate chars-per-token ratio for this model
        $charsPerToken = $this->getCharsPerToken($model);

        // Calculate base token count from character count
        $charCount = mb_strlen($text, 'UTF-8');
        $baseTokens = ceil($charCount / $charsPerToken);

        // Add overhead for formatting and structure
        // Messages typically have role indicators, delimiters, etc.
        $overhead = max(4, ceil($baseTokens * 0.02)); // 2% overhead, minimum 4 tokens

        return (int) ($baseTokens + $overhead);
    }

    /**
     * Count tokens in a message array (with role and content)
     *
     * @param  array  $message  Message array with 'role' and 'content' keys
     * @param  string|null  $model  Optional model name for model-specific counting
     * @return int Estimated token count including role overhead
     */
    public function countMessage(array $message, ?string $model = null): int
    {
        $content = $message['content'] ?? '';
        $role = $message['role'] ?? 'user';

        // Count content tokens
        $contentTokens = $this->count($content, $model);

        // Add role overhead (role indicator + formatting)
        $roleOverhead = 4; // Typical overhead for role marker and formatting

        return $contentTokens + $roleOverhead;
    }

    /**
     * Count tokens in an array of messages
     *
     * @param  array  $messages  Array of messages with 'role' and 'content'
     * @param  string|null  $model  Optional model name for model-specific counting
     * @return int Total estimated token count
     */
    public function countMessages(array $messages, ?string $model = null): int
    {
        $totalTokens = 0;

        foreach ($messages as $message) {
            $totalTokens += $this->countMessage($message, $model);
        }

        // Add overhead for message array structure
        $structureOverhead = count($messages) > 0 ? 3 : 0;

        return $totalTokens + $structureOverhead;
    }

    /**
     * Get the context window limit for a model
     *
     * @param  string|null  $model  Model name or provider
     * @return int Maximum context window size in tokens
     */
    public function getContextLimit(?string $model = null): int
    {
        if (empty($model)) {
            return self::MODEL_LIMITS['default'];
        }

        // Check for exact model match
        if (isset(self::MODEL_LIMITS[$model])) {
            return self::MODEL_LIMITS[$model];
        }

        // Check if model name contains a known provider
        foreach (['claude', 'gpt-4', 'gpt-3.5', 'ollama', 'lmstudio'] as $provider) {
            if (str_contains(strtolower($model), $provider)) {
                return self::MODEL_LIMITS[$provider] ?? self::MODEL_LIMITS['default'];
            }
        }

        return self::MODEL_LIMITS['default'];
    }

    /**
     * Get safe context limit (with buffer for response)
     *
     * Returns the context limit minus a buffer to allow for the model's response.
     * For Claude models, we reserve ~4000 tokens for the response.
     *
     * @param  string|null  $model  Model name or provider
     * @return int Safe context limit for input
     */
    public function getSafeContextLimit(?string $model = null): int
    {
        $fullLimit = $this->getContextLimit($model);

        // Reserve tokens for response based on model type
        $responseBuffer = $this->isClaudeModel($model) ? 4000 : 2000;

        return max(1000, $fullLimit - $responseBuffer);
    }

    /**
     * Calculate how many tokens are remaining in context
     *
     * @param  int  $currentTokens  Current token count
     * @param  string|null  $model  Model name or provider
     * @return int Remaining tokens available
     */
    public function getRemainingTokens(int $currentTokens, ?string $model = null): int
    {
        $limit = $this->getSafeContextLimit($model);

        return max(0, $limit - $currentTokens);
    }

    /**
     * Calculate percentage of context used
     *
     * @param  int  $currentTokens  Current token count
     * @param  string|null  $model  Model name or provider
     * @return float Percentage of context used (0-100)
     */
    public function getContextUsagePercent(int $currentTokens, ?string $model = null): float
    {
        $limit = $this->getSafeContextLimit($model);
        if ($limit === 0) {
            return 100.0;
        }

        return min(100.0, ($currentTokens / $limit) * 100);
    }

    /**
     * Check if context is approaching the limit
     *
     * @param  int  $currentTokens  Current token count
     * @param  string|null  $model  Model name or provider
     * @param  float  $threshold  Threshold percentage (default 80%)
     * @return bool True if approaching limit
     */
    public function isApproachingLimit(int $currentTokens, ?string $model = null, float $threshold = 80.0): bool
    {
        return $this->getContextUsagePercent($currentTokens, $model) >= $threshold;
    }

    /**
     * Check if context has exceeded the limit
     *
     * @param  int  $currentTokens  Current token count
     * @param  string|null  $model  Model name or provider
     * @return bool True if exceeded
     */
    public function isOverLimit(int $currentTokens, ?string $model = null): bool
    {
        return $currentTokens > $this->getSafeContextLimit($model);
    }

    /**
     * Get warning level for context usage
     *
     * @param  int  $currentTokens  Current token count
     * @param  string|null  $model  Model name or provider
     * @return string 'safe', 'warning', 'critical', or 'exceeded'
     */
    public function getWarningLevel(int $currentTokens, ?string $model = null): string
    {
        $percentage = $this->getContextUsagePercent($currentTokens, $model);

        if ($percentage >= 100) {
            return 'exceeded';
        } elseif ($percentage >= 90) {
            return 'critical';
        } elseif ($percentage >= 75) {
            return 'warning';
        } else {
            return 'safe';
        }
    }

    /**
     * Get chars-per-token ratio for a model
     *
     * @param  string|null  $model  Model name or provider
     * @return float Characters per token ratio
     */
    private function getCharsPerToken(?string $model = null): float
    {
        if (empty($model)) {
            return self::CHARS_PER_TOKEN['default'];
        }

        $modelLower = strtolower($model);

        // Check for model family matches
        if (str_contains($modelLower, 'claude')) {
            return self::CHARS_PER_TOKEN['claude'];
        }
        if (str_contains($modelLower, 'gpt')) {
            return self::CHARS_PER_TOKEN['gpt'];
        }
        if (str_contains($modelLower, 'ollama')) {
            return self::CHARS_PER_TOKEN['ollama'];
        }
        if (str_contains($modelLower, 'lm')) {
            return self::CHARS_PER_TOKEN['lmstudio'];
        }

        return self::CHARS_PER_TOKEN['default'];
    }

    /**
     * Check if model is a Claude model
     *
     * @param  string|null  $model  Model name
     * @return bool True if Claude model
     */
    private function isClaudeModel(?string $model = null): bool
    {
        if (empty($model)) {
            return false;
        }

        return str_contains(strtolower($model), 'claude');
    }

    /**
     * Format token count for display
     *
     * @param  int  $tokens  Token count
     * @param  bool  $abbreviated  Use abbreviated format (K for thousands)
     * @return string Formatted token count
     */
    public function formatTokenCount(int $tokens, bool $abbreviated = true): string
    {
        if (! $abbreviated || $tokens < 1000) {
            return number_format($tokens);
        }

        if ($tokens < 1000000) {
            return number_format($tokens / 1000, 1).'K';
        }

        return number_format($tokens / 1000000, 1).'M';
    }

    /**
     * Get model display name
     *
     * @param  string|null  $model  Model name
     * @return string Display name
     */
    public function getModelDisplayName(?string $model = null): string
    {
        if (empty($model)) {
            return 'Unknown Model';
        }

        // Handle common model patterns
        $patterns = [
            '/claude-3-5-sonnet/' => 'Claude 3.5 Sonnet',
            '/claude-3-5-haiku/' => 'Claude 3.5 Haiku',
            '/claude-3-opus/' => 'Claude 3 Opus',
            '/claude-3-sonnet/' => 'Claude 3 Sonnet',
            '/claude-3-haiku/' => 'Claude 3 Haiku',
            '/gpt-4-turbo/' => 'GPT-4 Turbo',
            '/gpt-4/' => 'GPT-4',
            '/gpt-3.5-turbo/' => 'GPT-3.5 Turbo',
        ];

        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $model)) {
                return $name;
            }
        }

        return $model;
    }
}
