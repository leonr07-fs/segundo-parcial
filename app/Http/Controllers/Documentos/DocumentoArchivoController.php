<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\Docentes\DocumentoDocente;
use App\Models\InscripcionPagos\Documento;
use App\Services\Documentos\DocumentoArchivoService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentoArchivoController extends Controller
{
    public function __construct(private readonly DocumentoArchivoService $documentoArchivoService)
    {
    }

    public function postulante(Documento $documento): BinaryFileResponse
    {
        $documento->loadMissing('inscripcion');

        return $this->documentoArchivoService->responderArchivo(
            $documento->archivo_path,
            $documento->tipo . '-' . $documento->id,
        );
    }

    public function docente(DocumentoDocente $documento): BinaryFileResponse
    {
        $documento->loadMissing('solicitudDocente');

        return $this->documentoArchivoService->responderArchivo(
            $documento->archivo_path,
            $documento->tipo . '-' . $documento->id,
        );
    }
}
