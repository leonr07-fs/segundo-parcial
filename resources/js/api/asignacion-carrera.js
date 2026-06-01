import axios from 'axios';

export const getAsignaciones = async () => {
    const response = await axios.get('/api/asignaciones-carrera');
    return response.data;
};

export const ejecutarAsignacion = async () => {
    const response = await axios.post('/api/asignaciones-carrera/ejecutar');
    return response.data;
};
