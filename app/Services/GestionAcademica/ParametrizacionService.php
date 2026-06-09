<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\Aula;
use App\Models\GestionAcademica\Docente;
use App\Models\GestionAcademica\Grupo;
use App\Models\GestionAcademica\GrupoMateria;
use App\Models\GestionAcademica\Horario;
use App\Models\GestionAcademica\Materia;
use App\Models\Seguridad\User;
use App\Support\FicctAulas;
use App\Support\States\GestionState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * [CU08] y [CU13]/[CU15] Parametrización y asignación
 * Vinculación UML: Servicio de configuración de materias, aulas y asignación de horarios/docentes.
 */

class ParametrizacionService
{
    public function __construct(private readonly GestionVigenteService $gestionVigenteService)
    {
    }

    public function listarMaterias()
    {
        return Materia::orderBy('nombre')->orderBy('codigo')->get();
    }

    public function crearMateria(array $datos): Materia
    {
        return Materia::create([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'activa' => $datos['activa'] ?? true,
        ]);
    }

    public function actualizarEstadoMateria(int $materiaId, bool $activa): Materia
    {
        $materia = Materia::findOrFail($materiaId);
        $materia->update(['activa' => $activa]);

        return $materia->fresh();
    }

    public function listarAulas()
    {
        return Aula::whereIn('codigo', FicctAulas::TODAS)->orderBy('codigo')->get();
    }

    public function crearAula(array $datos): Aula
    {
        if (! FicctAulas::esAulaFicct($datos['codigo'])) {
            throw new \Exception('El aula no pertenece al catalogo fijo del Modulo 236 FICCT.');
        }

        return Aula::updateOrCreate(['codigo' => $datos['codigo']], [
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'] ?? null,
            'capacidad' => $datos['capacidad'] ?? null,
            'ubicacion' => $datos['ubicacion'] ?? null,
            'activa' => $datos['activa'] ?? true,
        ]);
    }

    public function actualizarCapacidadAula(int $aulaId, int $capacidad): Aula
    {
        $aula = Aula::findOrFail($aulaId);

        if (! FicctAulas::esAulaFicct($aula->codigo)) {
            throw new \Exception('El aula no pertenece al catalogo fijo del Modulo 236 FICCT.');
        }

        $aula->update(['capacidad' => $capacidad]);

        return $aula->fresh();
    }

    public function listarGrupos(?int $gestionId = null)
    {
        $gestionVigente = $this->gestionVigenteService->actual();
        $gestionId = $gestionId ?? $gestionVigente?->id ?? 0;

        if (!$gestionVigente || $gestionId !== $gestionVigente->id) {
            return collect();
        }

        return Grupo::with(['gestion', 'aula', 'materias'])
            ->where('gestion_id', $gestionId)
            ->orderBy('codigo')
            ->get();
    }

    public function crearGrupo(array $datos): Grupo
    {
        $this->asegurarGestionEditable((int) $datos['gestion_id']);

        return Grupo::create([
            'gestion_id' => $datos['gestion_id'],
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'] ?? null,
            'cupo_maximo' => $datos['cupo_maximo'] ?? Grupo::CUPO_MAXIMO,
            'aula_id' => $datos['aula_id'] ?? null,
            'estado' => $datos['estado'] ?? 'configuracion',
        ]);
    }

    public function listarDocentes()
    {
        $gestionVigente = $this->gestionVigenteService->actual();

        if (!$gestionVigente) {
            return collect();
        }

        return Docente::query()
            ->where('activo', true)
            ->where(function ($query) use ($gestionVigente) {
                $query->whereHas('repostulaciones', function ($q) use ($gestionVigente) {
                    $q->where('gestion_id', $gestionVigente->id)
                      ->where('estado', \App\Support\States\RepostulacionDocenteState::APROBADA);
                })
                ->orWhereHas('grupoMaterias.grupo', function ($q) use ($gestionVigente) {
                    $q->where('gestion_id', $gestionVigente->id);
                })
                ->orWhereDoesntHave('grupoMaterias'); // Newly created/registered docentes with no history
            })
            ->orderBy('nombres')
            ->get();
    }

    public function crearDocente(array $datos): array
    {
        return DB::transaction(function () use ($datos) {
            $docente = Docente::create([
                'ci' => $datos['ci'],
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'] ?? null,
                'correo' => $datos['correo'],
                'telefono' => $datos['telefono'] ?? null,
                'activo' => $datos['activo'] ?? true,
            ]);

            $credentialService = app(CredentialService::class);
            $numeroRegistro = $docente->ci;
            $passwordTemporal = $docente->ci;
            
            $usuarioExistente = User::where('email', $docente->correo)->first();
            if ($usuarioExistente && $usuarioExistente->role !== User::ROLE_DOCENTE) {
                throw new \Exception('El correo del docente pertenece a un usuario con un rol diferente.');
            }

            $user = User::updateOrCreate([
                'email' => $docente->correo,
            ], [
                'name' => trim($docente->nombres . ' ' . ($docente->apellidos ?? '')),
                'numero_registro' => $numeroRegistro,
                'password' => $passwordTemporal,
                'role' => User::ROLE_DOCENTE,
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            $notificado = $this->enviarCredencialesDocente($docente, $numeroRegistro, $passwordTemporal);

            return [
                'docente' => $docente,
                'usuario' => $user,
                'credenciales' => [
                    'numero_registro' => $numeroRegistro,
                    'password_temporal' => $passwordTemporal,
                    'correo_enviado' => $notificado,
                ],
            ];
        });
    }

    private function enviarCredencialesDocente(Docente $docente, string $numeroRegistro, string $passwordTemporal): bool
    {
        try {
            Mail::raw(
                "Bienvenido al sistema CUP FICCT.\n\n" .
                "Sus credenciales de acceso son:\n" .
                "Numero de registro: {$numeroRegistro}\n" .
                "Contrasena temporal: {$passwordTemporal}\n\n" .
                "Ingrese al sistema y conserve estos datos de forma segura.",
                function ($message) use ($docente) {
                    $message->to($docente->correo)
                        ->subject('Credenciales de acceso CUP FICCT');
                }
            );

            return true;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar correo de credenciales al docente.', [
                'docente_id' => $docente->id,
                'correo' => $docente->correo,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function listarMateriasDeGrupo(int $grupoId)
    {
        return GrupoMateria::with(['materia', 'docente', 'horarios'])
            ->where('grupo_id', $grupoId)
            ->orderBy('id')
            ->get()
            ->map(fn (GrupoMateria $grupoMateria) => [
                'id' => $grupoMateria->id,
                'materia_id' => $grupoMateria->materia_id,
                'codigo' => $grupoMateria->materia?->codigo,
                'nombre' => $grupoMateria->materia?->nombre,
                'docente_id' => $grupoMateria->docente_id,
                'docente_nombre' => $grupoMateria->docente
                    ? trim($grupoMateria->docente->nombres . ' ' . ($grupoMateria->docente->apellidos ?? ''))
                    : null,
                'horarios' => $grupoMateria->horarios->map(fn (Horario $horario) => [
                    'id' => $horario->id,
                    'dia_semana' => $horario->dia_semana,
                    'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                    'hora_fin' => substr((string) $horario->hora_fin, 0, 5),
                    'modalidad' => $horario->modalidad,
                    'aula_id' => $horario->aula_id,
                ])->values(),
            ])->values();
    }

    public function asignarMateriaAGrupo(int $grupoId, array $datos)
    {
        return DB::transaction(function () use ($grupoId, $datos) {
            $grupo = Grupo::with('gestion')->findOrFail($grupoId);
            $this->asegurarGestionEditable((int) $grupo->gestion_id);

            if ($grupo->materias()->where('materia_id', $datos['materia_id'])->exists()) {
                throw new \Exception('La materia ya esta asignada a este grupo.');
            }

            $grupoMateria = GrupoMateria::create([
                'grupo_id' => $grupo->id,
                'materia_id' => $datos['materia_id'],
                'docente_id' => $datos['docente_id'] ?? null,
            ]);

            $grupoMateria->horarios()->create([
                'aula_id' => $grupo->aula_id,
                'dia_semana' => $datos['dia_semana'],
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin' => $datos['hora_fin'],
                'modalidad' => 'presencial',
            ]);

            return $grupoMateria->load(['materia', 'docente', 'horarios']);
        });
    }

    private function asegurarGestionEditable(int $gestionId): void
    {
        $cerrada = \App\Models\GestionAcademica\Gestion::whereKey($gestionId)
            ->where('estado', GestionState::CERRADA)
            ->exists();

        if ($cerrada) {
            throw new \Exception('La gestion esta cerrada definitivamente. No se permiten movimientos, solo consulta administrativa.');
        }
    }
}
