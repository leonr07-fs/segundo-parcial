import axios from 'axios';

export const getAsignaciones = async (gestionId = null) => {
    const response = await axios.get('/api/asignaciones-carrera', {
        params: gestionId ? { gestion_id: gestionId } : {},
    });
    return response.data;
};

export const ejecutarAsignacion = async (gestionId) => {
    const response = await axios.post('/api/asignaciones-carrera/ejecutar', { gestion_id: gestionId });
    return response.data;
};

export const guardarCuposCarrera = async (cupos, gestionId) => {
    const response = await axios.put('/api/asignaciones-carrera/cupos', { cupos, gestion_id: gestionId });
    return response.data;
};
