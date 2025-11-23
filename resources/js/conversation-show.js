import { renderMarkdown, enhanceCodeBlocks, renderAllMarkdown } from './modules/markdown-renderer';
import { ConnectionManager } from './modules/connection-manager';
import { notify } from './modules/notification-manager';
import { setupFormLoading } from './modules/loading-state';
import { setupTitleEditor } from './modules/title-editor';

/**
 * Initialize conversation show page
 */
export function initConversationShow(conversationId) {
    // Render existing markdown
    renderAllMarkdown();

    // Setup connection manager
    const liveIndicator = document.getElementById('live-indicator');
    const statusText = liveIndicator?.querySelector('p');
    const statusDot = liveIndicator?.querySelector('svg');

    let connectionManager = null;
    if (liveIndicator && statusText && statusDot && window.Echo) {
        connectionManager = new ConnectionManager(liveIndicator, statusText, statusDot);

        // Show connection notifications
        connectionManager.onStateChange((state) => {
            if (state === 'connected') {
                notify.success('Real-time updates active', 2000);
            } else if (state === 'disconnected') {
                notify.warning('Connection lost - Reconnecting...', 3000);
            } else if (state === 'failed') {
                notify.error('Connection failed - Please refresh the page');
            }
        });
    }

    // Setup title editor
    setupTitleEditor(conversationId);

    // Setup form loading state
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        setupFormLoading(messageForm, {
            loadingText: 'Sending...'
        });
    }

    // Setup real-time message listener
    if (window.Echo && connectionManager) {
        setupMessageListener(conversationId, connectionManager);
    }
}

/**
 * Setup real-time message listener
 */
function setupMessageListener(conversationId, connectionManager) {
    const messagesContainer = document.getElementById('messages-container');
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');

    window.Echo.private(`conversations.${conversationId}`)
        .listen('.message.received', (event) => {
            console.log('New message received:', event);

            try {
                // Add the new assistant message to the UI
                const messageElement = createMessageElement(event.message, event.query_status);
                messagesContainer.appendChild(messageElement);

                // Scroll to the new message
                messageElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                // Re-enable form if it was disabled
                if (messageForm) {
                    messageForm.reset();
                    if (messageInput) messageInput.disabled = false;
                    if (sendButton) {
                        sendButton.disabled = false;
                        const buttonText = sendButton.querySelector('#buttonText');
                        const loadingSpinner = sendButton.querySelector('#loadingSpinner');
                        if (buttonText) buttonText.textContent = 'Send Message';
                        if (loadingSpinner) loadingSpinner.classList.add('hidden');
                    }
                }

                // Show notification
                notify.success('New response received!', 3000);

                // Hide pending indicator if present
                const pendingIndicator = document.querySelector('.bg-blue-50.border-blue-200');
                if (pendingIndicator) {
                    pendingIndicator.remove();
                }
            } catch (error) {
                console.error('Error handling message:', error);
                notify.error('Error displaying message. Please refresh.', 5000);
            }
        })
        .error((error) => {
            console.error('Echo channel error:', error);
            notify.error('Real-time connection error', 3000);
        });

    console.log(`Subscribed to conversation ${conversationId}`);
}

/**
 * Create message element from message data
 */
function createMessageElement(message, queryStatus) {
    const timestamp = new Date(message.created_at).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex justify-start animate-fade-in';
    messageDiv.innerHTML = `
        <div class="max-w-3xl w-full mr-12">
            <div class="bg-gray-50 shadow-sm sm:rounded-lg p-6 border-2 border-green-200">
                <div class="flex items-start justify-between mb-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Assistant
                        <span class="ml-1 animate-pulse">●</span>
                    </span>
                    <span class="text-xs text-gray-500">${escapeHtml(timestamp)}</span>
                </div>
                <div class="markdown-content" data-raw-content="${btoa(message.content)}"></div>
                ${queryStatus && queryStatus.duration_ms ? `
                    <div class="mt-3 pt-3 border-t border-gray-200 text-xs text-gray-500 flex items-center space-x-3">
                        <span class="inline-flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            ${(queryStatus.duration_ms / 1000).toFixed(2)}s
                        </span>
                        ${queryStatus.usage_stats && queryStatus.usage_stats.total_tokens ? `
                            <span class="inline-flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                </svg>
                                ${queryStatus.usage_stats.total_tokens} tokens
                            </span>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
        </div>
    `;

    // Render markdown for the new message
    const markdownContainer = messageDiv.querySelector('.markdown-content');
    const rawContent = atob(markdownContainer.dataset.rawContent);
    markdownContainer.innerHTML = renderMarkdown(rawContent);
    enhanceCodeBlocks(markdownContainer);

    return messageDiv;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
