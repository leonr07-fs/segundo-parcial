# Sistema CUP FICCT

![Laravel](https://img.shields.io/badge/Laravel-12-red)
![Vue](https://img.shields.io/badge/Vue.js-3-42b883)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Database-336791)
![Vite](https://img.shields.io/badge/Vite-Frontend-646CFF)

Aplicacion web para administrar el proceso de admision universitaria del Curso Preuniversitario (CUP) de la FICCT.

El sistema cubre el flujo principal del postulante: registro, validacion documental, pago, organizacion academica, asistencia docente, carga progresiva de examenes, resultado final y asignacion de carrera por cupos.

## Stack Tecnologico

| Capa | Tecnologia |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | Vue.js 3 |
| Estilos | TailwindCSS 4 |
| Base de datos | PostgreSQL |
| Bundler | Vite |
| Cliente HTTP | Axios |
| Pruebas | PHPUnit Feature Tests |

## Modulos Principales

- Autenticacion por roles.
- Registro y repostulacion de postulantes.
- Validacion documental y prevalidacion.
- Registro y verificacion de pagos.
- Gestion de gestiones academicas, materias, aulas, grupos y docentes.
- Asignacion automatica de grupos, materias, docentes, aulas y horarios.
- Registro de asistencia docente.
- Importacion progresiva de examenes CUP.
- Validacion academica y calculo de promedios.
- Asignacion de carrera por cupos configurables por gestion.
- Reportes, dashboards y bitacora de auditoria.

## Reglas De Negocio

- Cada postulante puede tener una sola inscripcion por gestion academica.
- Cada postulante registra primera y segunda opcion de carrera.
- Las carreras tienen cupos independientes por gestion.
- La asignacion de carrera prioriza los promedios mas altos.
- Si no existe cupo en la primera opcion, se intenta asignar la segunda opcion.
- Las materias evaluadas del CUP son Computacion, Matematicas, Ingles y Fisica.
- Cada materia contempla tres examenes.
- Los examenes se cargan de forma progresiva: primero examen 1, luego examen 2 y finalmente examen 3.
- El sistema calcula los promedios, no el archivo CSV.
- Las notas validas estan entre 0 y 100.
- El postulante aprueba con promedio final mayor o igual a 60.
- Cada grupo admite como maximo 70 estudiantes.
- La cantidad de grupos se calcula con `CEIL(total_inscritos / 70)`.
- Un docente puede ser asignado como maximo a 4 grupos.

## Flujo General

1. El postulante envia su formulario y documentos.
2. Administracion valida los documentos.
3. Si los documentos son aprobados, se habilita el pago.
4. Cajero o administracion registra y verifica el pago.
5. El sistema genera credenciales para el postulante.
6. Administracion cierra inscripciones.
7. El sistema genera grupos usando cupo maximo de 70 estudiantes.
8. Se asignan materias, docentes, aulas y horarios.
9. El docente registra asistencia por materia, grupo y fecha.
10. Administracion importa el examen 1.
11. Luego importa el examen 2 para estudiantes habilitados.
12. Finalmente importa el examen 3.
13. El sistema calcula resultados CUP.
14. Administracion configura cupos por carrera y gestion.
15. El sistema asigna carreras por orden de merito.

## Formato CSV De Examenes

Cada carga corresponde a un solo examen seleccionado desde la pantalla de importacion.

```csv
inscripcion_codigo,grupo_materia_id,nota
CUP-2026-00001,15,85
CUP-2026-00002,15,72
```

Archivos sinteticos recomendados:

```text
storage/app/datos_sinteticos/carga_separada/notas_examen_1.csv
storage/app/datos_sinteticos/carga_separada/notas_examen_2.csv
storage/app/datos_sinteticos/carga_separada/notas_examen_3.csv
```

## Instalacion Local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Configurar PostgreSQL en `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cupficct
DB_USERNAME=postgres
DB_PASSWORD=
```

## Ejecucion En Desarrollo

```bash
composer run dev
```

Tambien se puede ejecutar por separado:

```bash
php artisan serve
npm run dev
```

## Datos Sinteticos

Generar archivos de carga separados:

```bash
php artisan cup:synthetic-export-files
```

Los archivos se generan en:

```text
storage/app/datos_sinteticos/carga_separada
```

## Pruebas

```bash
php artisan test
```

Pruebas puntuales:

```bash
php artisan test --filter=Cu09ImportacionResultadosTest
php artisan test --filter=Cu10ValidacionAcademicaTest
php artisan test --filter=Cu12AsignacionCarreraTest
php artisan test --filter=AsignacionAutomaticaTest
```

## Pasarela De Pago

La integracion con Libelula esta planificada como fase final. El flujo definido es:

- Primero se valida la documentacion.
- Luego se genera la orden, link o QR de pago.
- Despues se confirma el pago.
- Finalmente se generan credenciales.

El detalle esta documentado en `docs/PLAN_PASARELA_PAGO_LIBELULA.md`.

## Estado Del Proyecto

Proyecto academico orientado a cumplir los requerimientos del segundo examen parcial de Sistemas de Informacion 1 para una aplicacion web de admision universitaria CUP FICCT.
