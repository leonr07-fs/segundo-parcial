<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ReportesExportaciones\DashboardController;
use App\Http\Controllers\PortalPostulante\PostulacionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');
Route::view('/login', 'app')->name('login');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/forgot-password', [PasswordController::class, 'forgot'])->middleware('guest')->name('password.forgot');
Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('guest')->name('password.reset');
Route::get('/api/auth/user', [AuthenticatedSessionController::class, 'user'])->middleware('auth')->name('auth.user');
Route::put('/api/auth/password', [PasswordController::class, 'change'])->middleware('auth')->name('auth.password.change');
Route::get('/api/csrf-token', fn () => response()->json([
    'ok' => true,
    'data' => [
        'csrf_token' => csrf_token(),
    ],
]))->name('api.csrf-token');

/* CU02 - Registrar postulación CUP */
Route::view('/postulaciones/crear', 'app')->name('postulaciones.create');
Route::get('/api/postulaciones/create', [PostulacionController::class, 'create'])->name('api.postulaciones.create');
Route::post('/api/postulaciones', [PostulacionController::class, 'store'])->name('api.postulaciones.store');
Route::get('/api/solicitudes-docentes/create', [\App\Http\Controllers\GruposDocentes\SolicitudDocenteController::class, 'create'])->name('api.solicitudes-docentes.create');
Route::post('/api/solicitudes-docentes', [\App\Http\Controllers\GruposDocentes\SolicitudDocenteController::class, 'store'])->name('api.solicitudes-docentes.store');

/* CU06 - Habilitar repostulación en nueva gestión */
Route::post('/api/postulantes/repostular', [\App\Http\Controllers\PortalPostulante\RepostulacionController::class, 'store'])->middleware('auth')->name('api.postulantes.repostular');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::view('/admin/bitacora', 'app')->name('admin.bitacora');
    Route::get('/api/bitacora', [\App\Http\Controllers\SeguridadUsuarios\AuditLogController::class, 'index'])->name('api.bitacora.index');
    Route::view('/admin/solicitudes-docentes', 'app')->name('admin.solicitudes-docentes');
    Route::get('/api/admin/solicitudes-docentes', [\App\Http\Controllers\GruposDocentes\SolicitudDocenteController::class, 'index'])->name('api.admin.solicitudes-docentes.index');
    Route::put('/api/admin/documentos-docentes/{documento}/revisar', [\App\Http\Controllers\GruposDocentes\SolicitudDocenteController::class, 'revisarDocumento'])->name('api.admin.documentos-docentes.revisar');
    Route::post('/api/admin/solicitudes-docentes/{solicitud}/aprobar', [\App\Http\Controllers\GruposDocentes\SolicitudDocenteController::class, 'aprobar'])->name('api.admin.solicitudes-docentes.aprobar');
    Route::post('/api/admin/solicitudes-docentes/{solicitud}/observar', [\App\Http\Controllers\GruposDocentes\SolicitudDocenteController::class, 'observar'])->name('api.admin.solicitudes-docentes.observar');
    Route::post('/api/admin/solicitudes-docentes/{solicitud}/rechazar', [\App\Http\Controllers\GruposDocentes\SolicitudDocenteController::class, 'rechazar'])->name('api.admin.solicitudes-docentes.rechazar');
});

Route::middleware(['auth', 'role:admin,autoridad,coordinador'])->group(function () {
    Route::view('/admin/dashboard', 'app')->name('admin.dashboard');

    /* CU05 - Buscar, consultar y actualizar postulante */
    Route::view('/admin/postulantes', 'app')->name('admin.postulantes');
    Route::view('/admin/postulantes/{id}', 'app')->name('admin.postulantes.detalle');
    Route::get('/api/admin/postulantes', [\App\Http\Controllers\GestionAcademica\AdminPostulanteController::class, 'index'])->name('api.admin.postulantes.index');
    Route::get('/api/admin/postulantes/{id}', [\App\Http\Controllers\GestionAcademica\AdminPostulanteController::class, 'show'])->name('api.admin.postulantes.show');
    Route::put('/api/admin/postulantes/{id}', [\App\Http\Controllers\GestionAcademica\AdminPostulanteController::class, 'update'])->middleware('role:admin')->name('api.admin.postulantes.update');
    Route::post('/api/admin/postulantes/{postulanteId}/inscripciones/{inscripcionId}/anular', [\App\Http\Controllers\GestionAcademica\AdminPostulanteController::class, 'anularInscripcion'])->middleware('role:admin')->name('api.admin.postulantes.inscripciones.anular');

    /* Gestiones */
    Route::get('/api/gestiones', [\App\Http\Controllers\GestionAcademica\GestionController::class, 'index'])->name('api.gestiones.index');
    Route::post('/api/gestiones', [\App\Http\Controllers\GestionAcademica\GestionController::class, 'store'])->name('api.gestiones.store');
    Route::put('/api/gestiones/{id}/habilitar', [\App\Http\Controllers\GestionAcademica\GestionController::class, 'habilitar'])->name('api.gestiones.habilitar');
    Route::put('/api/gestiones/{id}/cerrar', [\App\Http\Controllers\GestionAcademica\GestionController::class, 'cerrar'])->middleware('role:admin')->name('api.gestiones.cerrar');
    Route::put('/api/gestiones/{id}/cerrar-final', [\App\Http\Controllers\GestionAcademica\GestionController::class, 'cerrarFinal'])->middleware('role:admin')->name('api.gestiones.cerrar_final');

    /* CU08 - Parametrizar y gestionar materia, grupo y aula */
    Route::view('/admin/parametros', 'app')->name('admin.parametros');
    Route::get('/api/materias', [\App\Http\Controllers\GestionAcademica\MateriaController::class, 'index'])->name('api.materias.index');
    Route::post('/api/materias', [\App\Http\Controllers\GestionAcademica\MateriaController::class, 'store'])->name('api.materias.store');
    Route::put('/api/materias/{id}/estado', [\App\Http\Controllers\GestionAcademica\MateriaController::class, 'actualizarEstado'])->middleware('role:admin')->name('api.materias.estado');
    Route::get('/api/aulas', [\App\Http\Controllers\GestionAcademica\AulaController::class, 'index'])->name('api.aulas.index');
    Route::post('/api/aulas', [\App\Http\Controllers\GestionAcademica\AulaController::class, 'store'])->name('api.aulas.store');
    Route::put('/api/aulas/{id}/capacidad', [\App\Http\Controllers\GestionAcademica\AulaController::class, 'actualizarCapacidad'])->middleware('role:admin')->name('api.aulas.capacidad');
    Route::get('/api/grupos', [\App\Http\Controllers\GruposDocentes\GrupoController::class, 'index'])->name('api.grupos.index');
    Route::post('/api/grupos', [\App\Http\Controllers\GruposDocentes\GrupoController::class, 'store'])->name('api.grupos.store');

    /* CU13 - Asignación de Materias a Grupos y Docentes */
    Route::get('/api/docentes', [\App\Http\Controllers\GruposDocentes\DocenteController::class, 'index'])->name('api.docentes.index');
    Route::post('/api/docentes', [\App\Http\Controllers\GruposDocentes\DocenteController::class, 'store'])->name('api.docentes.store');
    Route::get('/api/grupos/{grupo}/materias', [\App\Http\Controllers\GruposDocentes\GrupoMateriaController::class, 'index'])->name('api.grupos.materias.index');
    Route::post('/api/grupos/{grupo}/materias', [\App\Http\Controllers\GruposDocentes\GrupoMateriaController::class, 'store'])->name('api.grupos.materias.store');
    Route::post('/api/asignacion-automatica/generar', [\App\Http\Controllers\GruposDocentes\AsignacionAutomaticaController::class, 'generar'])->middleware('role:admin')->name('api.asignacion-automatica.generar');
    Route::post('/api/asignacion-automatica/confirmar', [\App\Http\Controllers\GruposDocentes\AsignacionAutomaticaController::class, 'confirmar'])->middleware('role:admin')->name('api.asignacion-automatica.confirmar');

    /* CU14 y CU15 - Consultar y Exportar Evaluaciones */
    Route::view('/admin/notas', 'app')->name('admin.notas');
    Route::get('/api/evaluaciones/grupo-materia/{id}', [\App\Http\Controllers\GestionAcademica\EvaluacionController::class, 'porGrupoMateria'])->name('api.evaluaciones.grupo_materia');
    Route::get('/api/reportes/evaluaciones/{id}/exportar', [\App\Http\Controllers\GestionAcademica\EvaluacionController::class, 'exportarActa'])->name('api.evaluaciones.exportar');

    /* Reportes y Exportaciones */
    Route::view('/admin/reportes', 'app')->name('admin.reportes');
    Route::get('/api/reportes/catalogo', [\App\Http\Controllers\ReportesExportaciones\ReporteController::class, 'catalogo'])->name('api.reportes.catalogo');
    Route::get('/api/reportes/estatico/{tipo}', [\App\Http\Controllers\ReportesExportaciones\ReporteController::class, 'estatico'])->name('api.reportes.estatico');
    Route::post('/api/reportes/dinamico', [\App\Http\Controllers\ReportesExportaciones\ReporteController::class, 'dinamico'])->name('api.reportes.dinamico');

    /* CU03 - Validar Requisitos Documentales */
    Route::view('/admin/validacion-documental', 'app')->name('admin.validacion-documental');
    Route::view('/admin/validacion-documental/{id}', 'app')->name('admin.validacion-documental.detalle');
    Route::get('/api/inscripciones/pendientes-validacion', [\App\Http\Controllers\PortalPostulante\ValidacionDocumentalController::class, 'index'])->name('api.validacion-documental.index');
    Route::get('/api/inscripciones/{id}/documentos', [\App\Http\Controllers\PortalPostulante\ValidacionDocumentalController::class, 'show'])->name('api.validacion-documental.show');
    Route::post('/api/inscripciones/{id}/documentos/validar', [\App\Http\Controllers\PortalPostulante\ValidacionDocumentalController::class, 'store'])->middleware('role:admin')->name('api.validacion-documental.store');

    /* CU04 - Registrar Pago */
    Route::view('/admin/pagos', 'app')->name('admin.pagos');
    Route::view('/admin/pagos/{id}', 'app')->name('admin.pagos.detalle');
    Route::get('/api/inscripciones/pendientes-pago', [\App\Http\Controllers\PortalPostulante\PagoController::class, 'index'])->name('api.pagos.index');
    Route::post('/api/inscripciones/{id}/pagos', [\App\Http\Controllers\PortalPostulante\PagoController::class, 'store'])->middleware('role:admin')->name('api.pagos.store');

    /* CU09 - Importar Resultados Académicos */
    Route::view('/admin/evaluaciones/importar', 'app')->name('admin.evaluaciones.importar');
    Route::post('/api/evaluaciones/importar', [\App\Http\Controllers\GestionAcademica\EvaluacionController::class, 'importar'])->middleware('role:admin')->name('api.evaluaciones.importar');

    /* CU10 - Validar Reglas Académicas (Supervisión) */
    Route::view('/admin/validaciones-academicas', 'app')->name('admin.validaciones-academicas');
    Route::get('/api/validaciones-academicas', [\App\Http\Controllers\GestionAcademica\ValidacionAcademicaController::class, 'index'])->name('api.validaciones-academicas.index');

    /* CU12 - Asignar Carrera por Cupos */
    Route::view('/admin/asignaciones-carrera', 'app')->name('admin.asignaciones-carrera');
    Route::get('/api/asignaciones-carrera', [\App\Http\Controllers\GestionAcademica\AsignacionCarreraController::class, 'index'])->name('api.asignaciones-carrera.index');
    Route::put('/api/asignaciones-carrera/cupos', [\App\Http\Controllers\GestionAcademica\AsignacionCarreraController::class, 'guardarCupos'])->middleware('role:admin')->name('api.asignaciones-carrera.cupos');
    Route::post('/api/asignaciones-carrera/ejecutar', [\App\Http\Controllers\GestionAcademica\AsignacionCarreraController::class, 'ejecutar'])->middleware('role:admin')->name('api.asignaciones-carrera.ejecutar');

    Route::get('/api/admin/dashboard', [DashboardController::class, 'admin'])->name('api.admin.dashboard');
});

Route::view('/docente/dashboard', 'app')->middleware(['auth', 'role:docente'])->name('docente.dashboard');
Route::view('/postulante/dashboard', 'app')->middleware(['auth', 'role:postulante'])->name('postulante.dashboard');
Route::get('/api/docente/carga', [DashboardController::class, 'docente'])->middleware(['auth', 'role:docente'])->name('api.docente.carga');
Route::get('/api/docente/asistencias/{grupoMateriaId}', [\App\Http\Controllers\GruposDocentes\AsistenciaDocenteController::class, 'show'])->middleware(['auth', 'role:docente'])->name('api.docente.asistencias.show');
Route::post('/api/docente/asistencias/{grupoMateriaId}', [\App\Http\Controllers\GruposDocentes\AsistenciaDocenteController::class, 'store'])->middleware(['auth', 'role:docente'])->name('api.docente.asistencias.store');
Route::get('/api/admin/asistencias', [\App\Http\Controllers\GruposDocentes\AsistenciaDocenteController::class, 'index'])->middleware(['auth', 'role:admin,autoridad,coordinador'])->name('api.admin.asistencias.index');
Route::get('/api/postulante/academico', [DashboardController::class, 'postulante'])->middleware(['auth', 'role:postulante'])->name('api.postulante.academico');
