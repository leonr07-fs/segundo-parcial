export async function login(credentials) {
    const response = await window.axios.post('/login', credentials);

    return response.data;
}

export async function logout() {
    const response = await window.axios.post('/logout');

    return response.data;
}

export async function fetchAuthenticatedUser() {
    const response = await window.axios.get('/api/auth/user');

    return response.data;
}
