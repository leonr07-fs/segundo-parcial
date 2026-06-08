import axios from 'axios';

export async function registrarRepostulacionDocente({ ci, correo }) {
    const { data } = await axios.post('/api/public/repostulacion-docente', { ci, correo });
    return data;
}

export async function fetchRepostulacionesDocentes(params = {}) {
    const { data } = await axios.get('/api/admin/repostulaciones-docentes', { params });
    return data;
}

export async function aprobarRepostulacionDocente(id) {
    const { data } = await axios.post(`/api/admin/repostulaciones-docentes/${id}/aprobar`);
    return data;
}

export async function rechazarRepostulacionDocente(id, observacion) {
    const { data } = await axios.post(`/api/admin/repostulaciones-docentes/${id}/rechazar`, { observacion });
    return data;
}
