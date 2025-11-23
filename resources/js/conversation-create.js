import { setupFormLoading, withLoading } from './modules/loading-state';
import { notify } from './modules/notification-manager';

/**
 * Initialize conversation create page
 */
export function initConversationCreate(providers) {
    // Setup form loading state
    const form = document.querySelector('form');
    if (form) {
        setupFormLoading(form, {
            loadingText: 'Creating...'
        });
    }

    // Setup model updater
    setupModelSelector(providers);
}

/**
 * Setup model selector with dynamic loading
 */
function setupModelSelector(providers) {
    const providerRadios = document.querySelectorAll('input[name="provider"]');
    const modelSelect = document.getElementById('model');
    const modelSection = document.getElementById('modelSection');

    if (!modelSelect || !modelSection) return;

    providerRadios.forEach(radio => {
        radio.addEventListener('change', async () => {
            const providerKey = radio.value;
            await updateModels(providerKey, providers, modelSelect, modelSection);
        });
    });
}

/**
 * Update models based on selected provider
 */
async function updateModels(providerKey, providers, modelSelect, modelSection) {
    const provider = providers[providerKey];

    // For LM Studio, fetch models from API
    if (providerKey === 'lmstudio') {
        modelSelect.innerHTML = '<option value="">Loading models...</option>';
        modelSection.classList.remove('hidden');

        try {
            const response = await fetch('/api/lmstudio/models');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.models.length > 0) {
                modelSelect.innerHTML = '<option value="">Select a model</option>';
                data.models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    modelSelect.appendChild(option);
                });
                notify.success(`Loaded ${data.models.length} models from LM Studio`, 2000);
            } else {
                modelSelect.innerHTML = '<option value="">No models available</option>';
                notify.warning('No models available. Is LM Studio running?', 4000);
            }
        } catch (error) {
            console.error('Failed to fetch LM Studio models:', error);
            modelSelect.innerHTML = '<option value="">Failed to load models</option>';
            notify.error('Failed to load models. Check if LM Studio is running.', 5000);
        }
    }
    // For other providers with static models
    else if (provider && provider.models && provider.models.length > 0) {
        modelSelect.innerHTML = '<option value="">Default model</option>';
        provider.models.forEach(model => {
            const option = document.createElement('option');
            option.value = model;
            option.textContent = model;
            modelSelect.appendChild(option);
        });
        modelSection.classList.remove('hidden');
    } else {
        modelSection.classList.add('hidden');
    }
}
