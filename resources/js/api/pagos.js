export async function fetchInscripcionesPendientesPago() {
    const response = await window.axios.get('/api/inscripciones/pendientes-pago');
    return response.data;
}

export async function registrarPago(inscripcionId, payload) {
    const response = await window.axios.post(`/api/inscripciones/${inscripcionId}/pagos`, payload);
    return response.data;
}

export async function createPayPalOrder(payload = {}) {
    const response = await window.axios.post('/api/pagos/paypal/create-order', payload);
    return response.data;
}

export async function capturePayPalOrder(orderID, payload = {}) {
    const response = await window.axios.post('/api/pagos/paypal/capture-order', { orderID, ...payload });
    return response.data;
}

export async function createPublicPayPalOrder(ci) {
    const response = await window.axios.post('/api/public/paypal/create-order', { ci });
    return response.data;
}

export async function capturePublicPayPalOrder(orderID) {
    const response = await window.axios.post('/api/public/paypal/capture-order', { orderID });
    return response.data;
}
