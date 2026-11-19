import { api } from '../api.js';
import { helpers } from './helpers.js';

export default () => ({
    ...helpers,
    loading: true,
    error: '',
    stats: {},
    recent: [],

    async init() {
        try {
            const [stats, recent] = await Promise.all([
                api('tickets/stats'),
                api('tickets', { query: { orderby: 'updated_at', order: 'desc', per_page: 6 } }),
            ]);

            this.stats = stats.data;
            this.recent = recent.data;
        } catch (error) {
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },
});
