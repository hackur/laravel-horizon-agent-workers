import './bootstrap';

// Make modules available globally for inline scripts if needed
import { notify } from './modules/notification-manager';
window.notify = notify;
