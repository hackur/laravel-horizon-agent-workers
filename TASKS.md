# Incomplete, Buggy & Unproven Features - Task List

This document tracks features that need attention, testing, or completion.

## 🔴 Critical Issues

### 1. Authentication Integration
- **Status**: ⚠️ Partially Complete
- **Issue**: LLM query forms don't automatically attach current user
- **Tasks**:
  - [ ] Update `LLMQueryController@store` to automatically set `user_id` from auth
  - [ ] Update `LLMQueryDispatcher` to accept optional user context
  - [ ] Update views to show user-specific queries only
  - [ ] Add authorization policies for viewing/editing queries

### 2. Conversation Context
- **Status**: ⚠️ Not Implemented
- **Issue**: Queries are not linked to conversations yet
- **Tasks**:
  - [ ] Create conversation UI (list, create, view)
  - [ ] Add ability to create queries within conversation context
  - [ ] Implement conversation history passing to LLM providers
  - [ ] Add conversation message auto-creation when query completes

### 3. Job User Context Missing
- **Status**: ⚠️ Critical
- **Issue**: Background jobs don't have user context
- **Tasks**:
  - [ ] Update `BaseLLMJob` to accept and store user_id
  - [ ] Pass user_id through dispatcher to jobs
  - [ ] Test jobs run correctly with user association

## 🟡 Missing Features

### 4. API Authentication
- **Status**: ❌ Not Implemented
- **Tasks**:
  - [ ] Add Sanctum middleware to API routes
  - [ ] Create API token generation in user profile
  - [ ] Add API authentication documentation
  - [ ] Test API with bearer tokens

### 5. Real-time Updates
- **Status**: ❌ Not Implemented
- **Tasks**:
  - [ ] Add WebSocket/Pusher integration for live query updates
  - [ ] Remove meta refresh from query show page
  - [ ] Add job completion notifications
  - [ ] Implement real-time Horizon metrics on dashboard

### 6. Error Handling & Validation
- **Status**: ⚠️ Incomplete
- **Tasks**:
  - [ ] Add comprehensive validation to LLMQueryController
  - [ ] Add try-catch blocks in all job classes
  - [ ] Implement proper error logging
  - [ ] Add user-friendly error messages
  - [ ] Test failure scenarios for each provider

### 7. Provider Configuration
- **Status**: ⚠️ Not User-Friendly
- **Tasks**:
  - [ ] Create admin panel for provider settings
  - [ ] Add `.env` checker for API keys
  - [ ] Validate provider availability before dispatching
  - [ ] Add health check endpoints for each provider

### 8. Job Retries & Rate Limiting
- **Status**: ❌ Not Implemented
- **Tasks**:
  - [ ] Implement intelligent retry logic
  - [ ] Add rate limiting for Claude API
  - [ ] Add queue priority management UI
  - [ ] Implement job throttling

## 🟢 Nice-to-Have Features

### 9. Conversation Management UI
- **Status**: ❌ Not Started
- **Tasks**:
  - [ ] Create conversation list page
  - [ ] Add conversation detail view
  - [ ] Implement conversation search/filter
  - [ ] Add conversation export functionality

### 10. Streaming Support
- **Status**: ⚠️ Partial (Ollama only)
- **Tasks**:
  - [ ] Implement streaming for Claude API
  - [ ] Add SSE endpoint for real-time streaming
  - [ ] Update UI to display streaming responses
  - [ ] Test streaming with all providers

### 11. Batch Processing
- **Status**: ❌ Not Implemented
- **Tasks**:
  - [ ] Add batch job creation UI
  - [ ] Implement Laravel's job batching
  - [ ] Add batch progress tracking
  - [ ] Create batch result export

### 12. Cost Tracking
- **Status**: ❌ Not Implemented
- **Tasks**:
  - [ ] Track token usage for Claude API
  - [ ] Calculate and store cost per query
  - [ ] Create cost analytics dashboard
  - [ ] Add budget limits/warnings

## 🐛 Known Bugs

### 13. Route Model Binding Issue
- **Status**: ⚠️ Potential Issue
- **Problem**: `{llmQuery}` parameter might not bind correctly due to table name
- **Fix**:
  ```php
  // In LLMQuery model, add:
  public function getRouteKeyName()
  {
      return 'id';
  }
  ```

### 14. Team Context Not Used
- **Status**: ⚠️ Feature Exists But Unused
- **Issue**: Queries don't leverage Jetstream's team functionality
- **Tasks**:
  - [ ] Add team_id to LLMQuery model
  - [ ] Filter queries by current team
  - [ ] Add team permissions for query access

### 15. Missing Pagination
- **Status**: ⚠️ Performance Issue
- **Issue**: Query index might load too many records
- **Fix**: Already implemented with `paginate(20)` but needs testing

## 🧪 Untested Features

### 16. Provider Integration Testing
- **Status**: ❌ Not Tested
- **Tasks**:
  - [ ] Test Claude API with real API key
  - [ ] Test Ollama with local instance
  - [ ] Test LM Studio integration
  - [ ] Test Claude Code CLI integration
  - [ ] Test timeout handling for long-running jobs

### 17. Horizon Queue Management
- **Status**: ❌ Not Tested in Production
- **Tasks**:
  - [ ] Test queue priorities under load
  - [ ] Test auto-scaling behavior
  - [ ] Verify memory limits are appropriate
  - [ ] Test job failure recovery

### 18. Database Performance
- **Status**: ⚠️ Unknown
- **Tasks**:
  - [ ] Add indexes for common queries
  - [ ] Test query performance with large datasets
  - [ ] Optimize eager loading for relationships
  - [ ] Add database query logging

## 📝 Documentation Gaps

### 19. Missing Documentation
- **Tasks**:
  - [ ] API endpoint documentation
  - [ ] Job dispatch patterns
  - [ ] Conversation threading examples
  - [ ] Provider-specific configuration
  - [ ] Deployment guide
  - [ ] Security best practices

### 20. Developer Experience
- **Tasks**:
  - [ ] Add PHPDoc blocks to all methods
  - [ ] Create architecture diagram
  - [ ] Add contribution guidelines
  - [ ] Create troubleshooting guide

## 🚀 Next Steps (Recommended Order)

1. **Fix Authentication Context** (Task #1, #3) - Critical for multi-user usage
2. **Add API Authentication** (Task #4) - Important for security
3. **Implement Conversation UI** (Task #2, #9) - Core feature
4. **Test Provider Integrations** (Task #16) - Validate core functionality
5. **Add Error Handling** (Task #6) - Improve reliability
6. **Implement Real-time Updates** (Task #5) - Better UX
7. **Add Cost Tracking** (Task #12) - Business value
8. **Performance Testing** (Task #18) - Scalability

## 🛠 Quick Wins

These can be completed quickly:
- Fix route model binding (Task #13)
- Add validation rules (Task #6)
- Add PHPDoc blocks (Task #20)
- Create health check endpoints (Task #7)
- Test basic provider integrations (Task #16)

## ⚡ Testing Checklist

Before production deployment:
- [ ] Test user registration and login
- [ ] Test query creation with auth
- [ ] Test all providers (Claude, Ollama, LMStudio, Claude Code)
- [ ] Test job failures and retries
- [ ] Test Horizon dashboard access
- [ ] Test API endpoints with authentication
- [ ] Load test with 100+ concurrent queries
- [ ] Test database migrations on fresh install
- [ ] Test seeders create correct data
- [ ] Verify Redis connection handling
- [ ] Test queue worker crash recovery
- [ ] Verify log files are created correctly

## 📊 Metrics to Track

- Query success/failure rates
- Average query duration by provider
- Queue wait times
- Memory usage per worker
- Database query performance
- API rate limit usage

---

**Last Updated**: 2025-10-01
**Priority**: Focus on authentication (#1, #3) and provider testing (#16) first
