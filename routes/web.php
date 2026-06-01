<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\PostulacionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');
Route::view('/login', 'app')->name('login');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/api/auth/user', [AuthenticatedSessionController::class, 'user'])->middleware('auth')->name('auth.user');

/* CU02 - Registrar postulación CUP */
Route::view('/postulaciones/crear', 'app')->name('postulaciones.create');
Route::get('/api/postulaciones/create', [PostulacionController::class, 'create'])->name('api.postulaciones.create');
Route::post('/api/postulaciones', [PostulacionController::class, 'store'])->name('api.postulaciones.store');

Route::middleware(['auth', 'role:admin,autoridad,coordinador'])->group(function () {
    Route::view('/admin/dashboard', 'app')->name('admin.dashboard');

    /* CU03 - Validar Requisitos Documentales */
    Route::view('/admin/validacion-documental', 'app')->name('admin.validacion-documental');
    Route::view('/admin/validacion-documental/{id}', 'app')->name('admin.validacion-documental.detalle');
    Route::get('/api/inscripciones/pendientes-validacion', [\App\Http\Controllers\ValidacionDocumentalController::class, 'index'])->name('api.validacion-documental.index');
    Route::get('/api/inscripciones/{id}/documentos', [\App\Http\Controllers\ValidacionDocumentalController::class, 'show'])->name('api.validacion-documental.show');
    Route::post('/api/inscripciones/{id}/documentos/validar', [\App\Http\Controllers\ValidacionDocumentalController::class, 'store'])->middleware('role:admin')->name('api.validacion-documental.store');

    /* CU04 - Registrar Pago */
    Route::view('/admin/pagos', 'app')->name('admin.pagos');
    Route::view('/admin/pagos/{id}', 'app')->name('admin.pagos.detalle');
    Route::get('/api/inscripciones/pendientes-pago', [\App\Http\Controllers\PagoController::class, 'index'])->name('api.pagos.index');
    Route::post('/api/inscripciones/{id}/pagos', [\App\Http\Controllers\PagoController::class, 'store'])->middleware('role:admin')->name('api.pagos.store');

    /* CU09 - Importar Resultados Académicos */
    Route::view('/admin/evaluaciones/importar', 'app')->name('admin.evaluaciones.importar');
    Route::post('/api/evaluaciones/importar', [\App\Http\Controllers\EvaluacionController::class, 'importar'])->middleware('role:admin')->name('api.evaluaciones.importar');

    /* CU10 - Validar Reglas Académicas (Supervisión) */
    Route::view('/admin/validaciones-academicas', 'app')->name('admin.validaciones-academicas');
    Route::get('/api/validaciones-academicas', [\App\Http\Controllers\ValidacionAcademicaController::class, 'index'])->name('api.validaciones-academicas.index');

    /* CU12 - Asignar Carrera por Cupos */
    Route::view('/admin/asignaciones-carrera', 'app')->name('admin.asignaciones-carrera');
    Route::get('/api/asignaciones-carrera', [\App\Http\Controllers\AsignacionCarreraController::class, 'index'])->name('api.asignaciones-carrera.index');
    Route::post('/api/asignaciones-carrera/ejecutar', [\App\Http\Controllers\AsignacionCarreraController::class, 'ejecutar'])->middleware('role:admin')->name('api.asignaciones-carrera.ejecutar');
});

Route::view('/docente/dashboard', 'app')->middleware(['auth', 'role:docente'])->name('docente.dashboard');
Route::view('/postulante/dashboard', 'app')->middleware(['auth', 'role:postulante'])->name('postulante.dashboard');
