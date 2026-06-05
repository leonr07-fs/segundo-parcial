<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CU01: Autenticación de Usuario
 * Controlador para manejar el ingreso y cierre de sesión de todos los usuarios del sistema.
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
