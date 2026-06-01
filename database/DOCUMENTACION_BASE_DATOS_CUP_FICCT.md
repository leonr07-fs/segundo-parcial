# Documentacion de base de datos CUP FICCT

## Estado general

La base de datos esta pensada para un sistema CUP FICCT en Laravel con
PostgreSQL. El modelo final sale de dos migraciones:

- `2026_05_31_061500_create_cup_ficct_schema.php`: crea la estructura base.
- `2026_05_31_111438_patch_cup_reglas_negocio.php`: agrega reglas de negocio,
  campos faltantes y cambia `horarios` para depender de `grupo_materias`.

La tabla central del sistema es `inscripciones`. Casi todos los procesos se
ordenan alrededor de una inscripcion CUP de un postulante en una gestion.

## Flujo funcional cubierto

1. El postulante se registra en `postulantes`.
2. El postulante crea una `inscripcion` para una `gestion`.
3. Registra sus opciones en `opciones_carrera`.
4. Entrega documentos en `documentos`.
5. Campus/FICCT valida documentos en `validaciones_documentales`.
6. Se registra biometria en `biometrias`.
7. Se registra el pago en `pagos` y su recibo en `recibos`.
8. El administrador crea `grupos`, `grupo_materias` y `horarios`.
9. Cada inscripcion se asigna a un grupo mediante `inscripcion_grupo`.
10. Docentes registran notas en `evaluaciones`.
11. El sistema calcula resultado final en `resultados_cup`.
12. Se asigna carrera segun cupos en `asignaciones_carrera`.
13. Resultados y reportes se manejan con `publicaciones_resultados` y `reportes`.

## Como leer el codigo de las migraciones

Las migraciones son archivos PHP que Laravel usa para crear o modificar tablas.
Cada migracion tiene dos metodos importantes:

- `up()`: se ejecuta cuando corremos `php artisan migrate`. Aqui se crean
  tablas, columnas, llaves foraneas, indices y reglas.
- `down()`: se ejecuta cuando se revierte una migracion. Aqui se eliminan los
  cambios hechos por `up()`.

En el codigo se usan estas instrucciones principales:

- `Schema::create('tabla', function (Blueprint $table) { ... })`: crea una
  tabla nueva.
- `$table->id()`: crea la llave primaria `id`.
- `$table->string('campo', longitud)`: crea un campo de texto corto.
- `$table->text('campo')`: crea un campo de texto largo.
- `$table->date('campo')`: crea una fecha.
- `$table->timestamp('campo')`: crea fecha y hora.
- `$table->decimal('campo', 5, 2)`: crea numero decimal. En notas permite
  valores como `85.50`.
- `$table->boolean('campo')`: crea verdadero/falso.
- `$table->foreignId('campo_id')->constrained('tabla')`: crea una llave
  foranea hacia otra tabla.
- `restrictOnDelete()`: impide borrar el registro padre si tiene registros
  relacionados.
- `cascadeOnDelete()`: si se borra el padre, tambien se borran sus hijos.
- `nullOnDelete()`: si se borra el padre, el campo queda en `NULL`.
- `$table->unique(...)`: evita registros duplicados.
- `$table->index()`: mejora busquedas por ese campo.
- `DB::statement('ALTER TABLE ... CHECK ...')`: agrega reglas de validacion
  directamente en PostgreSQL.

## Paso a paso del codigo de la base de datos

### 1. `gestiones`

Codigo principal:

```php
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
```

Que hace:

- Crea la tabla que identifica cada proceso CUP.
- `nombre` es unico para no repetir una gestion.
- `anio` y `periodo` permiten separar procesos academicos.
- `estado` permite controlar si la gestion esta planificada, abierta, cerrada
  o publicada.
- `timestamps()` crea `created_at` y `updated_at`.

### 2. `carreras`

Que hace:

- Guarda el catalogo de carreras disponibles.
- `codigo` es unico para identificar la carrera de forma estable.
- `activa` permite ocultar una carrera sin borrarla.
- Se usa luego en opciones, cupos y asignacion final.

### 3. `postulantes`

Que hace:

- Guarda los datos personales del estudiante/postulante.
- `ci` es unico para evitar duplicar personas.
- `correo` tambien es unico para evitar registros repetidos.
- La migracion patch agrega `colegio_procedencia` y `ciudad`.

Codigo agregado por patch:

```php
Schema::table('postulantes', function (Blueprint $table) {
    $table->string('colegio_procedencia', 150)->nullable();
    $table->string('ciudad', 100)->nullable();
});
```

Por que existe:

- El postulante puede participar en varias gestiones, pero sus datos personales
  viven en una sola tabla.
- Asi evitamos repetir nombres, CI, correo y datos personales en cada
  inscripcion.

### 4. `inscripciones`

Que hace:

- Es la tabla central del sistema.
- Une un `postulante` con una `gestion`.
- Guarda el codigo CUP unico de seguimiento.

Relaciones importantes:

```php
$table->foreignId('postulante_id')->constrained('postulantes')->restrictOnDelete();
$table->foreignId('gestion_id')->constrained('gestiones')->restrictOnDelete();
$table->unique(['postulante_id', 'gestion_id']);
```

Que significan:

- Una inscripcion pertenece a un postulante.
- Una inscripcion pertenece a una gestion.
- No se puede borrar un postulante o una gestion si ya tiene inscripciones.
- Un mismo postulante no puede inscribirse dos veces en la misma gestion.

### 5. `opciones_carrera`

Que hace:

- Guarda las carreras elegidas por el postulante.
- Permite primera opcion y segunda opcion.

Reglas:

```php
$table->unique(['inscripcion_id', 'prioridad']);
$table->unique(['inscripcion_id', 'carrera_id']);
DB::statement('ALTER TABLE opciones_carrera ADD CONSTRAINT ck_opciones_prioridad CHECK (prioridad IN (1,2))');
```

Que significan:

- Una inscripcion no puede tener dos primeras opciones.
- Una inscripcion no puede repetir la misma carrera.
- La prioridad solo puede ser `1` o `2`.

### 6. `documentos`

Que hace:

- Guarda los documentos entregados por cada inscripcion.
- Ejemplos: CI, titulo de bachiller, certificado, fotografia digitalizada.

Campos clave:

- `tipo`: identifica el documento.
- `estado`: permite saber si esta pendiente, observado, aprobado o rechazado.
- `revisado_por`: usuario que reviso el documento.
- `revisado_en`: fecha/hora de revision.

Regla:

```php
$table->unique(['inscripcion_id', 'tipo']);
```

Esto evita que la misma inscripcion tenga dos documentos del mismo tipo.

### 7. `validaciones_documentales`

Que hace:

- Guarda el resultado final de la revision documental.
- Resume si la documentacion completa fue aprobada, observada o rechazada.

Regla clave:

```php
$table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
```

Que significa:

- Cada inscripcion puede tener una sola validacion documental.
- Si se elimina la inscripcion, se elimina su validacion documental.

### 8. `biometrias`

Que hace:

- Guarda datos de fotografia y huella del postulante.
- Controla intentos de captura y estado biometrico.

Campos clave:

- `foto_path`: ruta de la fotografia.
- `huella_hash`: valor guardado de la huella.
- `intentos`: cantidad de intentos de captura.
- `estado`: pendiente, capturada, fallida o validada.

### 9. `pagos`

Que hace:

- Registra pagos CUP realizados por una inscripcion.
- Permite manejar pagos pendientes, aprobados, rechazados o anulados.

Campos clave:

- `monto`: importe pagado.
- `moneda`: por defecto `BOB`.
- `metodo`: caja, pasarela, transferencia, etc.
- `referencia`: codigo externo unico.
- `pagado_en`: fecha/hora del pago.

### 10. `recibos`

Que hace:

- Guarda el recibo generado para un pago.

Reglas:

```php
$table->foreignId('pago_id')->unique()->constrained('pagos')->cascadeOnDelete();
$table->string('numero', 50)->unique();
```

Que significan:

- Cada pago tiene como maximo un recibo.
- Cada recibo tiene un numero unico.

### 11. `aulas`

Que hace:

- Guarda aulas fisicas donde se dictan clases o evaluaciones.
- Se usa en `grupos` y `horarios`.

Campos clave:

- `codigo`: identificador unico del aula.
- `capacidad`: cantidad de estudiantes.
- `ubicacion`: referencia fisica.
- `activa`: permite deshabilitar aulas.

### 12. `docentes`

Que hace:

- Guarda docentes que dictan materias del CUP.

Uso:

- No se asigna directamente al grupo completo.
- Se asigna a `grupo_materias`, porque un grupo puede tener varias materias y
  cada materia puede tener un docente distinto.

### 13. `materias`

Que hace:

- Guarda el catalogo de materias evaluables del CUP.
- Se conecta con grupos mediante `grupo_materias`.

### 14. `grupos`

Que hace:

- Representa los paralelos o grupos de estudiantes en una gestion.

Reglas:

```php
$table->unique(['gestion_id', 'codigo']);
DB::statement('ALTER TABLE grupos ADD CONSTRAINT ck_grupos_cupo_maximo CHECK (cupo_maximo BETWEEN 1 AND 70)');
```

Que significan:

- En una misma gestion no puede repetirse el codigo de grupo.
- El cupo maximo de un grupo debe estar entre 1 y 70.

### 15. `grupo_materias`

Que hace:

- Es una tabla intermedia entre grupo, materia y docente.
- Responde la pregunta: que docente dicta que materia en que grupo.

Regla:

```php
$table->unique(['grupo_id', 'materia_id']);
```

Esto evita duplicar la misma materia dentro del mismo grupo.

### 16. `horarios`

Que hace:

- Guarda el horario de una materia especifica dentro de un grupo.
- En el modelo final depende de `grupo_materia_id`.

Cambio importante del patch:

```php
$table->dropForeign(['grupo_id']);
$table->dropColumn('grupo_id');
$table->foreignId('grupo_materia_id')
    ->after('id')
    ->constrained('grupo_materias')
    ->cascadeOnDelete();
```

Que significa:

- Antes el horario dependia solo del grupo.
- Ahora depende de `grupo_materias`, entonces el frontend podra mostrar:
  grupo, materia, docente, aula, dia y hora sin confusion.

Reglas:

```php
$table->unique(['grupo_materia_id', 'dia_semana', 'hora_inicio'], 'horarios_grupo_materia_dia_hora_unique');
DB::statement('ALTER TABLE horarios ADD CONSTRAINT ck_horarios_rango CHECK (hora_fin > hora_inicio)');
DB::statement('ALTER TABLE horarios ADD CONSTRAINT ck_horarios_dia CHECK (dia_semana BETWEEN 1 AND 7)');
```

Que significan:

- No se duplica el mismo horario de una materia.
- La hora final debe ser mayor a la hora inicial.
- El dia de semana debe estar entre 1 y 7.

### 17. `inscripcion_grupo`

Que hace:

- Asigna una inscripcion a un grupo.

Regla clave:

```php
$table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
```

Que significa:

- Una inscripcion solo puede estar asignada a un grupo en este modelo.

### 18. `evaluaciones`

Que hace:

- Guarda las notas de una inscripcion en una materia de su grupo.
- Maneja tres examenes y un promedio.

Relacion importante:

```php
$table->foreignId('grupo_materia_id')->constrained('grupo_materias')->restrictOnDelete();
```

Esto permite saber exactamente de que materia/docente/grupo es la nota.

Reglas:

```php
DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_ex1 CHECK (examen_1 IS NULL OR (examen_1 >= 0 AND examen_1 <= 100))');
DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_ex2 CHECK (examen_2 IS NULL OR (examen_2 >= 0 AND examen_2 <= 100))');
DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_ex3 CHECK (examen_3 IS NULL OR (examen_3 >= 0 AND examen_3 <= 100))');
DB::statement('ALTER TABLE evaluaciones ADD CONSTRAINT ck_eval_prom CHECK (promedio IS NULL OR (promedio >= 0 AND promedio <= 100))');
```

Que significan:

- Las notas pueden estar vacias mientras no se registren.
- Si se registran, deben estar entre 0 y 100.

### 19. `resultados_cup`

Que hace:

- Guarda el resultado final consolidado de una inscripcion.
- Se llena despues de calcular las evaluaciones.

Campos clave:

- `promedio_final`: nota final del CUP.
- `estado_final`: aprobado, reprobado o pendiente.
- `cerrado_en`: fecha/hora de cierre del resultado.

### 20. `cupos_carrera`

Que hace:

- Define cuantos cupos tiene una carrera en una gestion.

Reglas:

```php
$table->unique(['gestion_id', 'carrera_id']);
DB::statement('ALTER TABLE cupos_carrera ADD CONSTRAINT ck_cupos_tot CHECK (cupo_total >= 0)');
DB::statement('ALTER TABLE cupos_carrera ADD CONSTRAINT ck_cupos_disp CHECK (cupo_disponible >= 0)');
DB::statement('ALTER TABLE cupos_carrera ADD CONSTRAINT ck_cupos_rel CHECK (cupo_disponible <= cupo_total)');
```

Que significan:

- Una carrera solo tiene un registro de cupos por gestion.
- Los cupos no pueden ser negativos.
- El cupo disponible no puede ser mayor que el cupo total.

### 21. `asignaciones_carrera`

Que hace:

- Guarda la carrera final asignada al postulante aprobado.
- Tambien permite representar lista de espera o sin cupo.

Campos clave:

- `carrera_id`: nullable, porque puede no haber carrera asignada.
- `opcion_prioridad`: indica si se asigno por primera o segunda opcion.
- `promedio_usado`: promedio con el que compitio.
- `estado`: pendiente, asignado, lista_espera o sin_cupo.

### 22. `publicaciones_resultados`

Que hace:

- Controla si los resultados de una gestion ya fueron autorizados y publicados.

Campos clave:

- `estado`: borrador, autorizado, publicado o cerrado.
- `autorizado_por`: usuario que autoriza.
- `autorizado_en`: fecha/hora de autorizacion.
- `publicado_en`: fecha/hora de publicacion.

### 23. `reportes`

Que hace:

- Guarda reportes generados por gestion.
- Ejemplos: aprobados, reprobados, listas oficiales, asignaciones por carrera.

Campos clave:

- `publicacion_resultado_id`: relacion opcional con publicacion.
- `gestion_id`: gestion del reporte.
- `tipo`: tipo de reporte.
- `archivo_path`: ruta del archivo generado.
- `generado_por`: usuario que genero el reporte.
- `generado_en`: fecha/hora de generacion.

## Tablas catalogo

### gestiones

Representa una gestion/proceso CUP.

Campos principales:

- `id`: identificador.
- `nombre`: nombre unico de la gestion. Ejemplo: `CUP 1-2026`.
- `anio`: anio de la gestion.
- `periodo`: periodo academico opcional.
- `fecha_inicio`, `fecha_fin`: rango de vigencia.
- `estado`: estado operativo. Ejemplos sugeridos: `planificada`, `abierta`,
  `cerrada`, `publicada`.

Relaciones:

- Una gestion tiene muchas `inscripciones`.
- Una gestion tiene muchos `grupos`.
- Una gestion tiene muchos `cupos_carrera`.
- Una gestion tiene muchas `publicaciones_resultados`.
- Una gestion tiene muchos `reportes`.

### carreras

Catalogo de carreras disponibles para seleccion/asignacion.

Campos principales:

- `id`: identificador.
- `codigo`: codigo unico de carrera.
- `nombre`: nombre de la carrera.
- `activa`: habilita o deshabilita la carrera.

Relaciones:

- Una carrera aparece en muchas `opciones_carrera`.
- Una carrera tiene cupos por gestion en `cupos_carrera`.
- Una carrera puede ser asignada en `asignaciones_carrera`.

### aulas

Catalogo de aulas usadas para grupos y horarios.

Campos principales:

- `codigo`: codigo unico del aula.
- `nombre`: nombre visible.
- `capacidad`: capacidad fisica.
- `ubicacion`: bloque, piso o referencia.
- `activa`: disponibilidad del aula.

Relaciones:

- Un aula puede estar asociada a muchos `grupos`.
- Un aula puede estar asociada a muchos `horarios`.

### docentes

Catalogo de docentes del CUP.

Campos principales:

- `ci`: documento de identidad unico, opcional.
- `nombres`, `apellidos`.
- `correo`: correo unico, opcional.
- `telefono`.
- `activo`: disponibilidad del docente.

Relaciones:

- Un docente puede dictar muchas materias de grupo en `grupo_materias`.

### materias

Catalogo de materias/evaluaciones del CUP.

Campos principales:

- `codigo`: codigo unico.
- `nombre`: nombre de la materia.
- `activa`: habilitada o no.

Relaciones:

- Una materia puede estar en muchos grupos mediante `grupo_materias`.

## Tablas del registro e inscripcion

### postulantes

Guarda los datos personales del postulante. Se reutiliza entre gestiones.

Campos principales:

- `ci`: identificador unico del postulante.
- `complemento`: complemento del CI.
- `nombres`.
- `apellido_paterno`, `apellido_materno`.
- `fecha_nacimiento`.
- `genero`.
- `correo`: correo unico.
- `telefono`.
- `direccion`.
- `colegio_procedencia`: agregado por regla de negocio.
- `ciudad`: agregado por regla de negocio.

Relaciones:

- Un postulante puede tener muchas `inscripciones`.
- Restriccion importante: no puede tener mas de una inscripcion en la misma
  gestion, porque `inscripciones` tiene `unique(postulante_id, gestion_id)`.

### inscripciones

Es la tabla eje del sistema. Representa la postulacion CUP de un postulante en
una gestion.

Campos principales:

- `postulante_id`: FK a `postulantes`.
- `gestion_id`: FK a `gestiones`.
- `codigo`: codigo CUP unico para consulta y seguimiento.
- `fecha_inscripcion`.
- `estado`: estado general del proceso.
- `observacion`.

Estados sugeridos:

- `prepostulado`
- `documentos_pendientes`
- `validado`
- `biometria_completa`
- `pago_pendiente`
- `pagado`
- `asignado_grupo`
- `evaluado`
- `aprobado`
- `reprobado`
- `asignado_carrera`
- `lista_espera`

Relaciones:

- Pertenece a un `postulante`.
- Pertenece a una `gestion`.
- Tiene muchas `opciones_carrera`.
- Tiene muchos `documentos`.
- Tiene una `validacion_documental`.
- Tiene una `biometria`.
- Tiene muchos `pagos`.
- Tiene una asignacion de grupo en `inscripcion_grupo`.
- Tiene muchas `evaluaciones`.
- Tiene un `resultado_cup`.
- Tiene una `asignacion_carrera`.

### opciones_carrera

Guarda la primera y segunda opcion de carrera por inscripcion.

Campos principales:

- `inscripcion_id`: FK a `inscripciones`.
- `carrera_id`: FK a `carreras`.
- `prioridad`: solo puede ser `1` o `2`.

Reglas:

- `unique(inscripcion_id, prioridad)`: no puede haber dos primeras opciones ni
  dos segundas opciones.
- `unique(inscripcion_id, carrera_id)`: no puede repetir la misma carrera.
- `CHECK prioridad IN (1,2)`.

## Tablas de validacion documental y biometrica

### documentos

Guarda cada documento entregado por una inscripcion.

Campos principales:

- `inscripcion_id`.
- `tipo`: ejemplo `ci`, `titulo_bachiller`, `certificado`.
- `numero`: numero del documento si aplica.
- `archivo_path`: ruta del archivo digitalizado.
- `estado`: `pendiente`, `observado`, `aprobado`, `rechazado`.
- `observacion`.
- `revisado_por`: usuario que reviso.
- `revisado_en`.

Regla:

- `unique(inscripcion_id, tipo)`: un documento de cada tipo por inscripcion.

### validaciones_documentales

Representa el cierre de revision documental por inscripcion.

Campos principales:

- `inscripcion_id`: unico.
- `estado`: `pendiente`, `observado`, `aprobado`, `rechazado`.
- `observacion`.
- `validado_por`.
- `validado_en`.

Cardinalidad:

- Una inscripcion tiene como maximo una validacion documental.

### biometrias

Guarda la captura biometrica del postulante para una inscripcion.

Campos principales:

- `inscripcion_id`: unico.
- `foto_path`.
- `huella_hash`.
- `intentos`.
- `estado`: `pendiente`, `capturada`, `fallida`, `validada`.
- `capturado_en`.

Cardinalidad:

- Una inscripcion tiene como maximo una biometria.

## Tablas de pago

### pagos

Registra pagos asociados a una inscripcion.

Campos principales:

- `inscripcion_id`.
- `monto`.
- `moneda`: por defecto `BOB`.
- `metodo`: caja, pasarela, transferencia, etc.
- `referencia`: referencia externa unica.
- `estado`: `pendiente`, `aprobado`, `rechazado`, `anulado`.
- `pagado_en`.

Cardinalidad:

- Una inscripcion puede tener varios pagos, aunque en el flujo normal deberia
  terminar con un pago aprobado.

### recibos

Representa el comprobante/recibo emitido para un pago.

Campos principales:

- `pago_id`: unico.
- `numero`: numero unico del recibo.
- `archivo_path`.
- `emitido_por`.
- `emitido_en`.

Cardinalidad:

- Un pago tiene como maximo un recibo.

## Tablas de grupos, materias y horarios

### grupos

Representa un paralelo/grupo CUP dentro de una gestion.

Campos principales:

- `gestion_id`.
- `codigo`: codigo del grupo dentro de la gestion.
- `nombre`.
- `cupo_maximo`: maximo 70.
- `aula_id`: aula principal opcional.
- `estado`: `configuracion`, `habilitado`, `cerrado`, `publicado`.

Reglas:

- `unique(gestion_id, codigo)`.
- `CHECK cupo_maximo BETWEEN 1 AND 70`.

Relaciones:

- Pertenece a una `gestion`.
- Puede tener un `aula`.
- Tiene muchas `grupo_materias`.
- Tiene muchas inscripciones mediante `inscripcion_grupo`.

### grupo_materias

Une grupo, materia y docente. Esta tabla permite saber que materia dicta que
docente en que grupo.

Campos principales:

- `grupo_id`.
- `materia_id`.
- `docente_id`.

Reglas:

- `unique(grupo_id, materia_id)`: una materia no se duplica dentro del mismo
  grupo.

Relaciones:

- Tiene muchos `horarios`.
- Tiene muchas `evaluaciones`.

### horarios

Representa el horario de una materia dentro de un grupo.

Campos finales:

- `grupo_materia_id`: FK a `grupo_materias`.
- `aula_id`: aula especifica del horario.
- `dia_semana`: numero de 1 a 7.
- `hora_inicio`.
- `hora_fin`.

Reglas:

- `unique(grupo_materia_id, dia_semana, hora_inicio)`.
- `CHECK hora_fin > hora_inicio`.
- `CHECK dia_semana BETWEEN 1 AND 7`.

Nota importante:

- La migracion base creo `horarios` con `grupo_id`, pero la migracion patch lo
  cambia a `grupo_materia_id`. El modelo final correcto para backend/frontend
  es con `grupo_materia_id`, porque asi se puede mostrar horario por materia y
  docente sin ambiguedad.

### inscripcion_grupo

Asigna cada inscripcion a un grupo.

Campos principales:

- `inscripcion_id`: unico.
- `grupo_id`.
- `estado`: `asignado`, `retirado`, `cambiado`.
- `asignado_en`.

Cardinalidad:

- Una inscripcion puede tener como maximo un grupo activo en este modelo.

## Tablas de evaluaciones y resultados

### evaluaciones

Guarda las tres notas de examen de una materia para una inscripcion.

Campos principales:

- `inscripcion_id`.
- `grupo_materia_id`.
- `examen_1`, `examen_2`, `examen_3`.
- `promedio`.
- `estado`: `pendiente`, `registrado`, `aprobado`, `reprobado`.
- `registrado_por`.
- `registrado_en`.

Reglas:

- `unique(inscripcion_id, grupo_materia_id)`.
- Cada examen debe estar entre 0 y 100 si no es null.
- El promedio debe estar entre 0 y 100 si no es null.

### resultados_cup

Guarda el resultado final consolidado de una inscripcion.

Campos principales:

- `inscripcion_id`: unico.
- `promedio_final`.
- `estado_final`: `pendiente`, `aprobado`, `reprobado`.
- `cerrado_en`.

Uso:

- Se llena despues de calcular las evaluaciones.
- Es la fuente para asignar carrera y publicar resultados.

## Tablas de cupos y asignacion de carrera

### cupos_carrera

Define cuantos cupos tiene cada carrera en cada gestion.

Campos principales:

- `gestion_id`.
- `carrera_id`.
- `cupo_total`.
- `cupo_disponible`.

Reglas:

- `unique(gestion_id, carrera_id)`.
- `CHECK cupo_total >= 0`.
- `CHECK cupo_disponible >= 0`.
- `CHECK cupo_disponible <= cupo_total`.

### asignaciones_carrera

Guarda la carrera asignada al postulante aprobado o su estado de espera.

Campos principales:

- `inscripcion_id`: unico.
- `carrera_id`: nullable para lista de espera o no asignado.
- `opcion_prioridad`: `1`, `2` o null.
- `promedio_usado`.
- `estado`: `pendiente`, `asignado`, `lista_espera`, `sin_cupo`.
- `asignado_en`.

Uso:

- El sistema primero intenta asignar la primera opcion.
- Si no hay cupo, intenta la segunda opcion.
- Si no hay cupo en ninguna opcion, queda en lista de espera.

## Tablas de publicacion y reportes

### publicaciones_resultados

Controla la autorizacion y publicacion de resultados por gestion.

Campos principales:

- `gestion_id`.
- `titulo`.
- `estado`: `borrador`, `autorizado`, `publicado`, `cerrado`.
- `autorizado_por`.
- `autorizado_en`.
- `publicado_en`.

### reportes

Guarda reportes generados por gestion.

Campos principales:

- `publicacion_resultado_id`.
- `gestion_id`.
- `tipo`: `aprobados`, `reprobados`, `listas_oficiales`, etc.
- `archivo_path`.
- `generado_por`.
- `generado_en`.

## Relaciones principales resumidas

- `postulantes 1:N inscripciones`.
- `gestiones 1:N inscripciones`.
- `inscripciones 1:N opciones_carrera`.
- `carreras 1:N opciones_carrera`.
- `inscripciones 1:N documentos`.
- `inscripciones 1:1 validaciones_documentales`.
- `inscripciones 1:1 biometrias`.
- `inscripciones 1:N pagos`.
- `pagos 1:1 recibos`.
- `gestiones 1:N grupos`.
- `grupos 1:N grupo_materias`.
- `materias 1:N grupo_materias`.
- `docentes 1:N grupo_materias`.
- `grupo_materias 1:N horarios`.
- `inscripciones 1:1 inscripcion_grupo`.
- `grupos 1:N inscripcion_grupo`.
- `inscripciones 1:N evaluaciones`.
- `grupo_materias 1:N evaluaciones`.
- `inscripciones 1:1 resultados_cup`.
- `gestiones 1:N cupos_carrera`.
- `carreras 1:N cupos_carrera`.
- `inscripciones 1:1 asignaciones_carrera`.
- `carreras 1:N asignaciones_carrera`.
- `gestiones 1:N publicaciones_resultados`.
- `publicaciones_resultados 1:N reportes`.

## Observaciones tecnicas importantes

- El esquema final esta orientado a PostgreSQL.
- Los `CHECK CONSTRAINT` de la migracion patch usan `DB::statement` con SQL de
  PostgreSQL. Si despues se usan tests con SQLite en memoria, esa migracion
  podria fallar y habria que adaptar el entorno de tests a PostgreSQL o
  condicionar esos statements por driver.
- Como la migracion patch reemplaza `horarios.grupo_id` por
  `horarios.grupo_materia_id`, se recomienda ejecutar estas migraciones antes
  de cargar datos reales. Si ya existieran horarios cargados, habria que hacer
  una migracion de datos para mapear cada horario a una `grupo_materia`.
- La ejecucion real de `php artisan migrate` aun requiere tener habilitado
  `pdo_pgsql` en PHP, porque el proyecto esta configurado con `DB_CONNECTION=pgsql`.

## Orden sugerido para seeders o carga inicial

1. `gestiones`
2. `carreras`
3. `materias`
4. `aulas`
5. `docentes`
6. `postulantes`
7. `inscripciones`
8. `opciones_carrera`
9. `documentos`
10. `validaciones_documentales`
11. `biometrias`
12. `pagos`
13. `recibos`
14. `grupos`
15. `grupo_materias`
16. `horarios`
17. `inscripcion_grupo`
18. `evaluaciones`
19. `resultados_cup`
20. `cupos_carrera`
21. `asignaciones_carrera`
22. `publicaciones_resultados`
23. `reportes`

## Conclusion de revision

La base esta completa para empezar backend y frontend del flujo CUP:
prepostulacion, validacion, pago, asignacion de grupos, horarios por
materia/docente, evaluaciones, cupos, asignacion de carrera y publicacion de
resultados.

Antes de ejecutar en PostgreSQL hay que habilitar `pdo_pgsql`. Antes de crear
controladores/modelos conviene definir los estados exactos que usara cada
tabla, para evitar strings distintos para el mismo concepto.
