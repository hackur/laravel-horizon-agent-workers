<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds full-text search indexes for SQLite using FTS5 virtual tables.
     * This creates a searchable index for conversation titles and message content,
     * enabling fast full-text search queries.
     */
    public function up(): void
    {
        // SQLite FTS5 (Full-Text Search) configuration
        if (DB::getDriverName() === 'sqlite') {
            // Create FTS5 virtual table for conversation_messages
            DB::statement("
                CREATE VIRTUAL TABLE conversation_messages_fts USING fts5(
                    id UNINDEXED,
                    conversation_id UNINDEXED,
                    role UNINDEXED,
                    content,
                    created_at UNINDEXED,
                    content='conversation_messages',
                    content_rowid='id'
                )
            ");

            // Create triggers to keep FTS index in sync with conversation_messages table
            DB::statement('
                CREATE TRIGGER conversation_messages_ai AFTER INSERT ON conversation_messages BEGIN
                    INSERT INTO conversation_messages_fts(rowid, id, conversation_id, role, content, created_at)
                    VALUES (new.id, new.id, new.conversation_id, new.role, new.content, new.created_at);
                END
            ');

            DB::statement("
                CREATE TRIGGER conversation_messages_ad AFTER DELETE ON conversation_messages BEGIN
                    INSERT INTO conversation_messages_fts(conversation_messages_fts, rowid, id, conversation_id, role, content, created_at)
                    VALUES('delete', old.id, old.id, old.conversation_id, old.role, old.content, old.created_at);
                END
            ");

            DB::statement("
                CREATE TRIGGER conversation_messages_au AFTER UPDATE ON conversation_messages BEGIN
                    INSERT INTO conversation_messages_fts(conversation_messages_fts, rowid, id, conversation_id, role, content, created_at)
                    VALUES('delete', old.id, old.id, old.conversation_id, old.role, old.content, old.created_at);
                    INSERT INTO conversation_messages_fts(rowid, id, conversation_id, role, content, created_at)
                    VALUES (new.id, new.id, new.conversation_id, new.role, new.content, new.created_at);
                END
            ");

            // Populate FTS index with existing data
            DB::statement('
                INSERT INTO conversation_messages_fts(rowid, id, conversation_id, role, content, created_at)
                SELECT id, id, conversation_id, role, content, created_at FROM conversation_messages
            ');

            // Create FTS5 virtual table for conversations (title search)
            DB::statement("
                CREATE VIRTUAL TABLE conversations_fts USING fts5(
                    id UNINDEXED,
                    user_id UNINDEXED,
                    title,
                    provider UNINDEXED,
                    model UNINDEXED,
                    created_at UNINDEXED,
                    content='conversations',
                    content_rowid='id'
                )
            ");

            // Create triggers to keep FTS index in sync with conversations table
            DB::statement('
                CREATE TRIGGER conversations_ai AFTER INSERT ON conversations BEGIN
                    INSERT INTO conversations_fts(rowid, id, user_id, title, provider, model, created_at)
                    VALUES (new.id, new.id, new.user_id, new.title, new.provider, new.model, new.created_at);
                END
            ');

            DB::statement("
                CREATE TRIGGER conversations_ad AFTER DELETE ON conversations BEGIN
                    INSERT INTO conversations_fts(conversations_fts, rowid, id, user_id, title, provider, model, created_at)
                    VALUES('delete', old.id, old.id, old.user_id, old.title, old.provider, old.model, old.created_at);
                END
            ");

            DB::statement("
                CREATE TRIGGER conversations_au AFTER UPDATE ON conversations BEGIN
                    INSERT INTO conversations_fts(conversations_fts, rowid, id, user_id, title, provider, model, created_at)
                    VALUES('delete', old.id, old.id, old.user_id, old.title, old.provider, old.model, old.created_at);
                    INSERT INTO conversations_fts(rowid, id, user_id, title, provider, model, created_at)
                    VALUES (new.id, new.id, new.user_id, new.title, new.provider, new.model, new.created_at);
                END
            ");

            // Populate FTS index with existing data
            DB::statement('
                INSERT INTO conversations_fts(rowid, id, user_id, title, provider, model, created_at)
                SELECT id, id, user_id, title, provider, model, created_at FROM conversations
            ');
        }

        // Note: All regular indexes are already created by other migrations
        // (2025_11_23_200100_add_performance_indexes_to_all_tables.php)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Drop triggers first
            DB::statement('DROP TRIGGER IF EXISTS conversation_messages_ai');
            DB::statement('DROP TRIGGER IF EXISTS conversation_messages_ad');
            DB::statement('DROP TRIGGER IF EXISTS conversation_messages_au');
            DB::statement('DROP TRIGGER IF EXISTS conversations_ai');
            DB::statement('DROP TRIGGER IF EXISTS conversations_ad');
            DB::statement('DROP TRIGGER IF EXISTS conversations_au');

            // Drop FTS tables
            DB::statement('DROP TABLE IF EXISTS conversation_messages_fts');
            DB::statement('DROP TABLE IF EXISTS conversations_fts');
        }

        // Note: Regular indexes are not dropped here as they're managed by other migrations
    }
};
