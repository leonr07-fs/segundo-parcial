/**
 * URLs seguras para visualizar documentación (requieren sesión autenticada).
 */
export function urlDocumentoPostulante(documentoId) {
    return `/api/documentos-postulante/${documentoId}/archivo`;
}

export function urlDocumentoDocente(documentoId) {
    return `/api/documentos-docentes/${documentoId}/archivo`;
}
