# Full-Text Search Implementation Guide

## Overview

This implementation adds comprehensive full-text search capabilities to the conversations feature, allowing users to search within both conversation titles and message content. The search is powered by SQLite's FTS5 (Full-Text Search) engine for optimal performance.

## Features Implemented

### 1. Full-Text Search Engine
- **SQLite FTS5** virtual tables for efficient full-text indexing
- Automatic synchronization via database triggers
- Searches across both conversation titles and message content
- Support for phrase searches and single-word queries

### 2. Advanced Search Filters
- **Search Type Toggle**: Choose between content search (messages) or title-only search
- **Provider Filter**: Filter conversations by LLM provider (Claude, Ollama, LM Studio, Local Command)
- **Status Filter**: Filter by query status (pending, processing, completed, failed)
- **Date Range Filter**: Search within specific date ranges
- **Sort Options**: Sort by most recent, oldest first, or alphabetically by title

### 3. Search Result Highlighting
- Automatic highlighting of search terms in results
- Context-aware excerpts showing matched content
- Case-insensitive matching
- Visual emphasis with yellow background for matched terms

### 4. Enhanced User Interface
- Collapsible advanced filters panel with localStorage persistence
- Active filters display with visual badges
- Real-time result count
- Auto-submit on filter changes
- Clear search functionality
- Responsive design for mobile and desktop

## Files Modified

### Database Migrations
- **`database/migrations/2025_11_23_233833_add_full_text_search_to_conversations_and_messages.php`**
  - Creates FTS5 virtual tables for conversations and messages
  - Sets up automatic triggers for index synchronization
  - Adds performance indexes for common query patterns
  - Populates indexes with existing data

### Models
- **`app/Models/Conversation.php`**
  - `scopeFullTextSearch()`: Full-text search across titles and messages
  - `scopeDateRange()`: Filter by date range
  - `scopeByStatus()`: Filter by query status
  - `prepareFtsSearchTerm()`: Prepares search terms for FTS5

- **`app/Models/ConversationMessage.php`**
  - `getSearchExcerpt()`: Returns context-aware excerpt around search match
  - `highlightSearchTerm()`: Highlights search terms in text with HTML marks
  - `getTruncatedContent()`: Returns truncated content for display
  - Added `HasFactory` trait for testing

### Controllers
- **`app/Http/Controllers/ConversationController.php`**
  - Enhanced `index()` method with full-text search support
  - Support for multiple filter combinations
  - Query string persistence for pagination
  - Search type selection (content vs. title)

### Views
- **`resources/views/conversations/index.blade.php`**
  - Complete redesign of search interface
  - Collapsible advanced filters section
  - Active filters display with badges
  - Search result highlighting in titles and previews
  - JavaScript for enhanced UX (filter persistence, auto-submit)

### Factories
- **`database/factories/ConversationMessageFactory.php`**
  - New factory for testing ConversationMessage model

### Tests
- **`tests/Unit/FullTextSearchTest.php`**
  - Comprehensive test suite covering:
    - Full-text search in message content
    - Full-text search in titles
    - Phrase search handling
    - Search excerpt generation
    - Search term highlighting
    - Date range filtering
    - Provider filtering
    - User-specific search results
    - Empty search handling

## Database Schema

### FTS5 Virtual Tables

#### conversation_messages_fts
- Indexes conversation message content for full-text search
- Automatically synced via triggers on INSERT, UPDATE, DELETE

#### conversations_fts
- Indexes conversation titles for full-text search
- Automatically synced via triggers on INSERT, UPDATE, DELETE

### Regular Indexes Added
- `l_l_m_queries.status` - For status filtering
- `conversations.provider` - For provider filtering
- `conversations(user_id, created_at)` - For user-specific date queries
- `conversations(user_id, last_message_at)` - For recent activity queries
- `conversation_messages(conversation_id, created_at)` - For message ordering
- `conversation_messages.role` - For role-based queries

## Usage Examples

### Basic Search
```php
// Search for conversations containing "Laravel"
$conversations = Conversation::where('user_id', auth()->id())
    ->fullTextSearch('Laravel')
    ->paginate(15);
```

### Advanced Search with Filters
```php
// Search with multiple filters
$conversations = Conversation::where('user_id', auth()->id())
    ->fullTextSearch('API integration')
    ->byProvider('claude')
    ->dateRange('2025-01-01', '2025-12-31')
    ->byStatus('completed')
    ->latest('last_message_at')
    ->paginate(15);
```

### Search Excerpt with Highlighting
```php
// Get highlighted excerpt
$message = ConversationMessage::find(1);
$excerpt = $message->getSearchExcerpt('Laravel', 150);
$highlighted = $message->highlightSearchTerm($excerpt, 'Laravel');
```

## Search Syntax

### Single Word
- Input: `Laravel`
- Searches for: Any occurrence of "Laravel" (case-insensitive)

### Phrase Search
- Input: `Laravel framework`
- Searches for: Exact phrase "Laravel framework"

### Title-Only Search
- Select "Search in titles only" radio button
- Only searches conversation titles, not message content

## Performance Considerations

1. **FTS5 Indexes**: Automatically maintained by SQLite triggers
2. **Composite Indexes**: Optimized for common query patterns
3. **Pagination**: Results are paginated to 15 items per page
4. **Query String Persistence**: Filters persist across pagination

## UI/UX Features

1. **Advanced Filters Panel**
   - Collapsible to save screen space
   - State persists in localStorage
   - Automatically expands when filters are active

2. **Active Filters Display**
   - Visual badges showing current filters
   - Color-coded by filter type
   - One-click clear functionality

3. **Search Results**
   - Result count displayed
   - Matched terms highlighted in yellow
   - Context-aware excerpts
   - Empty state messages

4. **Auto-Submit**
   - Filter dropdowns automatically submit on change
   - Keyboard support (Enter key to search)
   - Clear button in search input

## Testing

Run the full-text search test suite:

```bash
php artisan test tests/Unit/FullTextSearchTest.php
```

All 10 tests cover:
- Full-text search functionality
- Search highlighting and excerpts
- Filter combinations
- User isolation
- Edge cases (empty search, no results)

## API Response Format

### Controller Response Variables
```php
[
    'conversations' => $conversations,  // Paginated results
    'providers' => $providers,          // Available LLM providers
    'searchTerm' => $searchTerm,        // Current search term
    'filters' => [                      // Active filters
        'provider' => 'claude',
        'status' => 'completed',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'search_type' => 'content',
        'sort_by' => 'recent',
    ],
]
```

## Security Considerations

1. **Input Sanitization**: Search terms limited to 255 characters
2. **User Isolation**: All queries filtered by `user_id`
3. **SQL Injection Protection**: Uses parameter binding
4. **XSS Prevention**: Blade templates auto-escape output (except explicitly marked safe HTML)

## Migration Instructions

1. Run the migration:
   ```bash
   php artisan migrate
   ```

2. The migration will:
   - Create FTS5 virtual tables
   - Set up automatic triggers
   - Add performance indexes
   - Populate indexes with existing data

3. No manual reindexing required - triggers keep indexes synchronized

## Rollback

To rollback the full-text search implementation:

```bash
php artisan migrate:rollback
```

This will:
- Drop FTS5 virtual tables
- Remove triggers
- Drop added indexes

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- JavaScript required for enhanced features
- Gracefully degrades without JavaScript (basic search still works)

## Future Enhancements

Potential improvements for consideration:
1. Fuzzy search for typo tolerance
2. Search result ranking/relevance scoring
3. Search history and saved searches
4. Export search results
5. Advanced Boolean operators (AND, OR, NOT)
6. Multi-language search support
7. Search analytics and popular queries

## Maintenance

### Reindexing (if needed)
If FTS indexes become corrupted or out of sync:

```sql
-- Drop and recreate FTS tables
DROP TABLE IF EXISTS conversation_messages_fts;
DROP TABLE IF EXISTS conversations_fts;

-- Then re-run the migration
php artisan migrate:rollback --step=1
php artisan migrate
```

### Monitoring Performance
Monitor search query performance:

```sql
-- Check FTS table sizes
SELECT name, seq FROM sqlite_sequence WHERE name LIKE '%fts%';

-- Analyze query performance
EXPLAIN QUERY PLAN
SELECT * FROM conversations_fts WHERE conversations_fts MATCH 'search term';
```

## Support

For issues or questions:
1. Check the test suite for usage examples
2. Review the migration file for database schema
3. Inspect controller and model code for query patterns
4. Verify SQLite FTS5 is available (it's built into modern SQLite)

---

**Implementation Date**: November 23, 2025
**Laravel Version**: 12.x
**SQLite FTS Version**: FTS5
**Test Coverage**: 10 unit tests, 23 assertions
