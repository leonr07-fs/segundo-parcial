import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

async function refreshCsrfToken() {
    const response = await window.axios.get('/api/csrf-token', {
        skipCsrfRefresh: true,
    });
    const token = response.data?.data?.csrf_token;

    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        const meta = document.querySelector('meta[name="csrf-token"]');

        if (meta) {
            meta.setAttribute('content', token);
        }
    }

    return token;
}

window.refreshCsrfToken = refreshCsrfToken;

window.axios.interceptors.request.use(async (config) => {
    const method = (config.method || 'get').toLowerCase();
    const shouldRefresh = ['post', 'put', 'patch', 'delete'].includes(method)
        && !config.skipCsrfRefresh;

    if (shouldRefresh) {
        const token = await refreshCsrfToken();

        if (token) {
            config.headers = config.headers || {};
            config.headers['X-CSRF-TOKEN'] = token;
        }
    }

    return config;
});

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        if (error.response?.status === 419 && originalRequest && !originalRequest._csrfRetry) {
            originalRequest._csrfRetry = true;
            const token = await refreshCsrfToken();

            if (token) {
                originalRequest.headers = originalRequest.headers || {};
                originalRequest.headers['X-CSRF-TOKEN'] = token;
            }

            return window.axios(originalRequest);
        }

        return Promise.reject(error);
    }
);
