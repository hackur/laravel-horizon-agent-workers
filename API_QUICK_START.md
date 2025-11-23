# API Quick Start Guide

## Getting Your API Token

### Option 1: Web Interface (Recommended for Testing)
1. Start the development server: `composer dev`
2. Open browser to `http://localhost:8000`
3. Log in or register
4. Navigate to your profile
5. Click "API Tokens" section
6. Click "Create New Token"
7. Name your token (e.g., "Development", "Mobile App")
8. Copy the token (shown only once!)

### Option 2: Command Line (For Testing)
```bash
php artisan tinker
```
```php
$user = User::first(); // or User::find(1)
$token = $user->createToken('My API Token');
echo $token->plainTextToken;
```

## Making Your First API Call

### 1. Health Check (No Authentication)
```bash
curl http://localhost:8000/api/health
```

Expected response:
```json
{
  "status": "ok",
  "timestamp": "2025-10-01T15:30:00+00:00"
}
```

### 2. Get Your User Info (Authenticated)
```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     http://localhost:8000/api/user
```

Expected response:
```json
{
  "id": 1,
  "name": "Your Name",
  "email": "your@email.com",
  ...
}
```

### 3. List Your Conversations
```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     http://localhost:8000/api/conversations
```

### 4. Create a Conversation
```bash
curl -X POST \
     -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     -H "Content-Type: application/json" \
     -d '{
       "title": "My First API Conversation",
       "provider": "claude",
       "model": "claude-3-5-sonnet-20241022",
       "prompt": "Hello from the API!"
     }' \
     http://localhost:8000/api/conversations
```

### 5. Dispatch an LLM Query
```bash
curl -X POST \
     -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     -H "Content-Type: application/json" \
     -d '{
       "provider": "ollama",
       "prompt": "Explain machine learning in simple terms",
       "model": "llama2"
     }' \
     http://localhost:8000/api/llm-queries
```

### 6. Check Query Status
```bash
# Use the query ID from the previous response
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     http://localhost:8000/api/llm-queries/1
```

## Common API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/health` | Health check (no auth) |
| `GET` | `/api/user` | Get authenticated user |
| `GET` | `/api/conversations` | List conversations |
| `POST` | `/api/conversations` | Create conversation |
| `GET` | `/api/conversations/{id}` | View conversation |
| `PUT` | `/api/conversations/{id}` | Update conversation |
| `DELETE` | `/api/conversations/{id}` | Delete conversation |
| `POST` | `/api/conversations/{id}/messages` | Add message |
| `GET` | `/api/llm-queries` | List queries |
| `POST` | `/api/llm-queries` | Create query |
| `GET` | `/api/llm-queries/{id}` | View query |
| `GET` | `/api/tokens` | List your tokens |
| `POST` | `/api/tokens` | Create new token |
| `DELETE` | `/api/tokens/{id}` | Delete token |

## Rate Limits

- **Guest:** 60 requests per minute
- **Authenticated:** 120 requests per minute
- **Token Management:** 10 requests per minute

## Error Handling

All errors follow the same structure:

```json
{
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Specific error description"]
  }
}
```

### Common Status Codes:
- `200` - Success
- `201` - Created
- `401` - Unauthenticated (missing or invalid token)
- `403` - Unauthorized (insufficient permissions)
- `404` - Not found
- `422` - Validation error
- `429` - Rate limit exceeded
- `500` - Server error

## JavaScript Example

```javascript
const API_URL = 'http://localhost:8000/api';
const API_TOKEN = 'YOUR_TOKEN_HERE';

async function createConversation() {
  try {
    const response = await fetch(`${API_URL}/conversations`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${API_TOKEN}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        title: 'My JS Conversation',
        provider: 'claude',
        prompt: 'Hello from JavaScript!',
      })
    });

    if (!response.ok) {
      const error = await response.json();
      console.error('Error:', error);
      return;
    }

    const data = await response.json();
    console.log('Conversation created:', data);
  } catch (error) {
    console.error('Network error:', error);
  }
}

createConversation();
```

## Python Example

```python
import requests

API_URL = 'http://localhost:8000/api'
API_TOKEN = 'YOUR_TOKEN_HERE'

headers = {
    'Authorization': f'Bearer {API_TOKEN}',
    'Content-Type': 'application/json'
}

# Create a query
payload = {
    'provider': 'ollama',
    'prompt': 'What is Laravel?',
    'model': 'llama2'
}

response = requests.post(
    f'{API_URL}/llm-queries',
    headers=headers,
    json=payload
)

if response.status_code == 201:
    query = response.json()
    print(f"Query created: {query['data']['id']}")
else:
    print(f"Error: {response.json()}")
```

## Testing the API

```bash
# Run all API tests
php artisan test tests/Feature/Api/

# Run specific test file
php artisan test tests/Feature/Api/ApiAuthenticationTest.php

# Run with detailed output
php artisan test tests/Feature/Api/ --testdox
```

## Complete Documentation

For complete API documentation, see:
- `API_DOCUMENTATION.md` - Full endpoint reference
- `API_SECURITY_SUMMARY.md` - Security implementation details

## Troubleshooting

### "Unauthenticated" Error
- Check that you're including the `Authorization: Bearer {token}` header
- Verify your token is valid (hasn't been deleted)
- Ensure you copied the entire token

### "Rate limit exceeded" Error
- Wait 60 seconds before retrying
- Consider implementing exponential backoff in your client

### "Unauthorized" Error
- You're trying to access someone else's resource
- Ensure you're accessing your own conversations/queries

### "Validation error" Error
- Check the `errors` field in the response
- Verify all required fields are present and valid

## Support

For questions or issues:
1. Check the full documentation in `API_DOCUMENTATION.md`
2. Review the test files in `tests/Feature/Api/` for examples
3. Check the project README for general setup instructions
