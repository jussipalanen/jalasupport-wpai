import { api } from '../api.js';
import { helpers } from './helpers.js';

export default () => ({
  ...helpers,
  saving: false,
  error: '',
  errors: {},
  form: {
    title: '',
    description: '',
    customer_name: '',
    customer_email: '',
    category: 'general_inquiry',
    priority: 'normal',
  },

  async submit() {
    this.saving = true;
    this.error = '';
    this.errors = {};

    try {
      const response = await api('tickets', { method: 'POST', body: this.form });
      window.location.href = this.ticketUrl(response.data.id);
    } catch (error) {
      this.errors = error.errors ?? {};
      this.error = error.message;
      this.saving = false;
    }
  },
});
