<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds performance indexes to optimize common query patterns:
     * - Composite indexes for filtering + sorting operations
     * - Indexes on foreign keys not already indexed
     * - Indexes on commonly filtered columns (status, provider, role, etc.)
     * - Indexes on timestamp columns used for sorting
     */
    public function up(): void
    {
        // ==========================================
        // l_l_m_queries table optimizations
        // ==========================================
        Schema::table('l_l_m_queries', function (Blueprint $table) {
            // Composite indexes for common query patterns
            // Pattern: Filter by user + status + sort by created_at
            $table->index(['user_id', 'status', 'created_at'], 'llm_queries_user_status_created_idx');

            // Pattern: Filter by conversation + sort by created_at
            $table->index(['conversation_id', 'created_at'], 'llm_queries_conversation_created_idx');

            // Pattern: Filter by provider + status (for provider-specific dashboards)
            $table->index(['provider', 'status'], 'llm_queries_provider_status_idx');

            // Pattern: Filter by status + sort by created_at (for queue monitoring)
            $table->index(['status', 'created_at'], 'llm_queries_status_created_idx');

            // Index on completed_at for analytics and completed query filtering
            $table->index('completed_at');

            // Index on updated_at for recent activity queries
            $table->index('updated_at');

            // Index on finish_reason for analyzing completion patterns
            $table->index('finish_reason');
        });

        // ==========================================
        // conversations table optimizations
        // ==========================================
        Schema::table('conversations', function (Blueprint $table) {
            // Composite indexes for common query patterns
            // Pattern: Filter by user + sort by last_message_at (conversation list)
            $table->index(['user_id', 'last_message_at'], 'conversations_user_last_message_idx');

            // Pattern: Filter by team + sort by last_message_at (team conversations)
            $table->index(['team_id', 'last_message_at'], 'conversations_team_last_message_idx');

            // Pattern: Filter by user + provider (provider-specific conversations)
            $table->index(['user_id', 'provider'], 'conversations_user_provider_idx');

            // Pattern: Filter by provider + sort by created_at
            $table->index(['provider', 'created_at'], 'conversations_provider_created_idx');

            // Index on last_message_at for sorting recent conversations
            $table->index('last_message_at');

            // Index on updated_at for recently updated conversations
            $table->index('updated_at');

            // Index on provider for filtering by LLM provider
            $table->index('provider');
        });

        // ==========================================
        // conversation_messages table optimizations
        // ==========================================
        Schema::table('conversation_messages', function (Blueprint $table) {
            // Composite indexes for common query patterns
            // Pattern: Filter by conversation + sort by created_at (message history)
            // Note: This index already exists as 'conversation_id' and 'created_at' separately
            // We'll add a composite for better performance
            $table->index(['conversation_id', 'created_at'], 'conv_messages_conversation_created_idx');

            // Pattern: Filter by conversation + role (get all user/assistant messages)
            $table->index(['conversation_id', 'role'], 'conv_messages_conversation_role_idx');

            // Index on llm_query_id if not already indexed (for linking messages to queries)
            $table->index('llm_query_id');

            // Index on role for filtering by message type
            $table->index('role');

            // Index on updated_at for recently updated messages
            $table->index('updated_at');
        });

        // ==========================================
        // team_user table optimizations
        // ==========================================
        Schema::table('team_user', function (Blueprint $table) {
            // Individual foreign key indexes (not already indexed)
            $table->index('team_id');
            $table->index('user_id');

            // Index on role for filtering team members by role
            $table->index('role');

            // Composite index for team + role queries
            $table->index(['team_id', 'role'], 'team_user_team_role_idx');
        });

        // ==========================================
        // teams table optimizations
        // ==========================================
        Schema::table('teams', function (Blueprint $table) {
            // Composite index for user's personal team lookup
            $table->index(['user_id', 'personal_team'], 'teams_user_personal_idx');

            // Index on personal_team for filtering
            $table->index('personal_team');

            // Index on created_at for sorting teams by creation date
            $table->index('created_at');
        });

        // ==========================================
        // team_invitations table optimizations
        // ==========================================
        if (Schema::hasTable('team_invitations')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                // Index on email for looking up invitations
                if (Schema::hasColumn('team_invitations', 'email')) {
                    $table->index('email');
                }

                // Composite index for team + email
                if (Schema::hasColumn('team_invitations', 'team_id') &&
                    Schema::hasColumn('team_invitations', 'email')) {
                    $table->index(['team_id', 'email'], 'team_invitations_team_email_idx');
                }
            });
        }

        // ==========================================
        // users table optimizations
        // ==========================================
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Note: email is typically already unique indexed
                // Index on created_at for user registration analytics
                if (Schema::hasColumn('users', 'created_at') &&
                    ! $this->indexExists('users', ['created_at'])) {
                    $table->index('created_at');
                }

                // Index on updated_at for recently active users
                if (Schema::hasColumn('users', 'updated_at') &&
                    ! $this->indexExists('users', ['updated_at'])) {
                    $table->index('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ==========================================
        // l_l_m_queries table - drop indexes
        // ==========================================
        Schema::table('l_l_m_queries', function (Blueprint $table) {
            $table->dropIndex('llm_queries_user_status_created_idx');
            $table->dropIndex('llm_queries_conversation_created_idx');
            $table->dropIndex('llm_queries_provider_status_idx');
            $table->dropIndex('llm_queries_status_created_idx');
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['finish_reason']);
        });

        // ==========================================
        // conversations table - drop indexes
        // ==========================================
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_user_last_message_idx');
            $table->dropIndex('conversations_team_last_message_idx');
            $table->dropIndex('conversations_user_provider_idx');
            $table->dropIndex('conversations_provider_created_idx');
            $table->dropIndex(['last_message_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['provider']);
        });

        // ==========================================
        // conversation_messages table - drop indexes
        // ==========================================
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropIndex('conv_messages_conversation_created_idx');
            $table->dropIndex('conv_messages_conversation_role_idx');
            $table->dropIndex(['llm_query_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['updated_at']);
        });

        // ==========================================
        // team_user table - drop indexes
        // ==========================================
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropIndex(['team_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['role']);
            $table->dropIndex('team_user_team_role_idx');
        });

        // ==========================================
        // teams table - drop indexes
        // ==========================================
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('teams_user_personal_idx');
            $table->dropIndex(['personal_team']);
            $table->dropIndex(['created_at']);
        });

        // ==========================================
        // team_invitations table - drop indexes
        // ==========================================
        if (Schema::hasTable('team_invitations')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                if ($this->indexExists('team_invitations', ['email'])) {
                    $table->dropIndex(['email']);
                }
                if ($this->indexExists('team_invitations', 'team_invitations_team_email_idx')) {
                    $table->dropIndex('team_invitations_team_email_idx');
                }
            });
        }

        // ==========================================
        // users table - drop indexes
        // ==========================================
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', ['created_at'])) {
                    $table->dropIndex(['created_at']);
                }
                if ($this->indexExists('users', ['updated_at'])) {
                    $table->dropIndex(['updated_at']);
                }
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, array|string $columns): bool
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }
};
