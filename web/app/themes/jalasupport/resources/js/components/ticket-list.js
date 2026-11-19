import { api } from '../api.js';
import { helpers } from './helpers.js';

const DEFAULT_SORT = 'created_at:desc';

export default () => ({
  ...helpers,
  loading: true,
  error: '',
  tickets: [],
  meta: { total: 0, pages: 0, page: 1, per_page: 20 },
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

    this.load();
  },

  query() {
    const [orderby, order] = this.sort.split(':');

    return { ...this.filters, orderby, order, page: this.page, per_page: this.meta.per_page };
  },

  async load() {
    const requestId = ++this.requestId;
    this.loading = true;
    this.error = '';

    try {
      const response = await api('tickets', { query: this.query() });

      if (requestId !== this.requestId) return; // a newer request superseded this one

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
});
