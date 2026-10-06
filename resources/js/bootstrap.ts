import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo (and pusher-js, ~70 KB) is deliberately not imported here: only the job
// board listens for live updates, and it loads '@/echo' itself.
