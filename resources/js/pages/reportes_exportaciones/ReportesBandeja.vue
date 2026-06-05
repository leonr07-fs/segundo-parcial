<script setup>
// [CU18] Generar reportes - Generación de listados oficiales dinámicos y estáticos

import { computed, nextTick, onMounted, ref } from 'vue';
import { generarReporteDinamico, getCatalogoReportes, getReporteAsistenciasAdmin, getReporteEstatico } from '../../api/reportes';
import { useToast } from '../../api/toast';

const toast = useToast();

const catalogo = ref({
    reportes_estaticos: [],
    modulos_dinamicos: {},
    gestiones: [],
    materias: [],
});
const modo = ref('estatico');
const tipoEstatico = ref('aprobados');
const moduloDinamico = ref('postulantes');
const formatoSalida = ref('pdf');
const columnasSeleccionadas = ref([]);
const filtros = ref({
    gestion_id: '',
    materia_id: '',
    fecha_desde: '',
    fecha_hasta: '',
});
const reporte = ref(null);
const asistenciaAdmin = ref([]);
const cargando = ref(false);
const cargandoCatalogo = ref(false);
const cargandoAsistenciaAdmin = ref(false);
const docenteAsistenciaAbierto = ref(null);

const modulos = computed(() => catalogo.value.modulos_dinamicos ?? {});
const moduloActual = computed(() => modulos.value[moduloDinamico.value] ?? { columnas: [] });
const columnasActuales = computed(() => reporte.value?.columnas ?? []);
const filasActuales = computed(() => reporte.value?.filas ?? []);
const totalFilas = computed(() => filasActuales.value.length);
const totalDocentesAsistencia = computed(() => asistenciaAdmin.value.length);

onMounted(async () => {
    cargandoCatalogo.value = true;

    try {
        const payload = await getCatalogoReportes();
        catalogo.value = payload.data;
        const primerModulo = Object.keys(catalogo.value.modulos_dinamicos ?? {})[0];
        moduloDinamico.value = primerModulo ?? 'postulantes';
        resetColumnasDinamicas();
        await generar();
    } catch (error) {
        toast.alert({
            title: 'No se pudo cargar reportes',
            message: error.response?.data?.message ?? 'Intenta nuevamente.',
            confirmText: 'Aceptar',
            tone: 'danger',
        });
    } finally {
        cargandoCatalogo.value = false;
    }
});

function filtrosLimpios() {
    return Object.fromEntries(
        Object.entries(filtros.value).filter(([, valor]) => valor !== '' && valor !== null && valor !== undefined),
    );
}

async function cargarAsistenciaAdmin() {
    cargandoAsistenciaAdmin.value = true;

    try {
        const payload = await getReporteAsistenciasAdmin(filtrosLimpios());
        asistenciaAdmin.value = payload.data.resumen_docentes ?? [];
        docenteAsistenciaAbierto.value = asistenciaAdmin.value[0]?.docente_id ?? null;
    } catch (error) {
        toast.alert({
            title: 'No se pudo cargar asistencia',
            message: error.response?.data?.message ?? 'Intenta nuevamente.',
            confirmText: 'Aceptar',
            tone: 'danger',
        });
    } finally {
        cargandoAsistenciaAdmin.value = false;
    }
}

function toggleDocenteAsistencia(docenteId) {
    docenteAsistenciaAbierto.value = docenteAsistenciaAbierto.value === docenteId ? null : docenteId;
}

function resetColumnasDinamicas() {
    columnasSeleccionadas.value = (moduloActual.value.columnas ?? [])
        .slice(0, 5)
        .map((columna) => columna.key);
}

function cambiarModulo() {
    resetColumnasDinamicas();
    reporte.value = null;
}

function toggleColumna(key) {
    if (columnasSeleccionadas.value.includes(key)) {
        columnasSeleccionadas.value = columnasSeleccionadas.value.filter((columna) => columna !== key);
        return;
    }

    columnasSeleccionadas.value = [...columnasSeleccionadas.value, key];
}

async function generar() {
    cargando.value = true;

    try {
        const payload = modo.value === 'estatico'
            ? await getReporteEstatico(tipoEstatico.value, filtrosLimpios())
            : await generarReporteDinamico({
                modulo: moduloDinamico.value,
                columnas: columnasSeleccionadas.value,
                filtros: filtrosLimpios(),
            });

        reporte.value = payload.data;
        await cargarAsistenciaAdmin();
    } catch (error) {
        toast.alert({
            title: 'Reporte no generado',
            message: error.response?.data?.message ?? 'Revisa los filtros e intenta nuevamente.',
            confirmText: 'Aceptar',
            tone: 'danger',
        });
    } finally {
        cargando.value = false;
    }
}

async function generarSalida() {
    if (!reporte.value) {
        return;
    }

    if (formatoSalida.value === 'html') {
        abrirReporteHtml();
        return;
    }

    await imprimirPdf();
}

async function imprimirPdf() {
    const tituloAnterior = document.title;
    document.title = reporte.value?.titulo ?? 'Reporte CUP';

    await nextTick();
    window.print();

    window.setTimeout(() => {
        document.title = tituloAnterior;
    }, 500);
}

function abrirReporteHtml() {
    const ventana = window.open('', '_blank');

    if (!ventana) {
        toast.alert({
            title: 'No se pudo abrir HTML',
            message: 'El navegador bloqueo la ventana nueva. Permite ventanas emergentes para generar el reporte HTML.',
            confirmText: 'Aceptar',
            tone: 'danger',
        });
        return;
    }

    ventana.document.open();
    ventana.document.write(construirHtmlReporte());
    ventana.document.close();
}

function construirHtmlReporte() {
    const columnas = columnasActuales.value;
    const filas = filasActuales.value;

    const encabezados = columnas
        .map((columna) => `<th>${escapeHtml(columna.label)}</th>`)
        .join('');
    const cuerpo = filas.length > 0
        ? filas.map((fila) => `<tr>${columnas.map((columna) => `<td>${escapeHtml(fila[columna.key] ?? '-')}</td>`).join('')}</tr>`).join('')
        : `<tr><td colspan="${columnas.length}">Sin registros para los filtros aplicados.</td></tr>`;

    return `<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>${escapeHtml(reporte.value?.titulo ?? 'Reporte CUP')}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        .meta { display: flex; justify-content: space-between; gap: 16px; border-bottom: 1px solid #cbd5e1; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { font-size: 11px; font-weight: 700; color: #0e7490; text-transform: uppercase; }
        h1 { font-size: 20px; margin: 4px 0 0; }
        .small { font-size: 12px; color: #475569; text-align: right; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        th { background: #e2e8f0; text-align: left; font-size: 11px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fafc; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="meta">
        <div>
            <div class="brand">CUP FICCT</div>
            <h1>${escapeHtml(reporte.value?.titulo ?? 'Reporte CUP')}</h1>
        </div>
        <div class="small">
            <div>Generado: ${escapeHtml(reporte.value?.generado_en ?? '-')}</div>
            <div>Total filas: ${filas.length}</div>
        </div>
    </div>
    <table>
        <thead><tr>${encabezados}</tr></thead>
        <tbody>${cuerpo}</tbody>
    </table>
    <p class="no-print" style="margin-top:16px"><button onclick="window.print()">Imprimir HTML</button></p>
</body>
</html>`;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>

<template>
    <div class="space-y-6">
        <section class="report-controls rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700">Reportes CUP</p>
                    <h2 class="text-2xl font-bold text-slate-950">Reportes y Exportaciones</h2>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="modo === 'estatico' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="modo = 'estatico'; reporte = null"
                    >
                        Oficiales
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="modo === 'dinamico' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="modo = 'dinamico'; reporte = null"
                    >
                        Dinamicos
                    </button>
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-4">
                <label class="text-sm font-semibold text-slate-700">
                    Gestion
                    <select v-model="filtros.gestion_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="gestion in catalogo.gestiones" :key="gestion.id" :value="gestion.id">
                            {{ gestion.nombre }}
                        </option>
                    </select>
                </label>

                <label class="text-sm font-semibold text-slate-700">
                    Desde
                    <input v-model="filtros.fecha_desde" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>

                <label class="text-sm font-semibold text-slate-700">
                    Hasta
                    <input v-model="filtros.fecha_hasta" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>

                <label v-if="modo === 'dinamico' && moduloDinamico === 'evaluaciones'" class="text-sm font-semibold text-slate-700">
                    Materia
                    <select v-model="filtros.materia_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="materia in catalogo.materias" :key="materia.id" :value="materia.id">
                            {{ materia.nombre }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_auto]">
                <label v-if="modo === 'estatico'" class="text-sm font-semibold text-slate-700">
                    Reporte oficial
                    <select v-model="tipoEstatico" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="item in catalogo.reportes_estaticos" :key="item.id" :value="item.id">
                            {{ item.nombre }}
                        </option>
                    </select>
                </label>

                <div v-else class="space-y-3">
                    <label class="text-sm font-semibold text-slate-700">
                        Modulo
                        <select v-model="moduloDinamico" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="cambiarModulo">
                            <option v-for="(item, key) in modulos" :key="key" :value="key">
                                {{ item.nombre }}
                            </option>
                        </select>
                    </label>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="columna in moduloActual.columnas"
                            :key="columna.key"
                            class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-cyan-700"
                                :checked="columnasSeleccionadas.includes(columna.key)"
                                @change="toggleColumna(columna.key)"
                            >
                            {{ columna.label }}
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    <label class="min-w-36 text-sm font-semibold text-slate-700">
                        Formato
                        <select v-model="formatoSalida" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="pdf">PDF</option>
                            <option value="html">HTML</option>
                        </select>
                    </label>
                    <button
                        type="button"
                        class="rounded-lg bg-cyan-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="cargando || cargandoCatalogo"
                        @click="generar"
                    >
                        {{ cargando ? 'Generando...' : 'Generar' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!reporte"
                        @click="generarSalida"
                    >
                        Generar salida
                    </button>
                </div>
            </div>
        </section>

        <section class="report-controls rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Asistencia CUP</p>
                    <h3 class="text-xl font-bold text-slate-950">Control por Docente</h3>
                    <p class="mt-1 text-sm text-slate-500">Visualiza cada docente, sus materias y la asistencia de estudiantes por separado.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-slate-500">
                        {{ cargandoAsistenciaAdmin ? 'Cargando...' : `${totalDocentesAsistencia} docentes` }}
                    </span>
                    <button
                        type="button"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100 disabled:opacity-60"
                        :disabled="cargandoAsistenciaAdmin"
                        @click="cargarAsistenciaAdmin"
                    >
                        Actualizar
                    </button>
                </div>
            </div>

            <div v-if="asistenciaAdmin.length === 0" class="py-10 text-center text-sm font-medium text-slate-500">
                No hay asistencias registradas para los filtros aplicados.
            </div>

            <div v-else class="mt-4 space-y-3">
                <article
                    v-for="docente in asistenciaAdmin"
                    :key="docente.docente_id"
                    class="rounded-xl border border-slate-200 bg-slate-50"
                >
                    <button
                        type="button"
                        class="flex w-full flex-col gap-3 px-4 py-4 text-left sm:flex-row sm:items-center sm:justify-between"
                        @click="toggleDocenteAsistencia(docente.docente_id)"
                    >
                        <div>
                            <h4 class="text-base font-bold text-blue-950">{{ docente.docente || 'Docente sin nombre' }}</h4>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ docente.clases_registradas }} clases con asistencia · {{ docente.registros_estudiantes }} registros
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm sm:min-w-72">
                            <span class="rounded-lg bg-white px-3 py-2 font-semibold text-slate-700">
                                Materias: {{ docente.materias.length }}
                            </span>
                            <span class="rounded-lg bg-white px-3 py-2 font-semibold text-emerald-700">
                                {{ docente.porcentaje_asistencia }}%
                            </span>
                        </div>
                    </button>

                    <div v-if="docenteAsistenciaAbierto === docente.docente_id" class="space-y-4 border-t border-slate-200 bg-white p-4">
                        <div
                            v-for="materia in docente.materias"
                            :key="materia.grupo_materia_id"
                            class="rounded-xl border border-slate-100"
                        >
                            <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase text-slate-500">{{ materia.gestion }} · Grupo {{ materia.grupo }}</p>
                                    <h5 class="text-sm font-bold text-slate-950">{{ materia.materia_codigo }} - {{ materia.materia }}</h5>
                                </div>
                                <div class="text-sm font-semibold text-emerald-700">
                                    {{ materia.clases_registradas }} clases · {{ materia.porcentaje_asistencia }}%
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="bg-white text-xs uppercase text-slate-500">
                                        <tr>
                                            <th class="border-b border-slate-100 px-4 py-2">Codigo</th>
                                            <th class="border-b border-slate-100 px-4 py-2">Estudiante</th>
                                            <th class="border-b border-slate-100 px-4 py-2">Presente</th>
                                            <th class="border-b border-slate-100 px-4 py-2">Ausente</th>
                                            <th class="border-b border-slate-100 px-4 py-2">Tardanza</th>
                                            <th class="border-b border-slate-100 px-4 py-2">Justificado</th>
                                            <th class="border-b border-slate-100 px-4 py-2">% Asistencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="estudiante in materia.estudiantes"
                                            :key="estudiante.inscripcion_id"
                                            class="odd:bg-white even:bg-slate-50"
                                        >
                                            <td class="border-b border-slate-100 px-4 py-2 font-semibold text-slate-900">{{ estudiante.codigo }}</td>
                                            <td class="border-b border-slate-100 px-4 py-2">{{ estudiante.postulante }}</td>
                                            <td class="border-b border-slate-100 px-4 py-2">{{ estudiante.presente }}</td>
                                            <td class="border-b border-slate-100 px-4 py-2">{{ estudiante.ausente }}</td>
                                            <td class="border-b border-slate-100 px-4 py-2">{{ estudiante.tardanza }}</td>
                                            <td class="border-b border-slate-100 px-4 py-2">{{ estudiante.justificado }}</td>
                                            <td class="border-b border-slate-100 px-4 py-2 font-bold text-emerald-700">{{ estudiante.porcentaje_asistencia }}%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section id="reporte-print" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">CUP FICCT</p>
                    <h3 class="text-xl font-bold text-slate-950">{{ reporte?.titulo ?? 'Reporte sin generar' }}</h3>
                </div>
                <div class="text-left text-xs text-slate-500 sm:text-right">
                    <p>Generado: {{ reporte?.generado_en ?? '-' }}</p>
                    <p>Total filas: {{ totalFilas }}</p>
                </div>
            </div>

            <div v-if="!reporte" class="py-16 text-center text-sm font-medium text-slate-500">
                No hay datos generados.
            </div>

            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-xs uppercase text-slate-600">
                            <th
                                v-for="columna in columnasActuales"
                                :key="columna.key"
                                class="border border-slate-200 px-3 py-2 font-bold"
                            >
                                {{ columna.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filasActuales.length === 0">
                            <td :colspan="columnasActuales.length" class="border border-slate-200 px-3 py-8 text-center text-slate-500">
                                Sin registros para los filtros aplicados.
                            </td>
                        </tr>
                        <tr v-for="(fila, index) in filasActuales" :key="index" class="odd:bg-white even:bg-slate-50">
                            <td
                                v-for="columna in columnasActuales"
                                :key="columna.key"
                                class="border border-slate-200 px-3 py-2 align-top text-slate-800"
                            >
                                {{ fila[columna.key] ?? '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 14mm;
    }

    :global(body) {
        background: white;
    }

    :global(.report-no-print) {
        display: none !important;
    }

    .report-controls {
        display: none !important;
    }

    #reporte-print {
        border: 0;
        box-shadow: none;
        padding: 0;
        margin: 0;
    }
}
</style>
