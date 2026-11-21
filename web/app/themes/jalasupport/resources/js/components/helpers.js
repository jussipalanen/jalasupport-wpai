import { config } from '../api.js';

/**
 * Formatting helpers shared by the Alpine components.
 */
export const helpers = {
    ticketUrl(id) {
        return new URL(`tickets/${id}/`, config.appUrl).toString();
    },

    formatDate(iso) {
        if (!iso) return '—';

        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    },

    relativeTime(iso) {
        if (!iso) return '';

        const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
        const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
        const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

        for (const [unit, size] of units) {
            if (Math.abs(seconds) >= size) {
                return formatter.format(Math.round(seconds / size), unit);
            }
        }

        return formatter.format(seconds, 'second');
    },

    toast(message, type = 'success') {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
    },
};

export const badgeClasses = {
    status: {
        open: 'bg-sky-500/10 text-sky-300 ring-sky-500/30',
        in_progress: 'bg-amber-500/10 text-amber-300 ring-amber-500/30',
        resolved: 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30',
        closed: 'bg-white/5 text-slate-300 ring-white/10',
    },
    priority: {
        low: 'bg-white/5 text-slate-300 ring-white/10',
        normal: 'bg-blue-500/10 text-blue-300 ring-blue-500/30',
        high: 'bg-orange-500/10 text-orange-300 ring-orange-500/30',
        urgent: 'bg-red-500/10 text-red-300 ring-red-500/30',
    },
};
