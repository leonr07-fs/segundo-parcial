<?php

namespace App\Services\Documentos;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentoArchivoService
{
    /**
     * Sirve un archivo almacenado en el disco public (o private como respaldo).
     */
    public function responderArchivo(?string $archivoPath, string $nombreDescarga = 'documento'): BinaryFileResponse
    {
        if ($archivoPath === null || trim($archivoPath) === '') {
            throw new NotFoundHttpException('El documento no tiene archivo asociado.');
        }

        $archivoPath = str_replace('\\', '/', $archivoPath);

        if (str_contains($archivoPath, '..')) {
            throw new NotFoundHttpException('Ruta de archivo no valida.');
        }

        foreach (['public', 'local'] as $disco) {
            if (Storage::disk($disco)->exists($archivoPath)) {
                $rutaAbsoluta = Storage::disk($disco)->path($archivoPath);
                $nombreArchivo = basename($archivoPath) ?: $nombreDescarga;
                $mime = Storage::disk($disco)->mimeType($archivoPath) ?: 'application/octet-stream';

                return response()->file($rutaAbsoluta, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="' . addslashes($nombreArchivo) . '"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        throw new NotFoundHttpException('No se encontro el archivo del documento en el almacenamiento.');
    }
}
