export async function uploadEvaluacionesCsv(file) {
    const formData = new FormData();
    formData.append('archivo', file);

    const response = await window.axios.post('/api/evaluaciones/importar', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    });

    return response.data;
}
