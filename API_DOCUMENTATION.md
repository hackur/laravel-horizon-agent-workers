# Laravel Horizon Agent Workers API Documentation

## Overview

This API provides programmatic access to the Laravel Horizon Agent Workers application, allowing you to manage conversations, dispatch LLM queries, and monitor their status.

**Base URL:** `http://localhost:8000/api`
**Authentication:** Bearer Token (Laravel Sanctum)
**Content-Type:** `application/json`

---

## Table of Contents

- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [Error Responses](#error-responses)
- [Endpoints](#endpoints)
  - [Authentication](#authentication-endpoints)
  - [API Tokens](#api-token-management)
  - [Conversations](#conversations)
  - [LLM Queries](#llm-queries)
  - [LM Studio](#lm-studio)

---

## Authentication

All API endpoints (except `/api/health`) require authentication using Laravel Sanctum bearer tokens.

### Creating an API Token

1. Log in to the web application
2. Navigate to your profile
3. Click "Create New Token"
4. Give your token a name (e.g., "Mobile App", "CI/CD Pipeline")
5. Copy the generated token (it will only be shown once)

### Using the Token

Include the token in the `Authorization` header of your requests:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     http://localhost:8000/api/user
```

---

## Rate Limiting

Different rate limits apply based on authentication status:

| Type | Limit | Window |
|------|-------|--------|
| Guest (unauthenticated) | 60 requests | per minute |
| Authenticated | 120 requests | per minute |
| Token Management | 10 requests | per minute |

When rate limited, you'll receive a `429 Too Many Requests` response.

---

## Error Responses

All API errors follow a consistent JSON structure:

```json
{
  "message": "Human-readable error message",
  "errors": {
    "field_name": [
      "Specific error description"
    ]
  }
}
```

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Resource created |
| 401 | Unauthenticated (missing or invalid token) |
| 403 | Unauthorized (insufficient permissions) |
| 404 | Resource or endpoint not found |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Server error |

---

## Endpoints

### Authentication Endpoints

#### Get Authenticated User

```http
GET /api/user
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "email_verified_at": "2025-10-01T12:00:00.000000Z",
  "created_at": "2025-10-01T10:00:00.000000Z",
  "updated_at": "2025-10-01T12:00:00.000000Z"
}
```

---

### API Token Management

#### List API Tokens

```http
GET /api/tokens
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Mobile App",
      "abilities": ["*"],
      "last_used_at": "2025-10-01T14:30:00.000000Z",
      "created_at": "2025-10-01T10:00:00.000000Z",
      "expires_at": null
    }
  ]
}
```

#### Create API Token

```http
POST /api/tokens
```

**Headers:**
```
Authorization: Bearer {session-token}
Content-Type: application/json
```

**Body:**
```json
{
  "name": "My API Token",
  "abilities": ["*"]
}
```

**Response:**
```json
{
  "message": "API token created successfully. Please save this token as it will not be shown again.",
  "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz",
  "tokenObject": {
    "id": 2,
    "name": "My API Token",
    "abilities": ["*"],
    "created_at": "2025-10-01T15:00:00.000000Z"
  }
}
```

#### Delete API Token

```http
DELETE /api/tokens/{tokenId}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "message": "Token deleted successfully"
}
```

---

### Conversations

#### List Conversations

```http
GET /api/conversations
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `provider` (optional): Filter by LLM provider (claude, ollama, lmstudio, local-command)
- `search` (optional): Search in conversation titles
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 15)

**Example:**
```bash
curl -H "Authorization: Bearer {token}" \
     "http://localhost:8000/api/conversations?provider=claude&page=1"
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "My Claude Conversation",
      "provider": "claude",
      "model": "claude-3-5-sonnet-20241022",
      "user_id": 1,
      "team_id": null,
      "last_message_at": "2025-10-01T14:30:00.000000Z",
      "created_at": "2025-10-01T10:00:00.000000Z",
      "updated_at": "2025-10-01T14:30:00.000000Z",
      "messages_count": 5,
      "links": {
        "self": "http://localhost:8000/api/conversations/1",
        "messages": "http://localhost:8000/api/conversations/1/messages"
      }
    }
  ],
  "meta": {
    "total": 25,
    "count": 15,
    "per_page": 15,
    "current_page": 1,
    "total_pages": 2
  },
  "links": {
    "first": "http://localhost:8000/api/conversations?page=1",
    "last": "http://localhost:8000/api/conversations?page=2",
    "prev": null,
    "next": "http://localhost:8000/api/conversations?page=2"
  }
}
```

#### Create Conversation

```http
POST /api/conversations
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "title": "My New Conversation",
  "provider": "claude",
  "model": "claude-3-5-sonnet-20241022",
  "prompt": "Hello, can you help me with something?"
}
```

**Response:**
```json
{
  "message": "Conversation created successfully",
  "data": {
    "id": 2,
    "title": "My New Conversation",
    "provider": "claude",
    "model": "claude-3-5-sonnet-20241022",
    "user_id": 1,
    "messages": [...],
    "queries": [...],
    "links": {
      "self": "http://localhost:8000/api/conversations/2",
      "messages": "http://localhost:8000/api/conversations/2/messages"
    }
  }
}
```

#### Get Conversation

```http
GET /api/conversations/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "title": "My Conversation",
    "provider": "claude",
    "messages": [
      {
        "id": 1,
        "role": "user",
        "content": "Hello!",
        "created_at": "2025-10-01T10:00:00.000000Z"
      },
      {
        "id": 2,
        "role": "assistant",
        "content": "Hi! How can I help you?",
        "created_at": "2025-10-01T10:00:15.000000Z"
      }
    ]
  }
}
```

#### Update Conversation

```http
PUT /api/conversations/{id}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "title": "Updated Conversation Title"
}
```

**Response:**
```json
{
  "message": "Conversation updated successfully",
  "data": {
    "id": 1,
    "title": "Updated Conversation Title",
    ...
  }
}
```

#### Delete Conversation

```http
DELETE /api/conversations/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "message": "Conversation \"My Conversation\" deleted successfully"
}
```

#### Add Message to Conversation

```http
POST /api/conversations/{id}/messages
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "prompt": "Can you explain this further?"
}
```

**Response:**
```json
{
  "message": "Message added and query dispatched successfully",
  "data": {
    "id": 3,
    "conversation_id": 1,
    "role": "user",
    "content": "Can you explain this further?",
    "created_at": "2025-10-01T15:00:00.000000Z"
  }
}
```

---

### LLM Queries

#### List LLM Queries

```http
GET /api/llm-queries
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `provider` (optional): Filter by provider (claude, ollama, lmstudio, local-command)
- `status` (optional): Filter by status (pending, processing, completed, failed)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 20)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "provider": "claude",
      "model": "claude-3-5-sonnet-20241022",
      "prompt": "Hello, world!",
      "response": "Hi! How can I help you today?",
      "status": "completed",
      "error": null,
      "metadata": {},
      "reasoning": null,
      "usage": {
        "input_tokens": 10,
        "output_tokens": 15
      },
      "started_at": "2025-10-01T10:00:00.000000Z",
      "completed_at": "2025-10-01T10:00:05.000000Z",
      "links": {
        "self": "http://localhost:8000/api/llm-queries/1"
      }
    }
  ],
  "meta": {
    "total": 50,
    "count": 20,
    "per_page": 20,
    "current_page": 1,
    "total_pages": 3
  }
}
```

#### Create LLM Query

```http
POST /api/llm-queries
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "provider": "claude",
  "prompt": "What is Laravel?",
  "model": "claude-3-5-sonnet-20241022",
  "options": {
    "max_tokens": 1024
  }
}
```

**Response:**
```json
{
  "message": "Query dispatched successfully",
  "data": {
    "id": 2,
    "provider": "claude",
    "model": "claude-3-5-sonnet-20241022",
    "prompt": "What is Laravel?",
    "status": "pending",
    "created_at": "2025-10-01T15:00:00.000000Z",
    "links": {
      "self": "http://localhost:8000/api/llm-queries/2"
    }
  }
}
```

#### Get LLM Query

```http
GET /api/llm-queries/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "provider": "claude",
    "model": "claude-3-5-sonnet-20241022",
    "prompt": "What is Laravel?",
    "response": "Laravel is a web application framework...",
    "status": "completed",
    "usage": {
      "input_tokens": 10,
      "output_tokens": 150
    },
    "links": {
      "self": "http://localhost:8000/api/llm-queries/1"
    }
  }
}
```

---

### LM Studio

#### Get Available Models

```http
GET /api/lmstudio/models
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "models": [
    "llama-2-7b-chat",
    "mistral-7b-instruct",
    "phi-2"
  ],
  "cached": true
}
```

**Error Response (LM Studio not running):**
```json
{
  "success": false,
  "error": "LM Studio is not running or not accessible: Connection refused",
  "models": []
}
```

---

## Health Check

#### Check API Health

```http
GET /api/health
```

No authentication required. Rate limited to 60 requests per minute.

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-10-01T15:30:00+00:00"
}
```

---

## Example Usage

### cURL

```bash
# Get your user info
curl -H "Authorization: Bearer 1|AbCdEfGhIjKlMnOpQrStUvWxYz" \
     http://localhost:8000/api/user

# Create a conversation
curl -X POST \
     -H "Authorization: Bearer 1|AbCdEfGhIjKlMnOpQrStUvWxYz" \
     -H "Content-Type: application/json" \
     -d '{"title":"Test","provider":"claude","prompt":"Hello!"}' \
     http://localhost:8000/api/conversations

# List queries
curl -H "Authorization: Bearer 1|AbCdEfGhIjKlMnOpQrStUvWxYz" \
     http://localhost:8000/api/llm-queries?status=completed
```

### JavaScript (Fetch API)

```javascript
const API_TOKEN = '1|AbCdEfGhIjKlMnOpQrStUvWxYz';
const BASE_URL = 'http://localhost:8000/api';

// Create a conversation
async function createConversation() {
  const response = await fetch(`${BASE_URL}/conversations`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${API_TOKEN}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      title: 'My Conversation',
      provider: 'claude',
      prompt: 'Hello, Claude!',
      model: 'claude-3-5-sonnet-20241022'
    })
  });

  const data = await response.json();
  console.log(data);
}

// Get query status
async function getQueryStatus(queryId) {
  const response = await fetch(`${BASE_URL}/llm-queries/${queryId}`, {
    headers: {
      'Authorization': `Bearer ${API_TOKEN}`,
    }
  });

  const data = await response.json();
  return data.data.status;
}
```

### Python (requests)

```python
import requests

API_TOKEN = '1|AbCdEfGhIjKlMnOpQrStUvWxYz'
BASE_URL = 'http://localhost:8000/api'

headers = {
    'Authorization': f'Bearer {API_TOKEN}',
    'Content-Type': 'application/json'
}

# List conversations
response = requests.get(f'{BASE_URL}/conversations', headers=headers)
conversations = response.json()

# Create a query
payload = {
    'provider': 'ollama',
    'prompt': 'Explain machine learning',
    'model': 'llama2'
}
response = requests.post(f'{BASE_URL}/llm-queries', headers=headers, json=payload)
query = response.json()
```

---

## Best Practices

1. **Store tokens securely**: Never commit API tokens to version control
2. **Respect rate limits**: Implement exponential backoff when rate limited
3. **Poll responsibly**: When checking query status, use reasonable intervals (e.g., every 2-5 seconds)
4. **Handle errors gracefully**: Always check response status codes and handle errors
5. **Use meaningful token names**: Name tokens based on their purpose (e.g., "Production App", "Development")
6. **Rotate tokens regularly**: Delete unused tokens and create new ones periodically
7. **Use HTTPS in production**: Never send tokens over unencrypted connections

---

## Support

For questions, issues, or feature requests, please refer to the main project README or contact the development team.
