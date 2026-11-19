export default () => ({
  visible: false,
  message: '',
  type: 'success',
  timer: null,

  show({ message, type = 'success' }) {
    this.message = message;
    this.type = type;
    this.visible = true;

    clearTimeout(this.timer);
    this.timer = setTimeout(() => (this.visible = false), type === 'error' ? 6000 : 3000);
  },
});
