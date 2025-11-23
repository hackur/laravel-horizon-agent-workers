# Frontend Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Browser (Client)                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │  Blade View  │  │  Blade View  │  │  Blade View  │    │
│  │   show.php   │  │  create.php  │  │   index.php  │    │
│  └──────┬───────┘  └──────┬───────┘  └──────────────┘    │
│         │                  │                                │
│         ├──────────────────┴─────────── Vite Assets        │
│         │                                                   │
│  ┌──────▼──────────────────────────────────────────┐      │
│  │              app.css (14 KB gzip)               │      │
│  │  • Tailwind CSS                                 │      │
│  │  • highlight.js theme                           │      │
│  │  • Conversation styles                          │      │
│  └─────────────────────────────────────────────────┘      │
│                                                             │
│  ┌─────────────────────────────────────────────────┐      │
│  │              app.js (36 KB gzip)                │      │
│  │  • Laravel Echo (WebSocket)                     │      │
│  │  • Global utilities                             │      │
│  │  • Notification system                          │      │
│  └─────────────────────────────────────────────────┘      │
│                                                             │
│  ┌─────────────────────────────────────────────────┐      │
│  │        conversation-show.js (14 KB gzip)        │      │
│  │  • Real-time message updates                    │      │
│  │  • Markdown rendering                           │      │
│  │  • Title editing                                │      │
│  │  • Form handling                                │      │
│  └─────────────────────────────────────────────────┘      │
│                                                             │
│  ┌─────────────────────────────────────────────────┐      │
│  │          vendor.js (21 KB gzip)                 │      │
│  │  • marked (Markdown parser)                     │      │
│  │  • DOMPurify (XSS sanitizer)                    │      │
│  └─────────────────────────────────────────────────┘      │
│                                                             │
│  ┌─────────────────────────────────────────────────┐      │
│  │          highlight.js (8 KB gzip)               │      │
│  │  • Syntax highlighting core                     │      │
│  │  • 8 language definitions                       │      │
│  └─────────────────────────────────────────────────┘      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ WebSocket (Reverb)
                            │ HTTP (Axios)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Backend                          │
├─────────────────────────────────────────────────────────────┤
│  • Controllers                                              │
│  • Models                                                   │
│  • Jobs (Horizon)                                           │
│  • Broadcasting (Reverb)                                    │
└─────────────────────────────────────────────────────────────┘
```

## Module Architecture

```
resources/js/
│
├── app.js (Entry Point)
│   ├── Imports bootstrap.js
│   └── Exports global utilities
│
├── bootstrap.js (Laravel Echo Setup)
│   ├── Configures Pusher
│   └── Initializes Echo instance
│
├── conversation-show.js (Show Page Logic)
│   ├── initConversationShow()
│   ├── setupMessageListener()
│   └── createMessageElement()
│
├── conversation-create.js (Create Page Logic)
│   ├── initConversationCreate()
│   └── setupModelSelector()
│
└── modules/
    │
    ├── markdown-renderer.js
    │   ├── renderMarkdown()
    │   ├── enhanceCodeBlocks()
    │   └── renderAllMarkdown()
    │
    ├── connection-manager.js
    │   ├── ConnectionManager class
    │   ├── handleConnected()
    │   ├── handleDisconnected()
    │   └── attemptReconnect()
    │
    ├── notification-manager.js
    │   ├── NotificationManager class
    │   ├── show()
    │   ├── success()
    │   ├── error()
    │   └── warning()
    │
    ├── loading-state.js
    │   ├── LoadingState class
    │   ├── setupFormLoading()
    │   └── withLoading()
    │
    └── title-editor.js
        ├── TitleEditor class
        └── setupTitleEditor()
```

## Data Flow

### 1. Page Load Sequence

```
User requests page
       │
       ▼
Laravel renders Blade template
       │
       ├── Injects @vite() tags
       │   └── References compiled assets
       │
       ├── Renders initial messages
       │   └── Markdown in base64 data attributes
       │
       └── Outputs JavaScript initialization
           └── Calls init function with conversation ID
               │
               ▼
Browser loads assets (parallel)
       │
       ├── app.css (14 KB)
       ├── app.js (36 KB) ─────────┐
       ├── conversation-show.js (14 KB)  │
       ├── vendor.js (21 KB) ────────────┤ Cached after
       └── highlight.js (8 KB) ──────────┘ first load
               │
               ▼
JavaScript executes
       │
       ├── Initialize Laravel Echo
       │   └── Connect to WebSocket
       │
       ├── Render markdown content
       │   ├── Decode base64
       │   ├── Parse with marked
       │   ├── Sanitize with DOMPurify
       │   └── Apply syntax highlighting
       │
       ├── Setup connection manager
       │   └── Show connection status
       │
       ├── Setup title editor
       │   └── Enable inline editing
       │
       └── Setup message listener
           └── Listen for new messages
```

### 2. Real-Time Message Flow

```
User sends message
       │
       ▼
Form submission
       │
       ├── Show loading state
       ├── Disable form inputs
       └── POST to Laravel
               │
               ▼
Laravel processes request
       │
       ├── Create user message
       ├── Dispatch LLM job to Horizon
       └── Return response
               │
               ▼
Page reloads (showing pending state)
       │
       ▼
Horizon processes job
       │
       ├── Query LLM provider
       ├── Receive response
       ├── Save assistant message
       └── Broadcast MessageReceived event
               │
               ▼
WebSocket pushes to browser
       │
       ▼
Echo receives event
       │
       ├── Extract message data
       ├── Render markdown
       ├── Apply syntax highlighting
       ├── Add to DOM with animation
       ├── Scroll to message
       ├── Show success notification
       └── Re-enable form
```

### 3. Title Editing Flow

```
User clicks title
       │
       ▼
Title editor activates
       │
       ├── Hide display element
       ├── Show input element
       ├── Focus and select text
       └── Setup keyboard listeners
               │
               ▼
User edits and presses Enter
       │
       ▼
Title editor validates
       │
       ├── Check for changes
       ├── Show loading state
       └── AJAX POST to Laravel
               │
               ▼
Laravel updates database
       │
       └── Return JSON response
               │
               ▼
JavaScript handles response
       │
       ├── Update display text
       ├── Hide input element
       ├── Show display element
       └── Show success notification
```

### 4. WebSocket Reconnection Flow

```
WebSocket disconnects
       │
       ▼
Connection manager detects
       │
       ├── Update status indicator (gray)
       ├── Show "Disconnected" message
       └── Show warning notification
               │
               ▼
Start reconnection attempts
       │
       ├── Attempt 1: Wait 1s
       ├── Attempt 2: Wait 2s
       ├── Attempt 3: Wait 4s
       ├── Attempt 4: Wait 8s
       └── Attempt 5: Wait 16s
               │
               ├── Success ──────┐
               │                 │
               ▼                 ▼
       Max attempts      Reconnected
       reached                  │
           │                    ├── Update indicator (green)
           │                    ├── Show "Connected" message
           │                    └── Show success notification
           ▼
       Show failed state
           │
           ├── Update indicator (red)
           ├── Show "Failed" message
           └── Show "Reload Page" button
```

## Component Interaction Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                   Conversation Show Page                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │          Connection Manager                       │    │
│  │  • Monitors WebSocket state                       │    │
│  │  • Handles reconnection                           │    │
│  │  • Updates status indicator                       │    │
│  └───────────┬───────────────────────────────────────┘    │
│              │ emits events                                │
│              │                                             │
│  ┌───────────▼───────────────────────────────────────┐    │
│  │      Notification Manager (singleton)             │    │
│  │  • Shows toast notifications                      │    │
│  │  • Auto-dismiss with timer                        │    │
│  │  • Handles multiple notifications                 │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │          Markdown Renderer                        │    │
│  │  • Parses markdown with marked                    │    │
│  │  • Sanitizes HTML with DOMPurify                  │    │
│  │  • Applies syntax highlighting                    │    │
│  │  • Adds copy buttons                              │    │
│  └───────────┬───────────────────────────────────────┘    │
│              │ processes                                   │
│              │                                             │
│  ┌───────────▼───────────────────────────────────────┐    │
│  │       Message Container (DOM)                     │    │
│  │  • Displays rendered messages                     │    │
│  │  • Shows code with highlighting                   │    │
│  │  • Copy buttons on code blocks                    │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │          Title Editor                             │    │
│  │  • Inline editing interface                       │    │
│  │  • AJAX save                                      │    │
│  │  • Keyboard shortcuts                             │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │          Message Form                             │    │
│  │  • Loading state manager                          │    │
│  │  • Form validation                                │    │
│  │  • Submit handler                                 │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │          Echo Listener                            │    │
│  │  • Subscribes to private channel                  │    │
│  │  • Receives MessageReceived events                │    │
│  │  • Triggers message rendering                     │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## State Management

### Connection States
```
┌──────────┐   connect   ┌──────────┐
│          │ ──────────> │          │
│  Initial │             │Connecting│
│          │ <────────── │          │
└──────────┘   error     └─────┬────┘
                               │
                               │ success
                               │
                         ┌─────▼────┐
                    ┌───>│          │
                    │    │Connected │
                    │    │          │
                    │    └─────┬────┘
              reconnect  │     │
                    │    │     │ disconnect
                    │    │     │
              ┌─────┴────▼─────▼───┐
              │                     │
              │    Disconnected     │
              │                     │
              └─────┬───────────────┘
                    │
                    │ attempt < max
                    │
              ┌─────▼────┐
              │          │
              │Reconnect │
              │  -ing    │
              │          │
              └─────┬────┘
                    │
                    │ max reached
                    │
              ┌─────▼────┐
              │          │
              │  Failed  │
              │          │
              └──────────┘
```

### Message States
```
┌──────────┐   user types   ┌──────────┐
│          │ ─────────────> │          │
│   Idle   │                │  Typing  │
│          │ <───────────── │          │
└──────────┘   cancels      └─────┬────┘
                                   │
                                   │ submits
                                   │
                             ┌─────▼────┐
                             │          │
                             │ Sending  │
                             │          │
                             └─────┬────┘
                                   │
                ┌──────────────────┼──────────────────┐
                │ success          │                  │ error
                │                  │                  │
          ┌─────▼────┐       ┌─────▼────┐      ┌────▼────┐
          │          │       │          │      │         │
          │Processing│       │   Sent   │      │  Error  │
          │          │       │          │      │         │
          └─────┬────┘       └──────────┘      └─────────┘
                │
                │ LLM response
                │
          ┌─────▼────┐
          │          │
          │Completed │
          │          │
          └──────────┘
```

## Performance Considerations

### Bundle Splitting Strategy
```
Entry Points:
  ├── app.js (Common)
  │   └── Used on all pages
  │
  ├── conversation-show.js (Page-specific)
  │   └── Only loaded on show page
  │
  └── conversation-create.js (Page-specific)
      └── Only loaded on create page

Shared Chunks:
  ├── vendor.js (Heavy libraries)
  │   ├── marked
  │   └── dompurify
  │
  └── highlight.js (Syntax highlighting)
      └── Core + 8 languages
```

### Caching Strategy
```
Asset Type          Cache-Control           Versioning
─────────────────   ────────────────────   ─────────────
CSS                 public, max-age=31536000   Content hash
JavaScript          public, max-age=31536000   Content hash
Images              public, max-age=31536000   Content hash
manifest.json       no-cache                    Build time
```

### Loading Priority
```
Priority    Asset                   Size    When
──────────  ────────────────────   ─────   ─────────────
Critical    app.css                14 KB   Blocking
Critical    app.js                 36 KB   Async
High        conversation-show.js   14 KB   Async
High        vendor.js              21 KB   Async
Medium      highlight.js            8 KB   Async
Low         Images                 varies  Lazy
```

## Security Architecture

### Input Sanitization
```
User Input
    │
    ▼
Marked.js (Parse Markdown)
    │
    ▼
DOMPurify (Sanitize HTML)
    │
    ├── Allowed tags only
    ├── Remove script tags
    ├── Remove event handlers
    └── Whitelist attributes
    │
    ▼
Safe HTML in DOM
```

### XSS Prevention
- All user content sanitized via DOMPurify
- CSP headers (configured in Laravel)
- CSRF tokens on all forms
- No eval() or innerHTML with unsanitized data

### WebSocket Security
- Private channels (authentication required)
- Channel authorization via Laravel
- Encrypted connection (WSS)
- Token-based authentication

---

**Architecture Version**: 1.0
**Last Updated**: November 23, 2025
**Status**: Production Ready
