<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Cu01AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_receives_role_context(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@cup.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'email' => 'admin@cup.test',
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
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_DOCENTE,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'email' => 'docente@cup.test',
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
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_POSTULANTE,
            'is_active' => false,
        ]);

        $response = $this->postJson('/login', [
            'email' => 'postulante@cup.test',
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

    public function test_repeated_failed_logins_temporarily_lock_the_account(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@cup.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/login', [
                'email' => 'locked@cup.test',
                'password' => 'bad-password',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until);

        $response = $this->postJson('/login', [
            'email' => 'locked@cup.test',
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
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_AUTORIDAD,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'email' => 'autoridad@cup.test',
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
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_COORDINADOR,
            'is_active' => true,
        ]);

        $response = $this->postJson('/login', [
            'email' => 'coordinador@cup.test',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.role', User::ROLE_COORDINADOR)
            ->assertJsonPath('data.redirect_url', '/admin/dashboard');

        $this->assertAuthenticatedAs($user);
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
