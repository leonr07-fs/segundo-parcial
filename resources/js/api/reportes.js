import axios from 'axios';

export const getCatalogoReportes = async () => {
    const response = await axios.get('/api/reportes/catalogo');
    return response.data;
};

export const getReporteEstatico = async (tipo, filtros = {}) => {
    const response = await axios.get(`/api/reportes/estatico/${tipo}`, { params: filtros });
    return response.data;
};

export const generarReporteDinamico = async ({ modulo, columnas, filtros }) => {
    const response = await axios.post('/api/reportes/dinamico', {
        modulo,
        columnas,
        filtros,
    });
    return response.data;
};

export const getReporteAsistenciasAdmin = async (filtros = {}) => {
    const response = await axios.get('/api/admin/asistencias', { params: filtros });
    return response.data;
};
