import { ref } from 'vue';

export async function fetchInscripcionesPendientes(estado = 'pendientes') {
    const response = await window.axios.get(`/api/inscripciones/pendientes-validacion?estado=${estado}`);
    return response.data;
}

export async function fetchExpedienteDocumental(inscripcionId) {
    const response = await window.axios.get(`/api/inscripciones/${inscripcionId}/documentos`);
    return response.data;
}

export async function submitValidacionDocumental(inscripcionId, revisiones) {
    const response = await window.axios.post(`/api/inscripciones/${inscripcionId}/documentos/validar`, { revisiones });
    return response.data;
}
