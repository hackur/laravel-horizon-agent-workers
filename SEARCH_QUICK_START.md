# Full-Text Search Quick Start Guide

## Quick Overview

The conversation search feature allows you to find conversations by searching through titles and message content with advanced filtering options.

## Basic Usage

### Simple Text Search

1. Navigate to the Conversations page
2. Type your search term in the search box
3. Click "Search" or press Enter
4. Results show conversations containing your search term

**Example**: Search for "Laravel" to find all conversations mentioning Laravel

### Search Types

#### Search in Messages (Default)
- Searches within conversation titles AND message content
- Best for finding specific topics or keywords discussed

#### Search in Titles Only
- Searches only conversation titles
- Faster, best when you remember the conversation title

## Advanced Filters

Click "Show" under "Advanced Filters" to access:

### Provider Filter
Filter conversations by LLM provider:
- Claude API
- Ollama
- LM Studio
- Local Command

### Status Filter
Filter by query processing status:
- Pending
- Processing
- Completed
- Failed

### Date Range
Find conversations within specific dates:
- Start Date: earliest date to include
- End Date: latest date to include

### Sort Options
- **Most Recent**: Shows newest conversations first (default)
- **Oldest First**: Shows oldest conversations first
- **Title A-Z**: Alphabetical by conversation title

## Search Tips

### Finding Exact Phrases
- Multi-word searches are treated as exact phrases
- Example: "error handling" finds that exact phrase

### Single Word Searches
- Finds any occurrence of the word
- Case-insensitive
- Example: "api" matches "API", "api", "Api"

### Combining Filters
You can combine multiple filters:
1. Search for "authentication"
2. Filter by Provider: "Claude API"
3. Filter by Status: "Completed"
4. Set Date Range: Last week
5. Results show only completed Claude conversations about authentication from last week

## Understanding Results

### Result Cards Show
- **Conversation Title**: Highlighted if it matches search
- **Preview**: First message excerpt with highlighted matches
- **Message Count**: Number of messages in conversation
- **Provider Badge**: Which LLM provider was used
- **Timestamp**: When last updated

### Yellow Highlights
- Search terms are highlighted in yellow
- Appears in both titles and message previews
- Makes it easy to see why a conversation matched

### Result Count
- Shows total number of matching conversations
- Example: "Found 5 conversation(s) matching your search"

## Active Filters

### Filter Badges
Active filters appear as colored badges:
- 🔵 Blue: Search term
- 🟢 Green: Provider filter
- 🟣 Purple: Status filter
- 🟠 Orange: Date range

### Clearing Filters
- Click "Clear All" to remove all filters at once
- Or remove individual filters by adjusting them

## Keyboard Shortcuts

- **Enter**: Submit search from search box
- Search input auto-focuses for quick searching

## Common Use Cases

### Find a Specific Conversation
1. Remember a keyword from the conversation
2. Type it in search
3. Scan highlighted results

### Review Completed Work
1. Filter by Status: "Completed"
2. Set date range for the period you want
3. Optionally add provider filter

### Debug Failed Queries
1. Filter by Status: "Failed"
2. Optionally filter by Provider to narrow down
3. Review error messages in conversations

### Find Discussions on a Topic
1. Search for the topic keyword (e.g., "database migration")
2. Results show all conversations mentioning it
3. Preview shows context of where it was mentioned

## Empty Results

If no conversations match:
- Try broader search terms
- Remove some filters
- Check spelling
- Try searching in both messages and titles

## Performance Tips

1. **Specific searches are faster**: "authentication error" vs "error"
2. **Use filters to narrow results**: Especially on large conversation lists
3. **Date ranges help**: Limit search to relevant time periods

## Mobile Usage

- All features work on mobile devices
- Advanced filters collapse to save space
- Tap "Show" to expand filters
- Swipe through result cards

## Browser Storage

The search interface remembers:
- Whether advanced filters are expanded/collapsed
- Only stored in your browser
- Cleared when you clear browser data

## Troubleshooting

### Search not finding expected results
- Check if you're searching in "Titles only" mode
- Try removing filters one by one
- Verify conversation exists (check without search)

### Too many results
- Add more specific search terms
- Use provider or status filters
- Narrow date range

### Highlighting not showing
- Ensure JavaScript is enabled
- Try refreshing the page
- Check browser console for errors

## Privacy & Security

- Search is user-specific (you only see your conversations)
- Search terms are not logged or stored
- All searches require authentication

## Technical Details

### Search Index
- Powered by SQLite FTS5 (Full-Text Search)
- Automatically updated when messages are added
- No manual reindexing needed

### Search Scope
- Conversation titles
- Message content (user and assistant messages)
- Does not search metadata or settings

---

**Need Help?**
- Run tests: `php artisan test tests/Unit/FullTextSearchTest.php`
- Check implementation: See `FULL_TEXT_SEARCH_IMPLEMENTATION.md`
- Review code: `app/Http/Controllers/ConversationController.php`
