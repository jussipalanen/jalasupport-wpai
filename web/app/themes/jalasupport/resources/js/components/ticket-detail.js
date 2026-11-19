import { api, config } from '../api.js';
import { helpers } from './helpers.js';

/**
 * Ticket details: inline editing, property changes and the AI workflow.
 *
 * AI suggestions live only in component state until the user accepts one;
 * accepting sends a normal ticket update (PATCH) for that single field.
 */
export default (id) => ({
    ...helpers,
    id,
    ticket: null,
    loading: true,
    notFound: false,
    error: '',
    errors: {},
    saving: false,
    editing: false,
    edit: {},
    canDelete: config.canDelete,
    ai: { loading: null, error: '', unavailable: false, suggestions: null },

    async init() {
        try {
            this.ticket = (await api(`tickets/${this.id}`)).data;
        } catch (error) {
            this.notFound = error.status === 404;
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },

    async save(changes, successMessage) {
        this.saving = true;
        this.errors = {};

        try {
            this.ticket = (await api(`tickets/${this.id}`, { method: 'PATCH', body: changes })).data;
            this.toast(successMessage);

            return true;
        } catch (error) {
            this.errors = error.errors ?? {};
            this.toast(error.message, 'error');

            return false;
        } finally {
            this.saving = false;
        }
    },

    updateField(field, value) {
        if (this.ticket[field] === value) return;

        this.save({ [field]: value }, 'Ticket updated.');
    },

    startEdit() {
        this.edit = {
            title: this.ticket.title,
            description: this.ticket.description,
            customer_name: this.ticket.customer.name,
            customer_email: this.ticket.customer.email,
        };
        this.errors = {};
        this.editing = true;
    },

    cancelEdit() {
        this.editing = false;
        this.errors = {};
    },

    async saveEdit() {
        if (await this.save(this.edit, 'Changes saved.')) {
            this.editing = false;
        }
    },

    async destroy() {
        if (!window.confirm('Move this ticket to the trash?')) return;

        try {
            await api(`tickets/${this.id}`, { method: 'DELETE' });
            window.location.href = new URL('tickets/', config.appUrl).toString();
        } catch (error) {
            this.toast(error.message, 'error');
        }
    },

    // --- AI -----------------------------------------------------------------

    async requestAi(endpoint, mode) {
        this.ai.loading = mode;
        this.ai.error = '';

        try {
            const response = await api(`tickets/${this.id}/ai/${endpoint}`, { method: 'POST' });
            this.ai.suggestions = { ...(this.ai.suggestions ?? {}), ...response.data.suggestions };
        } catch (error) {
            this.ai.unavailable = error.status === 503;
            this.ai.error = error.message;
        } finally {
            this.ai.loading = null;
        }
    },

    analyze() {
        this.requestAi('analyze', 'analyze');
    },

    suggestReply() {
        this.requestAi('suggest-reply', 'reply');
    },

    async acceptField(field) {
        if (await this.save({ [field]: this.ai.suggestions[field] }, 'Suggestion applied.')) {
            this.rejectSuggestion(field);
        }
    },

    async acceptText(field) {
        const target = field === 'summary' ? 'ai_summary' : 'ai_suggested_reply';

        if (await this.save({ [target]: this.ai.suggestions[field] }, 'Saved to the ticket.')) {
            this.rejectSuggestion(field);
        }
    },

    rejectSuggestion(field) {
        if (!this.ai.suggestions) return;

        const next = { ...this.ai.suggestions };
        delete next[field];
        delete next[`${field}_label`];

        const remaining = ['category', 'priority', 'summary', 'suggested_reply'].some((key) => key in next);
        this.ai.suggestions = remaining ? next : null;
    },

    async copy(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.toast('Copied to clipboard.');
        } catch {
            this.toast('Copy failed. Select the text and copy it manually.', 'error');
        }
    },
});
