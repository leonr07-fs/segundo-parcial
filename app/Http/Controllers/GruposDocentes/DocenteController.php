<?php

namespace App\Http\Controllers\GruposDocentes;

use App\Http\Controllers\Controller;

use App\Services\GestionAcademica\ParametrizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * [CU14] Gestionar docentes
 * Vinculación UML: Administra el registro de docentes de nivelación y el cumplimiento de sus requisitos habilitantes.
 */

/**
 * CU14 - Gestionar solicitudes docentes
 *
 * Participantes del CU14 (Diagrama de Secuencia):
 * - Actor: Administrador
 * - Boundary: UI_Docente (Vue)
 * - Control: DocenteController (Actual)
 * - Control: ParametrizacionService
 * - Entity: Docente, User
 */
class DocenteController extends Controller
{
    public function __construct(private readonly ParametrizacionService $parametrizacionService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'docentes' => $this->parametrizacionService->listarDocentes()
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ci' => 'required|string|max:30|unique:docentes,ci|unique:users,numero_registro',
            'nombres' => 'required|string|max:120',
            'apellidos' => 'nullable|string|max:120',
            'correo' => 'required|email|max:150|unique:docentes,correo|unique:users,email',
            'telefono' => 'nullable|string|max:30',
            'activo' => 'boolean',
        ]);

        $resultado = $this->parametrizacionService->crearDocente($validated);

        return response()->json([
            'ok' => true,
            'message' => $resultado['credenciales']['correo_enviado']
                ? 'Docente creado correctamente. Se enviaron sus credenciales al correo registrado.'
                : 'Docente creado correctamente. No se pudo enviar el correo, muestre las credenciales generadas.',
            'data' => [
                'docente' => $resultado['docente'],
                'usuario' => $resultado['usuario'],
                'credenciales' => $resultado['credenciales'],
            ]
        ], 201);
    }
}
