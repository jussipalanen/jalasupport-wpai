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
    open: 'bg-sky-50 text-sky-700 ring-sky-200',
    in_progress: 'bg-amber-50 text-amber-700 ring-amber-200',
    resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    closed: 'bg-slate-100 text-slate-600 ring-slate-200',
  },
  priority: {
    low: 'bg-slate-50 text-slate-600 ring-slate-200',
    normal: 'bg-blue-50 text-blue-700 ring-blue-200',
    high: 'bg-orange-50 text-orange-700 ring-orange-200',
    urgent: 'bg-red-50 text-red-700 ring-red-200',
  },
};
