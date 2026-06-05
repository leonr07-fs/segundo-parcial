export async function uploadEvaluacionesCsv(file, numeroExamen) {
    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('numero_examen', numeroExamen);

    const response = await window.axios.post('/api/evaluaciones/importar', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    });

    return response.data;
}
