<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Migracion principal de la base de datos CUP FICCT
|--------------------------------------------------------------------------
|
| Esta migracion crea la estructura base del sistema CUP FICCT.
|
| Idea general del modelo:
| - `postulantes` guarda a la persona.
| - `gestiones` guarda cada proceso CUP.
| - `inscripciones` es la tabla central: une postulante + gestion.
| - Desde `inscripciones` se conectan documentos, biometria, pago, grupo,
|   evaluaciones, resultado final y asignacion de carrera.
|
| Orden de creacion:
| Las tablas catalogo y tablas padre se crean primero. Luego se crean las
| tablas hijas que dependen de ellas mediante llaves foraneas.
|
| Motor esperado:
| Esta estructura esta pensada para Laravel con PostgreSQL, aunque la mayor
| parte usa Schema Builder de Laravel.
|
*/
return new class extends Migration
{
    /**
     * Crea todas las tablas principales del sistema CUP FICCT.
     *
     * Laravel ejecuta este metodo cuando se corre:
     *
     * php artisan migrate
     *
     * Cada bloque Schema::create(...) crea una tabla. Las instrucciones
     * foreignId(...)->constrained(...) conectan una tabla con otra.
     */
    public function up(): void
    {
        /*
         * TABLA: gestiones
         *
         * Que hace:
         * Guarda cada version o periodo del proceso CUP. Ejemplo: CUP 1-2026.
         *
         * Para que sirve:
         * Permite separar inscripciones, grupos, cupos, resultados y reportes
         * por gestion academica.
         *
         * Relaciones:
         * - Una gestion tiene muchas inscripciones.
         * - Una gestion tiene muchos grupos.
         * - Una gestion tiene cupos por carrera.
         */
        Schema::create('gestiones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->unsignedSmallInteger('anio');
            $table->string('periodo', 30)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 30)->default('planificada')->index();
            $table->timestamps();
        });

        /*
         * TABLA: carreras
         *
         * Que hace:
         * Guarda el catalogo de carreras de la FICCT.
         *
         * Para que sirve:
         * Se usa cuando el postulante elige opciones de carrera y cuando el
         * sistema asigna carrera segun cupos.
         */
        Schema::create('carreras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 120);
            $table->boolean('activa')->default(true)->index();
            $table->timestamps();
        });

        /*
         * TABLA: postulantes
         *
         * Que hace:
         * Guarda los datos personales del postulante.
         *
         * Para que sirve:
         * Evita repetir los datos personales en cada gestion. Una misma persona
         * puede existir una vez como postulante y tener distintas inscripciones
         * en diferentes gestiones.
         *
         * Reglas:
         * - `ci` es unico.
         * - `correo` es unico.
         */
        Schema::create('postulantes', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 30)->unique();
            $table->string('complemento', 10)->nullable();
            $table->string('nombres', 120);
            $table->string('apellido_paterno', 80)->nullable();
            $table->string('apellido_materno', 80)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero', 20)->nullable();
            $table->string('correo', 150)->unique();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: inscripciones
         *
         * Que hace:
         * Es la tabla central del modelo. Representa la postulacion de una
         * persona a una gestion CUP.
         *
         * Como conecta:
         * - postulante_id conecta con `postulantes`.
         * - gestion_id conecta con `gestiones`.
         *
         * Regla importante:
         * `unique(postulante_id, gestion_id)` impide que una persona se
         * inscriba dos veces en la misma gestion.
         */
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')->constrained('postulantes')->restrictOnDelete();
            $table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
            $table->string('codigo', 40)->unique();
            $table->timestamp('fecha_inscripcion')->nullable();
            $table->string('estado', 40)->default('prepostulado')->index();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['postulante_id', 'gestion_id']);
        });

        /*
         * TABLA: opciones_carrera
         *
         * Que hace:
         * Guarda las carreras elegidas por una inscripcion.
         *
         * Como conecta:
         * - inscripcion_id conecta con `inscripciones`.
         * - carrera_id conecta con `carreras`.
         *
         * Reglas:
         * - Una inscripcion no puede repetir prioridad.
         * - Una inscripcion no puede elegir dos veces la misma carrera.
         *
         * Nota:
         * La migracion patch agrega el CHECK para que prioridad sea solo 1 o 2.
         */
        Schema::create('opciones_carrera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->unsignedTinyInteger('prioridad');
            $table->timestamps();

            $table->unique(['inscripcion_id', 'prioridad']);
            $table->unique(['inscripcion_id', 'carrera_id']);
        });

        /*
         * TABLA: documentos
         *
         * Que hace:
         * Guarda documentos entregados por el postulante para una inscripcion.
         *
         * Ejemplos de tipo:
         * CI, titulo de bachiller, certificado, fotografia u otros requisitos.
         *
         * Como conecta:
         * - inscripcion_id conecta con `inscripciones`.
         * - revisado_por conecta con `users`.
         *
         * Regla:
         * Una inscripcion solo puede tener un documento de cada tipo.
         */
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('numero', 80)->nullable();
            $table->string('archivo_path', 255)->nullable();
            $table->string('estado', 40)->default('pendiente')->index();
            $table->text('observacion')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();

            $table->unique(['inscripcion_id', 'tipo']);
        });

        /*
         * TABLA: validaciones_documentales
         *
         * Que hace:
         * Guarda el resultado global de la revision documental.
         *
         * Para que sirve:
         * Aunque `documentos` guarda cada documento, esta tabla resume si toda
         * la documentacion de la inscripcion fue aprobada, observada o rechazada.
         *
         * Regla:
         * Cada inscripcion tiene como maximo una validacion documental.
         */
        Schema::create('validaciones_documentales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
            $table->string('estado', 40)->default('pendiente')->index();
            $table->text('observacion')->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: biometrias
         *
         * Que hace:
         * Guarda la foto, huella y estado de captura biometrica de una
         * inscripcion.
         *
         * Regla:
         * Cada inscripcion tiene como maximo un registro biometrico.
         */
        Schema::create('biometrias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
            $table->string('foto_path', 255)->nullable();
            $table->string('huella_hash', 255)->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->string('estado', 40)->default('pendiente')->index();
            $table->timestamp('capturado_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: pagos
         *
         * Que hace:
         * Registra pagos CUP asociados a una inscripcion.
         *
         * Para que sirve:
         * Permite controlar pago pendiente, aprobado, rechazado o anulado.
         *
         * Como conecta:
         * Cada pago pertenece a una inscripcion.
         */
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->restrictOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 10)->default('BOB');
            $table->string('metodo', 40)->nullable();
            $table->string('referencia', 100)->nullable()->unique();
            $table->string('estado', 40)->default('pendiente')->index();
            $table->timestamp('pagado_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: recibos
         *
         * Que hace:
         * Guarda el comprobante emitido para un pago.
         *
         * Regla:
         * Un pago puede tener como maximo un recibo y cada recibo tiene numero
         * unico.
         */
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->unique()->constrained('pagos')->cascadeOnDelete();
            $table->string('numero', 50)->unique();
            $table->string('archivo_path', 255)->nullable();
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emitido_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: aulas
         *
         * Que hace:
         * Guarda aulas fisicas disponibles para clases o evaluaciones.
         *
         * Uso:
         * Se relaciona con grupos y horarios.
         */
        Schema::create('aulas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100)->nullable();
            $table->unsignedSmallInteger('capacidad')->nullable();
            $table->string('ubicacion', 150)->nullable();
            $table->boolean('activa')->default(true)->index();
            $table->timestamps();
        });

        /*
         * TABLA: docentes
         *
         * Que hace:
         * Guarda docentes que dictan materias del CUP.
         *
         * Uso:
         * El docente se conecta con una materia dentro de un grupo mediante
         * `grupo_materias`.
         */
        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 30)->nullable()->unique();
            $table->string('nombres', 120);
            $table->string('apellidos', 120)->nullable();
            $table->string('correo', 150)->nullable()->unique();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        /*
         * TABLA: materias
         *
         * Que hace:
         * Guarda el catalogo de materias del CUP.
         *
         * Uso:
         * Una materia se asigna a grupos por medio de `grupo_materias` y luego
         * recibe evaluaciones.
         */
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->boolean('activa')->default(true)->index();
            $table->timestamps();
        });

        /*
         * TABLA: grupos
         *
         * Que hace:
         * Guarda los grupos/paralelos de una gestion CUP.
         *
         * Como conecta:
         * - gestion_id conecta con `gestiones`.
         * - aula_id conecta con `aulas` como aula principal opcional.
         *
         * Regla:
         * En una misma gestion no se repite el codigo del grupo.
         *
         * Nota:
         * La migracion patch agrega la regla de cupo maximo entre 1 y 70.
         */
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 100)->nullable();
            $table->unsignedSmallInteger('cupo_maximo')->default(70);
            $table->foreignId('aula_id')->nullable()->constrained('aulas')->nullOnDelete();
            $table->string('estado', 40)->default('configuracion')->index();
            $table->timestamps();

            $table->unique(['gestion_id', 'codigo']);
        });

        /*
         * TABLA: grupo_materias
         *
         * Que hace:
         * Une grupo, materia y docente.
         *
         * Para que sirve:
         * Permite saber que docente dicta que materia en que grupo.
         *
         * Como conecta:
         * - grupo_id conecta con `grupos`.
         * - materia_id conecta con `materias`.
         * - docente_id conecta con `docentes`.
         */
        Schema::create('grupo_materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('materia_id')->constrained('materias')->restrictOnDelete();
            $table->foreignId('docente_id')->nullable()->constrained('docentes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['grupo_id', 'materia_id']);
        });

        /*
         * TABLA: horarios
         *
         * Que hace:
         * Guarda dia y hora de clases/evaluaciones.
         *
         * Estado en esta migracion:
         * Inicialmente se crea conectada a `grupos`.
         *
         * Estado final del modelo:
         * La migracion patch cambia esta relacion para que dependa de
         * `grupo_materias`, porque el horario debe ser por materia/docente.
         */
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('aula_id')->nullable()->constrained('aulas')->nullOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->unique(['grupo_id', 'dia_semana', 'hora_inicio']);
        });

        /*
         * TABLA: inscripcion_grupo
         *
         * Que hace:
         * Asigna una inscripcion a un grupo.
         *
         * Regla:
         * inscripcion_id es unico, por lo tanto una inscripcion solo puede
         * estar en un grupo dentro de este modelo.
         */
        Schema::create('inscripcion_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
            $table->string('estado', 40)->default('asignado')->index();
            $table->timestamp('asignado_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: evaluaciones
         *
         * Que hace:
         * Guarda notas de una inscripcion en una materia especifica del grupo.
         *
         * Como conecta:
         * - inscripcion_id conecta con `inscripciones`.
         * - grupo_materia_id conecta con `grupo_materias`.
         *
         * Regla:
         * Una inscripcion solo tiene una evaluacion por materia de grupo.
         *
         * Nota:
         * La migracion patch agrega los CHECK para notas entre 0 y 100.
         */
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->foreignId('grupo_materia_id')->constrained('grupo_materias')->restrictOnDelete();
            $table->decimal('examen_1', 5, 2)->nullable();
            $table->decimal('examen_2', 5, 2)->nullable();
            $table->decimal('examen_3', 5, 2)->nullable();
            $table->decimal('promedio', 5, 2)->nullable();
            $table->string('estado', 40)->default('pendiente')->index();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registrado_en')->nullable();
            $table->timestamps();

            $table->unique(['inscripcion_id', 'grupo_materia_id']);
        });

        /*
         * TABLA: resultados_cup
         *
         * Que hace:
         * Guarda el promedio final y el estado final de la inscripcion.
         *
         * Para que sirve:
         * Es la salida del proceso de evaluacion. Desde aqui se puede decidir
         * si el postulante queda aprobado, reprobado o pendiente.
         *
         * Regla:
         * Una inscripcion tiene como maximo un resultado final.
         */
        Schema::create('resultados_cup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
            $table->decimal('promedio_final', 5, 2)->nullable();
            $table->string('estado_final', 40)->default('pendiente')->index();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: cupos_carrera
         *
         * Que hace:
         * Guarda cuantos cupos tiene cada carrera en cada gestion.
         *
         * Regla:
         * Una carrera solo tiene un registro de cupos por gestion.
         *
         * Nota:
         * La migracion patch agrega CHECK para evitar cupos negativos o
         * disponibles mayores al total.
         */
        Schema::create('cupos_carrera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->unsignedSmallInteger('cupo_total');
            $table->unsignedSmallInteger('cupo_disponible');
            $table->timestamps();

            $table->unique(['gestion_id', 'carrera_id']);
        });

        /*
         * TABLA: asignaciones_carrera
         *
         * Que hace:
         * Guarda la carrera final asignada a una inscripcion aprobada.
         *
         * Para que sirve:
         * Permite asignar por primera opcion, segunda opcion o dejar al
         * postulante en lista de espera/sin cupo.
         *
         * Regla:
         * Una inscripcion tiene como maximo una asignacion de carrera.
         */
        Schema::create('asignaciones_carrera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
            $table->foreignId('carrera_id')->nullable()->constrained('carreras')->nullOnDelete();
            $table->unsignedTinyInteger('opcion_prioridad')->nullable();
            $table->decimal('promedio_usado', 5, 2)->nullable();
            $table->string('estado', 40)->default('pendiente')->index();
            $table->timestamp('asignado_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: publicaciones_resultados
         *
         * Que hace:
         * Controla autorizacion y publicacion de resultados de una gestion.
         *
         * Como conecta:
         * - gestion_id conecta con `gestiones`.
         * - autorizado_por conecta con `users`.
         */
        Schema::create('publicaciones_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->string('estado', 40)->default('borrador')->index();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('autorizado_en')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
        });

        /*
         * TABLA: reportes
         *
         * Que hace:
         * Guarda reportes generados del proceso CUP.
         *
         * Ejemplos:
         * aprobados, reprobados, listas oficiales, asignaciones por carrera.
         *
         * Como conecta:
         * - publicacion_resultado_id conecta con `publicaciones_resultados`.
         * - gestion_id conecta con `gestiones`.
         * - generado_por conecta con `users`.
         */
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_resultado_id')->nullable()->constrained('publicaciones_resultados')->nullOnDelete();
            $table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
            $table->string('tipo', 60);
            $table->string('archivo_path', 255)->nullable();
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generado_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Elimina las tablas creadas por esta migracion.
     *
     * Laravel ejecuta este metodo cuando se revierte la migracion.
     *
     * El orden es inverso al de creacion para respetar llaves foraneas:
     * primero se eliminan tablas hijas y al final las tablas padre.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes');
        Schema::dropIfExists('publicaciones_resultados');
        Schema::dropIfExists('asignaciones_carrera');
        Schema::dropIfExists('cupos_carrera');
        Schema::dropIfExists('resultados_cup');
        Schema::dropIfExists('evaluaciones');
        Schema::dropIfExists('inscripcion_grupo');
        Schema::dropIfExists('horarios');
        Schema::dropIfExists('grupo_materias');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('materias');
        Schema::dropIfExists('docentes');
        Schema::dropIfExists('aulas');
        Schema::dropIfExists('recibos');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('biometrias');
        Schema::dropIfExists('validaciones_documentales');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('opciones_carrera');
        Schema::dropIfExists('inscripciones');
        Schema::dropIfExists('postulantes');
        Schema::dropIfExists('carreras');
        Schema::dropIfExists('gestiones');
    }
};
