# CU01 - Autenticacion de Usuario

## Proposito

Restringir y asegurar el acceso al sistema CUP FICCT mediante autenticacion por credenciales y autorizacion por rol.

Actores:

- Administrador General
- Docente
- Postulante

Resultado esperado:

- El usuario inicia sesion con correo y contrasena.
- El backend valida credenciales, estado activo y bloqueo temporal.
- El sistema registra auditoria.
- El frontend redirige al panel correspondiente segun rol.

## Archivos principales del backend

### Migracion CU01

Archivo:

`database/migrations/2026_05_31_120000_patch_cu01_authentication_fields.php`

Que hace:

- Agrega campos a `users`:
  - `role`
  - `is_active`
  - `failed_login_attempts`
  - `locked_until`
  - `last_login_at`
- Agrega constraint:
  - `role IN ('admin','docente','postulante')`
- Crea tabla `audit_logs`.

Para que sirve:

- `role`: permite autorizar pantallas y rutas por perfil.
- `is_active`: bloquea usuarios inactivos.
- `failed_login_attempts`: cuenta intentos fallidos.
- `locked_until`: aplica bloqueo temporal.
- `last_login_at`: registra ultimo acceso exitoso.
- `audit_logs`: guarda eventos de seguridad.

### Modelo User

Archivo:

`app/Models/User.php`

Que se agrego:

- Constantes de rol:
  - `ROLE_ADMIN`
  - `ROLE_DOCENTE`
  - `ROLE_POSTULANTE`
- Lista `ROLES`.
- Campos fillable nuevos.
- Casts:
  - `is_active`
  - `locked_until`
  - `last_login_at`
- Metodo `isLocked()`.
- Metodo `dashboardPath()`.

Uso:

- `isLocked()` indica si el usuario tiene bloqueo temporal activo.
- `dashboardPath()` devuelve a que panel debe ir el usuario despues del login:
  - admin: `/admin/dashboard`
  - docente: `/docente/dashboard`
  - postulante: `/postulante/dashboard`

### Modelo AuditLog

Archivo:

`app/Models/AuditLog.php`

Que hace:

- Representa la tabla `audit_logs`.
- Permite guardar eventos de auditoria.
- Relaciona cada evento con un usuario cuando existe.

Eventos usados en CU01:

- `auth.login.success`
- `auth.login.failed`
- `auth.login.inactive`
- `auth.login.locked`
- `auth.logout`

### Request de login

Archivo:

`app/Http/Requests/Auth/LoginRequest.php`

Que hace:

- Valida entrada del login antes de llegar al service.

Reglas:

- `email` requerido y con formato email.
- `password` requerido y string.

Por que existe:

- Mantiene el controller limpio.
- Centraliza validacion de entrada del CU01.

### Servicio de auditoria

Archivo:

`app/Services/AuditLogService.php`

Que hace:

- Crea registros en `audit_logs`.
- Guarda:
  - usuario
  - evento
  - IP
  - user agent
  - metadata adicional

Uso:

- Es usado por `LoginService` para registrar accesos, fallos y logout.

### Servicio de login

Archivo:

`app/Services/Auth/LoginService.php`

Que hace:

- Contiene la regla de negocio principal de autenticacion.

Responsabilidades:

- Buscar usuario por email.
- Verificar hash de contrasena.
- Registrar intentos fallidos.
- Bloquear temporalmente despues de 5 fallos.
- Rechazar usuario inactivo.
- Crear sesion Laravel.
- Regenerar sesion para seguridad.
- Limpiar intentos fallidos en login correcto.
- Registrar auditoria.
- Devolver contexto del usuario y URL de redireccion.

Reglas implementadas:

- Credenciales invalidas: responde error controlado.
- Usuario inactivo: deniega acceso.
- Multiples fallos: bloquea cuenta temporalmente.
- Login correcto: crea sesion y carga rol.

### Controller de autenticacion

Archivo:

`app/Http/Controllers/Auth/AuthenticatedSessionController.php`

Que hace:

- Expone acciones HTTP para login, logout y usuario autenticado.

Metodos:

- `store(LoginRequest $request)`: procesa `POST /login`.
- `destroy(Request $request)`: procesa `POST /logout`.
- `user(Request $request)`: procesa `GET /api/auth/user`.

Regla de arquitectura:

- El controller no contiene logica compleja.
- Delega en `LoginService`.

### Middleware de rol

Archivo:

`app/Http/Middleware/EnsureUserHasRole.php`

Que hace:

- Verifica que el usuario autenticado tenga uno de los roles permitidos.

Uso:

```php
middleware(['auth', 'role:admin'])
```

Si el usuario no tiene permiso:

- Responde `403`.

### Registro de middleware

Archivo:

`bootstrap/app.php`

Que se agrego:

```php
$middleware->alias([
    'role' => EnsureUserHasRole::class,
]);
```

Para que sirve:

- Permite usar `role` en rutas Laravel 12.

### Rutas

Archivo:

`routes/web.php`

Rutas agregadas:

```php
Route::view('/', 'app')->name('home');
Route::view('/login', 'app')->name('login');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/api/auth/user', [AuthenticatedSessionController::class, 'user'])->middleware('auth')->name('auth.user');

Route::view('/admin/dashboard', 'app')->middleware(['auth', 'role:admin'])->name('admin.dashboard');
Route::view('/docente/dashboard', 'app')->middleware(['auth', 'role:docente'])->name('docente.dashboard');
Route::view('/postulante/dashboard', 'app')->middleware(['auth', 'role:postulante'])->name('postulante.dashboard');
```

Funcion:

- `/login`: muestra app Vue.
- `POST /login`: autentica.
- `POST /logout`: cierra sesion.
- `/api/auth/user`: devuelve contexto del usuario autenticado.
- Dashboards: protegidos por sesion y rol.

### Seeder de usuario inicial

Archivo:

`database/seeders/DatabaseSeeder.php`

Usuario creado:

```text
Correo: admin@cup.test
Contrasena: password
Rol: admin
Estado: activo
```

Uso:

- Permite probar CU01 sin esperar CU07.

## Archivos principales del frontend

### Vista Blade principal

Archivo:

`resources/views/app.blade.php`

Que hace:

- Es el layout base.
- Carga CSRF token.
- Carga Vite.
- Expone el contenedor Vue:

```html
<div id="app"></div>
```

Nota:

- Blade se usa solo como entrypoint.
- La interfaz real esta en Vue.

### Entry point Vue

Archivo:

`resources/js/app.js`

Que hace:

- Importa `bootstrap.js`.
- Crea la app Vue.
- Monta `App.vue` en `#app`.

### Configuracion Axios

Archivo:

`resources/js/bootstrap.js`

Que hace:

- Configura Axios global.
- Agrega header `X-Requested-With`.
- Habilita `withCredentials`.
- Lee CSRF token desde:

```html
<meta name="csrf-token">
```

Para que sirve:

- Permite consumir rutas Laravel protegidas por CSRF/session.

### API frontend de autenticacion

Archivo:

`resources/js/api/auth.js`

Funciones:

- `login(credentials)`
- `logout()`
- `fetchAuthenticatedUser()`

Endpoints consumidos:

- `POST /login`
- `POST /logout`
- `GET /api/auth/user`

### Componente principal Vue

Archivo:

`resources/js/App.vue`

Que hace:

- Muestra login si no hay usuario autenticado.
- Consulta sesion actual al cargar.
- Envia credenciales al backend.
- Muestra errores del backend.
- Redirige visualmente segun `dashboard_path`.
- Muestra dashboard basico por rol.
- Permite cerrar sesion.

Estados manejados:

- `loading`
- `submitting`
- `user`
- `message`
- `fieldErrors`

Pantallas incluidas:

- Login.
- Panel basico para Administrador, Docente o Postulante.

### Estilos Tailwind

Archivo:

`resources/css/app.css`

Que se agrego:

```css
@source '../**/*.vue';
```

Para que sirve:

- Permite que Tailwind detecte clases usadas en componentes Vue.

### Configuracion Vite

Archivo:

`vite.config.js`

Que se agrego:

```js
import vue from '@vitejs/plugin-vue';
```

Y el plugin:

```js
vue(),
```

Para que sirve:

- Permite compilar componentes `.vue`.

### Dependencias frontend

Archivo:

`package.json`

Dependencias agregadas:

- `vue`
- `@vitejs/plugin-vue`

## Pruebas del caso de uso

Archivo:

`tests/Feature/Auth/Cu01AuthenticationTest.php`

Pruebas implementadas:

- Usuario activo puede iniciar sesion y recibe contexto de rol.
- Credenciales invalidas se rechazan sin revelar informacion sensible.
- Usuario inactivo no puede iniciar sesion.
- Fallos consecutivos bloquean temporalmente la cuenta.

Comando:

```bash
php artisan test tests/Feature/Auth/Cu01AuthenticationTest.php
```

Resultado verificado:

```text
4 passed, 26 assertions
```

Suite completa verificada:

```bash
php artisan test
```

Resultado:

```text
6 passed, 28 assertions
```

## Build frontend

Comando:

```bash
npm run build
```

Resultado:

```text
vite build completed successfully
```

## Como probar manualmente CU01

1. Verificar PostgreSQL encendido.
2. Levantar Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

3. Levantar Vite:

```bash
npm run dev -- --host 127.0.0.1
```

4. Abrir:

```text
http://127.0.0.1:8000/login
```

5. Ingresar:

```text
Correo: admin@cup.test
Contrasena: password
```

Resultado esperado:

- El sistema muestra el panel de Administrador General.
- La sesion queda activa.
- Se registra evento `auth.login.success` en `audit_logs`.

## Checklist CU01

- Ruta creada: si.
- Request validando reglas: si.
- Service implementado: si.
- Controller delgado: si.
- Middleware por rol: si.
- Auditoria: si.
- Pantalla Vue funcionando: si.
- Consumo Axios conectado: si.
- Pruebas Feature: si.
- Build frontend: si.

