/**
 * API client para el módulo de postulaciones CUP (CU02).
 *
 * Centraliza las llamadas Axios para no repetir URLs base
 * en cada componente, como indica la guía maestra.
 */

export async function fetchFormData() {
    const response = await window.axios.get('/api/postulaciones/create');

    return response.data;
}

export async function storePostulacion(data) {
    const response = await window.axios.post('/api/postulaciones', data);

    return response.data;
}
