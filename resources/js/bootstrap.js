import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Send cookies and the CSRF token on API requests. As of axios 1.6 the
// X-XSRF-TOKEN header is no longer attached automatically for same-origin
// requests — it must be opted into explicitly. Required for Sanctum
// stateful (session) auth on the /api routes; without it Laravel returns 419.
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
