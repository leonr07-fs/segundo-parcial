<?php

namespace App\Http\Controllers\SeguridadUsuarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CU01 - Autenticación de usuario
 * Permite iniciar y cerrar sesión validando credenciales, controlando bloqueos y registrando eventos de acceso.
 *
 * Participantes del CU01 (Diagrama de Secuencia):
 * - Actor: Usuario
 * - Boundary: UI_Login (Vue)
 * - Control: AuthenticatedSessionController (Actual)
 * - Control: LoginService
 * - Entity: User
 * - Control: AuditLogService
 */
class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly LoginService $loginService)
    {
    }

    public function store(LoginRequest $request): JsonResponse
    {
        try {
            $data = $this->loginService->login($request->validated(), $request);

            return response()->json([
                'ok' => true,
                'message' => 'Inicio de sesion correcto.',
                'data' => $data,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'Las credenciales no son validas.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (HttpException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'errors' => [],
            ], $exception->getStatusCode());
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->loginService->logout($request);

        return response()->json([
            'ok' => true,
            'message' => 'Sesion cerrada correctamente.',
            'data' => [
                'redirect_url' => '/login',
            ],
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Usuario autenticado.',
            'data' => [
                'user' => $this->loginService->userContext($request->user()),
            ],
        ]);
    }
}
