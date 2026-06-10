<?php

namespace App\Http\Controllers\SeguridadUsuarios;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    public function change(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => $this->strongPasswordRules(),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return $this->validationError([
                'current_password' => ['La contrasena actual no es correcta.'],
            ]);
        }

        $user->forceFill([
            'password' => $request->input('password'),
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => 'Contrasena actualizada correctamente.',
        ]);
    }

    public function forgot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $email = $request->input('email');
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        if (User::where('email', $email)->exists()) {
            $this->sendResetLink($email, $token);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Si el correo esta registrado, se envio un enlace para cambiar la contrasena.',
            'data' => app()->environment(['local', 'testing']) ? ['reset_token' => $token] : [],
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => $this->strongPasswordRules(),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $record = DB::table('password_reset_tokens')->where('email', $request->input('email'))->first();

        if ($record === null || ! Hash::check($request->input('token'), $record->token)) {
            return $this->validationError([
                'token' => ['El enlace de recuperacion no es valido o ya expiro.'],
            ]);
        }

        $user = User::where('email', $request->input('email'))->firstOrFail();
        $user->forceFill([
            'password' => $request->input('password'),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Contrasena restablecida correctamente.',
        ]);
    }

    private function strongPasswordRules(): array
    {
        return ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()];
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Revise los datos ingresados.',
            'errors' => $errors,
        ], 422);
    }

    private function sendResetLink(string $email, string $token): void
    {
        $url = url('/login') . '?reset_token=' . urlencode($token) . '&email=' . urlencode($email);

        try {
            Mail::raw(
                "Recibimos una solicitud para cambiar su contrasena CUP FICCT.\n\n" .
                "Use este enlace para definir una nueva contrasena:\n{$url}\n\n" .
                "Si usted no solicito este cambio, ignore este mensaje.",
                function ($message) use ($email) {
                    $message->to($email)->subject('Recuperacion de contrasena CUP FICCT');
                }
            );
        } catch (\Throwable) {
            // El token queda disponible para reintento de envio o soporte administrativo.
        }
    }
}
