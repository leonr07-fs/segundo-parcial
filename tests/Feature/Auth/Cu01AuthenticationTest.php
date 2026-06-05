<?php

namespace Tests\Feature\Auth;

use App\Models\GestionAcademica\Gestion;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\InscripcionPagos\Postulante;
use App\Models\Seguridad\User;
use App\Support\States\GestionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Cu01AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_receives_role_context(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@cup.test',
            'numero_registro' => 'admin123',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'numero_registro' => 'admin123',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.email', 'admin@cup.test')
            ->assertJsonPath('data.user.role', User::ROLE_ADMIN)
            ->assertJsonPath('data.redirect_url', '/admin/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login.success',
        ]);
    }

    public function test_invalid_credentials_are_rejected_without_revealing_sensitive_details(): void
    {
        $user = User::factory()->create([
            'email' => 'docente@cup.test',
            'numero_registro' => 'docente123',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_DOCENTE,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'numero_registro' => 'docente123',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Las credenciales no son validas.');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'failed_login_attempts' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login.failed',
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'postulante@cup.test',
            'numero_registro' => 'postulante123',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_POSTULANTE,
            'is_active' => false,
        ]);

        $response = $this->postJson('/login', [
            'numero_registro' => 'postulante123',
            'password' => 'secret123',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'La cuenta no esta habilitada.');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login.inactive',
        ]);
    }

    public function test_postulante_no_puede_ingresar_cuando_su_gestion_esta_cerrada(): void
    {
        $user = User::factory()->create([
            'email' => 'cerrado@cup.test',
            'numero_registro' => '9000999',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_POSTULANTE,
            'is_active' => true,
        ]);

        $postulante = Postulante::factory()->create([
            'correo' => 'cerrado@cup.test',
            'ci' => '9000999',
        ]);

        Inscripcion::factory()->create([
            'postulante_id' => $postulante->id,
            'gestion_id' => Gestion::factory()->create(['estado' => GestionState::CERRADA])->id,
        ]);

        $response = $this->postJson('/login', [
            'numero_registro' => '9000999',
            'password' => 'secret123',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'La gestion CUP ya esta cerrada. El acceso del postulante fue deshabilitado para esta gestion.');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login.closed_gestion',
        ]);
    }

    public function test_repeated_failed_logins_temporarily_lock_the_account(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@cup.test',
            'numero_registro' => 'locked123',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/login', [
                'numero_registro' => 'locked123',
                'password' => 'bad-password',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until);

        $response = $this->postJson('/login', [
            'numero_registro' => 'locked123',
            'password' => 'secret123',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'La cuenta esta bloqueada temporalmente.');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login.locked',
        ]);
    }

    public function test_autoridad_user_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'autoridad@cup.test',
            'numero_registro' => 'autoridad123',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_AUTORIDAD,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'numero_registro' => 'autoridad123',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.role', User::ROLE_AUTORIDAD)
            ->assertJsonPath('data.redirect_url', '/admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_coordinador_user_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'coordinador@cup.test',
            'numero_registro' => 'coordinador123',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_COORDINADOR,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'numero_registro' => 'coordinador123',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.role', User::ROLE_COORDINADOR)
            ->assertJsonPath('data.redirect_url', '/admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_can_change_password_with_strong_password(): void
    {
        $user = User::factory()->create([
            'numero_registro' => '224051237',
            'password' => Hash::make('OldPass#2026'),
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson('/api/auth/password', [
                'current_password' => 'OldPass#2026',
                'password' => 'NewPass#2026',
                'password_confirmation' => 'NewPass#2026',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(Hash::check('NewPass#2026', $user->fresh()->password));
    }

    public function test_password_change_rejects_weak_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPass#2026'),
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson('/api/auth/password', [
                'current_password' => 'OldPass#2026',
                'password' => 'debil123',
                'password_confirmation' => 'debil123',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_user_can_request_password_reset_token_by_email_and_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'postulante@cup.test',
            'numero_registro' => '224051238',
            'password' => Hash::make('OldPass#2026'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/forgot-password', [
            'email' => 'postulante@cup.test',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $record = DB::table('password_reset_tokens')->where('email', 'postulante@cup.test')->first();
        $this->assertNotNull($record);

        $this->postJson('/reset-password', [
            'email' => 'postulante@cup.test',
            'token' => $response->json('data.reset_token'),
            'password' => 'ResetPass#2026',
            'password_confirmation' => 'ResetPass#2026',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(Hash::check('ResetPass#2026', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'postulante@cup.test']);
    }

    public function test_autoridad_can_read_validation_list_but_cannot_perform_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'autoridad@cup.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_AUTORIDAD,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Can read validations list
        $response = $this->getJson('/api/inscripciones/pendientes-validacion');
        $response->assertOk();

        // Cannot mutate validation (should return 403 Forbidden)
        $responseMutate = $this->postJson('/api/inscripciones/1/documentos/validar', [
            'estado' => 'aprobado',
        ]);
        $responseMutate->assertStatus(403);
    }
}
