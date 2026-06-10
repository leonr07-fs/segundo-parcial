<?php

namespace App\Services\GruposDocentes;

use App\Services\GestionAcademica\CredentialService;

use App\Models\GestionAcademica\Docente;
use App\Models\Docentes\DocumentoDocente;
use App\Models\GestionAcademica\Materia;
use App\Models\Docentes\SolicitudDocente;
use App\Models\Seguridad\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SolicitudDocenteService
{
    private CredentialService $credentialService;

    public const DOCUMENTOS_OBLIGATORIOS = [
        'ci',
        'titulo_profesional',
        'diplomado',
        'maestria',
        'cv',
    ];

    public function __construct(?CredentialService $credentialService = null)
    {
        $this->credentialService = $credentialService ?? app(CredentialService::class);
    }

    public function datosFormulario(): array
    {
        return [
            'materias' => Materia::where('activa', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'documentos_obligatorios' => self::DOCUMENTOS_OBLIGATORIOS,
        ];
    }

    public function registrar(array $datos): SolicitudDocente
    {
        return DB::transaction(function () use ($datos) {
            if (
                SolicitudDocente::where('correo', $datos['correo'])->exists()
                || Docente::where('correo', $datos['correo'])->exists()
                || User::where('email', $datos['correo'])->exists()
            ) {
                throw ValidationException::withMessages([
                    'correo' => ['Este correo ya fue registrado en una solicitud docente.'],
                ]);
            }

            if (SolicitudDocente::where('ci', $datos['ci'])->where('materia_id', $datos['materia_id'])->exists()) {
                throw new \DomainException('Ya existe una solicitud docente para esta materia con el mismo CI.');
            }

            if (
                SolicitudDocente::where('ci', $datos['ci'])->exists()
                || Docente::where('ci', $datos['ci'])->exists()
                || User::where('numero_registro', $datos['ci'])->exists()
            ) {
                throw ValidationException::withMessages([
                    'ci' => ['Este CI ya fue registrado en una solicitud docente.'],
                ]);
            }

            $solicitud = SolicitudDocente::create([
                'ci' => $datos['ci'],
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'] ?? null,
                'correo' => $datos['correo'],
                'telefono' => $datos['telefono'] ?? null,
                'materia_id' => $datos['materia_id'],
                'profesion' => $datos['profesion'],
                'estado' => 'pendiente',
            ]);

            foreach (self::DOCUMENTOS_OBLIGATORIOS as $tipo) {
                /** @var UploadedFile $archivo */
                $archivo = $datos['documentos'][$tipo];
                $path = $archivo->store("docentes/solicitudes/{$solicitud->id}", 'public');

                DocumentoDocente::create([
                    'solicitud_docente_id' => $solicitud->id,
                    'tipo' => $tipo,
                    'archivo_path' => $path,
                    'estado' => 'pendiente',
                ]);
            }

            $this->notificarAdministradores($solicitud);

            return $solicitud->load(['materia', 'documentos']);
        });
    }

    public function listar(array $filtros = [])
    {
        return SolicitudDocente::with(['materia', 'documentos', 'revisadoPor'])
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->latest('id')
            ->paginate(25);
    }

    public function revisarDocumento(int $documentoId, User $admin, string $estado, ?string $observacion = null): DocumentoDocente
    {
        $documento = DocumentoDocente::findOrFail($documentoId);
        $documento->update([
            'estado' => $estado,
            'observacion' => $observacion,
            'revisado_por' => $admin->id,
            'revisado_en' => now(),
        ]);

        return $documento->fresh();
    }

    public function observar(int $solicitudId, User $admin, ?string $observacion): SolicitudDocente
    {
        $solicitud = SolicitudDocente::findOrFail($solicitudId);
        $solicitud->update([
            'estado' => 'observada',
            'observacion' => $observacion,
            'revisado_por' => $admin->id,
            'revisado_en' => now(),
        ]);

        return $solicitud->fresh(['materia', 'documentos']);
    }

    public function rechazar(int $solicitudId, User $admin, ?string $observacion): SolicitudDocente
    {
        $solicitud = SolicitudDocente::findOrFail($solicitudId);
        $solicitud->update([
            'estado' => 'rechazada',
            'observacion' => $observacion,
            'revisado_por' => $admin->id,
            'revisado_en' => now(),
        ]);

        return $solicitud->fresh(['materia', 'documentos']);
    }

    public function aprobar(int $solicitudId, User $admin): array
    {
        return DB::transaction(function () use ($solicitudId, $admin) {
            $solicitud = SolicitudDocente::with('documentos')->findOrFail($solicitudId);
            $this->validarAprobacion($solicitud);

            $docente = Docente::updateOrCreate([
                'ci' => $solicitud->ci,
            ], [
                'nombres' => $solicitud->nombres,
                'apellidos' => $solicitud->apellidos,
                'correo' => $solicitud->correo,
                'telefono' => $solicitud->telefono,
                'activo' => true,
            ]);

            $codigoDocente = $solicitud->ci;
            $passwordTemporal = $solicitud->ci;

            $usuarioExistente = User::where('email', $solicitud->correo)->first();
            if ($usuarioExistente && $usuarioExistente->role !== User::ROLE_DOCENTE) {
                throw new \DomainException('El correo del docente pertenece a un usuario con un rol diferente.');
            }

            $user = User::updateOrCreate([
                'email' => $solicitud->correo,
            ], [
                'name' => trim($solicitud->nombres . ' ' . ($solicitud->apellidos ?? '')),
                'numero_registro' => $codigoDocente,
                'password' => $passwordTemporal,
                'role' => User::ROLE_DOCENTE,
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            $solicitud->update([
                'estado' => 'aprobada',
                'observacion' => null,
                'revisado_por' => $admin->id,
                'revisado_en' => now(),
            ]);

            $correoEnviado = $this->enviarCredenciales($solicitud, $codigoDocente, $passwordTemporal);

            return [
                'solicitud' => $solicitud->fresh(['materia', 'documentos']),
                'docente' => $docente,
                'usuario' => $user,
                'credenciales' => [
                    'codigo_docente' => $codigoDocente,
                    'password_temporal' => $passwordTemporal,
                    'correo_enviado' => $correoEnviado,
                ],
            ];
        });
    }

    private function validarAprobacion(SolicitudDocente $solicitud): void
    {
        $profesion = strtolower($solicitud->profesion);

        $materiaNombre = strtolower($solicitud->materia?->nombre ?? '');
        $materiaCodigo = strtolower($solicitud->materia?->codigo ?? '');

        // 1. Filtro de Grado Mínimo
        $tieneGradoMinimo = str_contains($profesion, 'licenciad') 
            || str_contains($profesion, 'lic.')
            || str_contains($profesion, 'ingenier')
            || str_contains($profesion, 'ing.');

        if (!$tieneGradoMinimo) {
            throw new \DomainException('La profesion debe acreditar que el postulante posee como minimo el grado de Licenciatura o Ingenieria.');
        }

        // 2. Filtro de Exclusión
        $exclusiones = ['econom', 'comercial', 'financier', 'administracion', 'auditor', 'contad', 'profesor'];
        foreach ($exclusiones as $exclusion) {
            if (str_contains($profesion, $exclusion)) {
                throw new \DomainException('No se admiten profesionales del area economica o con rango de profesor.');
            }
        }

        // 3. Filtro de Afinidad por Materia
        $esComputacion = str_contains($materiaNombre, 'computacion') || str_contains($materiaNombre, 'computación') || str_contains($materiaCodigo, 'com');
        $esMatematicas = str_contains($materiaNombre, 'matematica') || str_contains($materiaNombre, 'matemática') || str_contains($materiaCodigo, 'mat');
        $esFisica = str_contains($materiaNombre, 'fisica') || str_contains($materiaNombre, 'física') || str_contains($materiaCodigo, 'fis');
        $esIngles = str_contains($materiaNombre, 'ingles') || str_contains($materiaNombre, 'inglés') || str_contains($materiaCodigo, 'ing');

        $esAfin = false;

        if ($esComputacion) {
            $afines = ['sistemas', 'informatic', 'software', 'redes', 'telecomunicacion', 'computacion'];
            foreach ($afines as $afin) {
                if (str_contains($profesion, $afin)) {
                    $esAfin = true;
                    break;
                }
            }
            if (!$esAfin) {
                throw new \DomainException('Para Computacion, la profesion debe ser de una carrera tecnologica pura afin.');
            }
        } elseif ($esMatematicas) {
            $afines = ['matematic', 'civil', 'electromecanic', 'industrial', 'sistemas', 'informatic', 'petroler', 'quimic'];
            foreach ($afines as $afin) {
                if (str_contains($profesion, $afin)) {
                    $esAfin = true;
                    break;
                }
            }
            if (!$esAfin) {
                throw new \DomainException('Para Matematicas, la profesion debe tener un fuerte tronco matematico afin.');
            }
        } elseif ($esFisica) {
            $afines = ['fisic', 'civil', 'electromecanic', 'industrial', 'mecanic', 'petroler', 'mecatron'];
            foreach ($afines as $afin) {
                if (str_contains($profesion, $afin)) {
                    $esAfin = true;
                    break;
                }
            }
            if (!$esAfin) {
                throw new \DomainException('Para Fisica, la profesion debe tener aplicacion fisica intensiva.');
            }
        } elseif ($esIngles) {
            // Para inglés, aceptamos cualquier ingeniero o licenciado (que ya pasó el filtro 1 y 2).
            $esAfin = true;
        }

        $documentos = $solicitud->documentos->keyBy('tipo');

        foreach (self::DOCUMENTOS_OBLIGATORIOS as $tipo) {
            if (! $documentos->has($tipo) || $documentos[$tipo]->estado !== 'aprobado') {
                throw new \DomainException("No se puede aprobar: el documento {$tipo} debe estar aprobado.");
            }
        }
    }

    private function enviarCredenciales(SolicitudDocente $solicitud, string $codigoDocente, string $passwordTemporal): bool
    {
        try {
            Mail::raw(
                "Su solicitud docente CUP FICCT fue aprobada.\n\n" .
                "Codigo docente: {$codigoDocente}\n" .
                "Contrasena temporal: {$passwordTemporal}\n\n" .
                "Ingrese al sistema y cambie su contrasena cuando corresponda.",
                function ($message) use ($solicitud) {
                    $message->to($solicitud->correo)
                        ->subject('Credenciales docente CUP FICCT');
                }
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function notificarAdministradores(SolicitudDocente $solicitud): void
    {
        $admins = User::where('role', User::ROLE_ADMIN)->where('is_active', true)->get();

        foreach ($admins as $admin) {
            try {
                Mail::raw(
                    "Nueva solicitud docente recibida.\n\n" .
                    "Nombre: {$solicitud->nombres} {$solicitud->apellidos}\n" .
                    "CI: {$solicitud->ci}\n" .
                    "Correo: {$solicitud->correo}\n" .
                    "Materia: {$solicitud->materia?->nombre}\n\n" .
                    "Ingrese al sistema para revisar los documentos.",
                    function ($message) use ($admin) {
                        $message->to($admin->email)->subject('Nueva solicitud docente CUP');
                    }
                );
            } catch (\Throwable) {
                // La solicitud docente no debe fallar si el correo no esta configurado.
            }
        }
    }
}
