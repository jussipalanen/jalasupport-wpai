import Alpine from 'alpinejs';
import dashboard from './components/dashboard.js';
import ticketList from './components/ticket-list.js';
import ticketForm from './components/ticket-form.js';
import ticketDetail from './components/ticket-detail.js';
import toast from './components/toast.js';
import { badgeClasses } from './components/helpers.js';

Alpine.store('ui', {
    badgeClass(kind, value) {
        return badgeClasses[kind]?.[value] ?? 'bg-slate-50 text-slate-600 ring-slate-200';
    },
});

Alpine.data('dashboard', dashboard);
Alpine.data('ticketList', ticketList);
Alpine.data('ticketForm', ticketForm);
Alpine.data('ticketDetail', ticketDetail);
Alpine.data('toast', toast);

window.Alpine = Alpine;
Alpine.start();
