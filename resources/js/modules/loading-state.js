/**
 * Loading State Manager for forms and async operations
 */
export class LoadingState {
    constructor(button, options = {}) {
        this.button = button;
        this.originalText = button.textContent.trim();
        this.spinner = options.spinner || this.createSpinner();
        this.loadingText = options.loadingText || 'Loading...';
        this.isLoading = false;

        // Find or create spinner element
        if (typeof this.spinner === 'string') {
            const existingSpinner = button.querySelector(this.spinner);
            this.spinner = existingSpinner || this.createSpinner();
        }
    }

    createSpinner() {
        const spinner = document.createElement('svg');
        spinner.className = 'animate-spin -ml-1 mr-2 h-4 w-4 hidden loading-spinner';
        spinner.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        spinner.setAttribute('fill', 'none');
        spinner.setAttribute('viewBox', '0 0 24 24');
        spinner.innerHTML = `
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        `;

        // Insert spinner at the beginning of button
        this.button.insertBefore(spinner, this.button.firstChild);
        return spinner;
    }

    start() {
        if (this.isLoading) return;

        this.isLoading = true;
        this.button.disabled = true;
        this.spinner.classList.remove('hidden');

        // Update button text
        const textNode = Array.from(this.button.childNodes).find(
            node => node.nodeType === Node.TEXT_NODE && node.textContent.trim()
        );
        if (textNode) {
            this.originalText = textNode.textContent.trim();
            textNode.textContent = this.loadingText;
        } else {
            this.button.appendChild(document.createTextNode(this.loadingText));
        }

        this.button.classList.add('cursor-not-allowed', 'opacity-75');
    }

    stop() {
        if (!this.isLoading) return;

        this.isLoading = false;
        this.button.disabled = false;
        this.spinner.classList.add('hidden');

        // Restore original text
        const textNode = Array.from(this.button.childNodes).find(
            node => node.nodeType === Node.TEXT_NODE
        );
        if (textNode) {
            textNode.textContent = this.originalText;
        }

        this.button.classList.remove('cursor-not-allowed', 'opacity-75');
    }

    reset() {
        this.stop();
    }
}

/**
 * Setup loading states for forms
 */
export function setupFormLoading(form, options = {}) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return null;

    const loadingState = new LoadingState(submitButton, options);

    form.addEventListener('submit', () => {
        loadingState.start();
    });

    return loadingState;
}

/**
 * Create a promise with loading state
 */
export async function withLoading(button, asyncFn, options = {}) {
    const loadingState = new LoadingState(button, options);

    try {
        loadingState.start();
        const result = await asyncFn();
        return result;
    } finally {
        loadingState.stop();
    }
}
