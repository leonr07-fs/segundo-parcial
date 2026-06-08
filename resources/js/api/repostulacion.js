import axios from 'axios';

export async function validarRepostulacion({ ci, correo }) {
    const { data } = await axios.post('/api/public/repostulacion/validar', { ci, correo });
    return data;
}

export async function prepararRepostulacion({ ci, correo }) {
    const { data } = await axios.post('/api/public/repostulacion/preparar', { ci, correo });
    return data;
}

export async function createRepostulacionPayPalOrder({ ci, correo }) {
    const { data } = await axios.post('/api/public/repostulacion/paypal/create-order', { ci, correo });
    return data;
}

export async function captureRepostulacionPayPalOrder({ orderID, ci, correo }) {
    const { data } = await axios.post('/api/public/repostulacion/paypal/capture-order', { orderID, ci, correo });
    return data;
}
