import { ref } from 'vue';

export async function fetchInscripcionesPendientesPago() {
    const response = await window.axios.get('/api/inscripciones/pendientes-pago');
    return response.data;
}

export async function registrarPago(inscripcionId, payload) {
    const response = await window.axios.post(`/api/inscripciones/${inscripcionId}/pagos`, payload);
    return response.data;
}
