import { api } from '../api.js';
import { helpers } from './helpers.js';

const DEFAULT_SORT = 'created_at:desc';

export const PER_PAGE_OPTIONS = [10, 25, 50, 100];
export const DEFAULT_PER_PAGE = 25;

/**
 * Page numbers to show around the current page, with null as an ellipsis,
 * e.g. [1, null, 4, 5, 6, null, 12].
 */
export function pageWindow(current, total, radius = 1) {
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages = new Set([1, total]);
    for (let page = current - radius; page <= current + radius; page++) {
        if (page > 1 && page < total) pages.add(page);
    }

    const sorted = [...pages].sort((a, b) => a - b);
    const result = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) result.push(null);
        result.push(page);
    });

    return result;
}

export default () => ({
    ...helpers,
    loading: true,
    error: '',
    tickets: [],
    meta: { total: 0, pages: 0, page: 1, per_page: DEFAULT_PER_PAGE },
    perPageOptions: PER_PAGE_OPTIONS,
    perPage: DEFAULT_PER_PAGE,
    filters: { search: '', status: '', priority: '', category: '' },
    sort: DEFAULT_SORT,
    page: 1,
    requestId: 0,

    init() {
        // Restore state from the URL so filtered lists can be bookmarked and shared.
        const params = new URLSearchParams(window.location.search);

        Object.keys(this.filters).forEach((key) => {
            this.filters[key] = params.get(key) ?? '';
        });

        const orderby = params.get('orderby');
        if (orderby) this.sort = `${orderby}:${params.get('order') ?? 'desc'}`;
        this.page = Number(params.get('page') ?? 1) || 1;

        const perPage = Number(params.get('per_page'));
        if (PER_PAGE_OPTIONS.includes(perPage)) this.perPage = perPage;

        this.load();
    },

    query() {
        const [orderby, order] = this.sort.split(':');

        return { ...this.filters, orderby, order, page: this.page, per_page: this.perPage };
    },

    async load() {
        const requestId = ++this.requestId;
        this.loading = true;
        this.error = '';

        try {
            const response = await api('tickets', { query: this.query() });

            if (requestId !== this.requestId) return; // a newer request superseded this one

            // A bookmarked page beyond the last one: show the last page instead.
            if (response.data.length === 0 && response.meta.pages > 0 && this.page > response.meta.pages) {
                this.page = response.meta.pages;
                this.load();

                return;
            }

            this.tickets = response.data;
            this.meta = response.meta;
        } catch (error) {
            if (requestId === this.requestId) this.error = error.message;
        } finally {
            if (requestId === this.requestId) this.loading = false;
        }

        this.syncUrl();
    },

    syncUrl() {
        const params = new URLSearchParams();
        const query = this.query();

        ['search', 'status', 'priority', 'category'].forEach((key) => query[key] && params.set(key, query[key]));
        if (this.sort !== DEFAULT_SORT) {
            params.set('orderby', query.orderby);
            params.set('order', query.order);
        }
        if (this.page > 1) params.set('page', this.page);
        if (this.perPage !== DEFAULT_PER_PAGE) params.set('per_page', this.perPage);

        const search = params.toString();
        window.history.replaceState({}, '', search ? `?${search}` : window.location.pathname);
    },

    apply() {
        this.page = 1;
        this.load();
    },

    go(page) {
        if (page < 1 || page > this.meta.pages) return;
        this.page = page;
        this.load();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    setPerPage(value) {
        const perPage = Number(value);
        if (!PER_PAGE_OPTIONS.includes(perPage)) return;

        this.perPage = perPage;
        this.apply();
    },

    pages() {
        return pageWindow(this.meta.page, this.meta.pages);
    },

    reset() {
        this.filters = { search: '', status: '', priority: '', category: '' };
        this.sort = DEFAULT_SORT;
        this.apply();
    },

    hasFilters() {
        return Object.values(this.filters).some(Boolean) || this.sort !== DEFAULT_SORT;
    },

    open(id) {
        window.location.href = this.ticketUrl(id);
    },

    summary() {
        return this.meta.total === 1 ? '1 ticket' : `${this.meta.total} tickets`;
    },

    pageLabel() {
        return `Page ${this.meta.page} of ${this.meta.pages}`;
    },

    rangeLabel() {
        if (this.meta.total === 0) return 'No tickets';

        const first = (this.meta.page - 1) * this.meta.per_page + 1;
        const last = Math.min(this.meta.page * this.meta.per_page, this.meta.total);

        return `Showing ${first}–${last} of ${this.meta.total}`;
    },
});
