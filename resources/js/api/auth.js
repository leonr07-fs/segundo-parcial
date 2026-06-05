export async function login(credentials) {
    await refreshCsrfToken();

    const response = await window.axios.post('/login', credentials);

    return response.data;
}

export async function refreshCsrfToken() {
    return window.refreshCsrfToken();
}

export async function logout() {
    const response = await window.axios.post('/logout');

    return response.data;
}

export async function fetchAuthenticatedUser() {
    const response = await window.axios.get('/api/auth/user');

    return response.data;
}

export async function changePassword(payload) {
    const response = await window.axios.put('/api/auth/password', payload);

    return response.data;
}

export async function requestPasswordReset(payload) {
    const response = await window.axios.post('/forgot-password', payload);

    return response.data;
}

export async function resetPassword(payload) {
    const response = await window.axios.post('/reset-password', payload);

    return response.data;
}
