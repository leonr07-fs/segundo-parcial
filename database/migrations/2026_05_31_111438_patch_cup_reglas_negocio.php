<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Patch de reglas de negocio CUP FICCT
|--------------------------------------------------------------------------
|
| Esta migracion NO crea la base desde cero. Complementa la migracion
| principal `2026_05_31_061500_create_cup_ficct_schema.php`.
|
| Objetivo general:
| - Agregar datos faltantes al postulante.
| - Corregir la relacion de horarios para que dependa de materia/docente.
| - Agregar restricciones CHECK para que la base de datos proteja reglas
|   importantes aunque el backend tenga errores.
|
| Tablas tocadas:
| - postulantes
| - horarios
| - opciones_carrera
| - grupos
| - evaluaciones
| - cupos_carrera
|
| Motor esperado:
| - PostgreSQL. Los DB::statement con ALTER TABLE ... CHECK estan escritos
|   para PostgreSQL.
|
*/
return new class extends Migration
{
    /**
     * Aplica las correcciones.
     *
     * Laravel ejecuta este metodo cuando se corre:
     *
     * php artisan migrate
     *
     * En terminos de negocio, este metodo deja la base lista para trabajar
     * registro de postulantes, horarios por materia/docente, validacion de
     * notas, cupos y opciones de carrera.
     */
    public function up(): void
    {
        /*
         * TABLA: postulantes
         *
         * Que hace la tabla:
         * Guarda los datos personales del estudiante que postula al CUP.
         *
         * Que agrega este patch:
         * - colegio_procedencia: colegio del que viene el postulante.
         * - ciudad: ciudad de procedencia o residencia.
         *
         * Por que nullable:
         * Son datos importantes, pero pueden no existir en registros antiguos
         * o pueden completarse despues durante el flujo de registro.
         */
        Schema::table('postulantes', function (Blueprint $table) {
            $table->string('colegio_procedencia', 150)->nullable();
            $table->string('ciudad', 100)->nullable();
        });

        /*
         * TABLA: horarios
         *
         * Antes:
         * horarios se conectaba directamente con grupos mediante grupo_id.
         *
         * Problema:
         * Un grupo puede tener varias materias y cada materia puede tener un
         * docente distinto. Si el horario solo depende del grupo, luego no se
         * sabe con precision que materia/docente corresponde a ese horario.
         *
         * Ahora:
         * horarios se conecta con grupo_materias mediante grupo_materia_id.
         *
         * Como queda la conexion:
         * horarios -> grupo_materias -> grupos
         * horarios -> grupo_materias -> materias
         * horarios -> grupo_materias -> docentes
         *
         * Esto permite que el frontend muestre correctamente:
         * grupo + materia + docente + aula + dia + hora.
         */
        Schema::table('horarios', function (Blueprint $table) {
            // Primero se elimina el indice unico antiguo basado en grupo_id.
            $table->dropUnique('horarios_grupo_id_dia_semana_hora_inicio_unique');

            // Luego se elimina la llave foranea antigua hacia grupos.
            $table->dropForeign(['grupo_id']);

            // Se elimina la columna antigua porque ya no representa el modelo final.
            $table->dropColumn('grupo_id');

            /*
             * Nueva llave foranea:
             * Cada horario pertenece a una materia dentro de un grupo.
             *
             * cascadeOnDelete:
             * Si se elimina esa asignacion grupo-materia, sus horarios tambien
             * se eliminan porque ya no tendrian sentido.
             */
            $table->foreignId('grupo_materia_id')
                ->after('id')
                ->constrained('grupo_materias')
                ->cascadeOnDelete();

            /*
             * Evita duplicar el mismo horario para la misma materia del grupo.
             * Una misma grupo_materia no puede tener dos registros con el mismo
             * dia_semana y la misma hora_inicio.
             */
            $table->unique(['grupo_materia_id', 'dia_semana', 'hora_inicio'], 'horarios_grupo_materia_dia_hora_unique');
        });

        /*
         * TABLA: opciones_carrera
         *
         * Que hace la tabla:
         * Guarda las carreras elegidas por una inscripcion.
         *
         * Regla:
         * prioridad solo puede ser 1 o 2.
         *
         * Significado:
         * - 1 = primera opcion
         * - 2 = segunda opcion
         */
        DB::statement('ALTER TABLE opciones_carrera ADD CONSTRAINT ck_opciones_prioridad CHECK (prioridad IN (1,2))');

        /*
         * TABLA: grupos
         *
         * Que hace la tabla:
         * Guarda los grupos/paralelos del CUP en una gestion.
         *
         * Regla:
         * cupo_maximo debe estar entre 1 y 70.
         *
         * Significado:
         * No se permiten grupos vacios ni grupos con mas de 70 estudiantes.
         */
        DB::statement('ALTER TABLE grupos ADD CONSTRAINT ck_grupos_cupo_maximo CHECK (cupo_maximo BETWEEN 1 AND 70)');

        /*
         * TABLA: evaluaciones
         *
         * Que hace la tabla:
         * Guarda las notas de una inscripcion en una materia de su grupo.
         *
         * Reglas:
         * - examen_1, examen_2, examen_3 pueden ser NULL mientras no se cargan.
         * - Si se cargan, deben estar entre 0 y 100.
         * - promedio tambien puede ser NULL mientras no se calcula.
         * - Si se calcula, debe estar entre 0 y 100.
         *
         * Esto protege la base contra notas negativas o mayores a 100.
         */
        DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_ex1 CHECK (examen_1 IS NULL OR (examen_1 >= 0 AND examen_1 <= 100))');
        DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_ex2 CHECK (examen_2 IS NULL OR (examen_2 >= 0 AND examen_2 <= 100))');
        DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_ex3 CHECK (examen_3 IS NULL OR (examen_3 >= 0 AND examen_3 <= 100))');
        DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_prom CHECK (promedio IS NULL OR (promedio >= 0 AND promedio <= 100))');

        /*
         * TABLA: horarios
         *
         * Reglas:
         * - hora_fin debe ser mayor que hora_inicio.
         * - dia_semana debe estar entre 1 y 7.
         *
         * Recomendacion de interpretacion:
         * 1 = lunes, 2 = martes, 3 = miercoles, 4 = jueves, 5 = viernes,
         * 6 = sabado, 7 = domingo.
         */
        DB::statement('ALTER TABLE horarios ADD CONSTRAINT ck_horarios_rango CHECK (hora_fin > hora_inicio)');
        DB::statement('ALTER TABLE horarios ADD CONSTRAINT ck_horarios_dia CHECK (dia_semana BETWEEN 1 AND 7)');

        /*
         * TABLA: cupos_carrera
         *
         * Que hace la tabla:
         * Guarda los cupos disponibles por carrera y gestion.
         *
         * Reglas:
         * - cupo_total no puede ser negativo.
         * - cupo_disponible no puede ser negativo.
         * - cupo_disponible no puede superar cupo_total.
         *
         * Esto evita inconsistencias como:
         * - una carrera con -5 cupos
         * - una carrera con 80 disponibles de 50 totales
         */
        DB::statement('ALTER TABLE cupos_carrera ADD CONSTRAINT ck_cupos_tot CHECK (cupo_total >= 0)');
        DB::statement('ALTER TABLE cupos_carrera ADD CONSTRAINT ck_cupos_disp CHECK (cupo_disponible >= 0)');
        DB::statement('ALTER TABLE cupos_carrera ADD CONSTRAINT ck_cupos_rel CHECK (cupo_disponible <= cupo_total)');
    }

    /**
     * Revierte las correcciones.
     *
     * Laravel ejecuta este metodo si se revierte la migracion.
     *
     * Importante:
     * El orden importa. Primero se eliminan restricciones CHECK para que luego
     * se puedan modificar columnas y llaves foraneas sin conflicto.
     */
    public function down(): void
    {
        /*
         * Se eliminan las restricciones CHECK agregadas en up().
         *
         * IF EXISTS evita error si por alguna razon la restriccion no existe.
         */
        DB::statement('ALTER TABLE cupos_carrera DROP CONSTRAINT IF EXISTS ck_cupos_rel');
        DB::statement('ALTER TABLE cupos_carrera DROP CONSTRAINT IF EXISTS ck_cupos_disp');
        DB::statement('ALTER TABLE cupos_carrera DROP CONSTRAINT IF EXISTS ck_cupos_tot');
        DB::statement('ALTER TABLE horarios DROP CONSTRAINT IF EXISTS ck_horarios_dia');
        DB::statement('ALTER TABLE horarios DROP CONSTRAINT IF EXISTS ck_horarios_rango');
        DB::statement('ALTER TABLE evaluaciones DROP CONSTRAINT IF EXISTS ck_eval_prom');
        DB::statement('ALTER TABLE evaluaciones DROP CONSTRAINT IF EXISTS ck_eval_ex3');
        DB::statement('ALTER TABLE evaluaciones DROP CONSTRAINT IF EXISTS ck_eval_ex2');
        DB::statement('ALTER TABLE evaluaciones DROP CONSTRAINT IF EXISTS ck_eval_ex1');
        DB::statement('ALTER TABLE grupos DROP CONSTRAINT IF EXISTS ck_grupos_cupo_maximo');
        DB::statement('ALTER TABLE opciones_carrera DROP CONSTRAINT IF EXISTS ck_opciones_prioridad');

        /*
         * TABLA: horarios
         *
         * Revierte el cambio estructural:
         * - elimina grupo_materia_id
         * - vuelve a crear grupo_id
         *
         * Esto deja la tabla como estaba antes del patch.
         */
        Schema::table('horarios', function (Blueprint $table) {
            $table->dropUnique('horarios_grupo_materia_dia_hora_unique');
            $table->dropForeign(['grupo_materia_id']);
            $table->dropColumn('grupo_materia_id');
            $table->foreignId('grupo_id')
                ->after('id')
                ->constrained('grupos')
                ->cascadeOnDelete();
            $table->unique(['grupo_id', 'dia_semana', 'hora_inicio']);
        });

        /*
         * TABLA: postulantes
         *
         * Revierte los campos agregados por este patch.
         */
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn(['colegio_procedencia', 'ciudad']);
        });
    }
};
