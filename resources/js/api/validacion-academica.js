import axios from 'axios';

export const getValidacionesPendientes = async (page = 1) => {
    const response = await axios.get(`/api/validaciones-academicas?page=${page}`);
    return response.data;
};
