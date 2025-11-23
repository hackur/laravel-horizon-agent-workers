/**
 * Inline Title Editor with improved UX
 */
export class TitleEditor {
    constructor(options) {
        this.titleDisplay = options.titleDisplay;
        this.titleForm = options.titleForm;
        this.titleInput = options.titleInput;
        this.titleText = options.titleText;
        this.cancelButton = options.cancelButton;
        this.saveButton = options.saveButton;
        this.onSave = options.onSave;

        this.init();
    }

    init() {
        // Click to edit
        this.titleDisplay.addEventListener('click', () => {
            this.startEdit();
        });

        // Cancel button
        this.cancelButton.addEventListener('click', (e) => {
            e.preventDefault();
            this.cancelEdit();
        });

        // Keyboard shortcuts
        this.titleInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                this.cancelEdit();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                this.saveEdit();
            }
        });

        // Form submission
        this.titleForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveEdit();
        });
    }

    startEdit() {
        this.titleDisplay.classList.add('hidden');
        this.titleForm.classList.remove('hidden');
        this.titleInput.focus();
        this.titleInput.select();
    }

    cancelEdit() {
        this.titleForm.classList.add('hidden');
        this.titleDisplay.classList.remove('hidden');
        this.titleInput.value = this.titleText.textContent.trim();
    }

    async saveEdit() {
        const newTitle = this.titleInput.value.trim();
        const oldTitle = this.titleText.textContent.trim();

        if (!newTitle || newTitle === oldTitle) {
            this.cancelEdit();
            return;
        }

        // Disable inputs during save
        this.titleInput.disabled = true;
        this.saveButton.disabled = true;
        this.cancelButton.disabled = true;
        this.saveButton.textContent = 'Saving...';

        try {
            if (this.onSave) {
                await this.onSave(newTitle);
            } else {
                // Default form submission
                this.titleForm.submit();
            }

            // Update display
            this.titleText.textContent = newTitle;
            this.cancelEdit();
        } catch (error) {
            console.error('Failed to save title:', error);
            // Re-enable inputs on error
            this.titleInput.disabled = false;
            this.saveButton.disabled = false;
            this.cancelButton.disabled = false;
            this.saveButton.textContent = 'Save';
            throw error;
        }
    }
}

/**
 * Setup title editor with AJAX save
 */
export function setupTitleEditor(conversationId) {
    const titleDisplay = document.getElementById('titleDisplay');
    const titleForm = document.getElementById('titleForm');
    const titleInput = document.getElementById('titleInput');
    const titleText = document.getElementById('titleText');
    const cancelButton = document.getElementById('cancelEdit');
    const saveButton = titleForm?.querySelector('button[type="submit"]');

    if (!titleDisplay || !titleForm || !titleInput) {
        return null;
    }

    return new TitleEditor({
        titleDisplay,
        titleForm,
        titleInput,
        titleText,
        cancelButton,
        saveButton,
        onSave: async (newTitle) => {
            // AJAX save
            const formData = new FormData(titleForm);
            formData.set('title', newTitle);

            const response = await fetch(titleForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to save title');
            }

            return response.json();
        }
    });
}
