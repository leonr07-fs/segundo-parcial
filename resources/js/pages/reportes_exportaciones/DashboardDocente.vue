<script setup>
// [CU16] Consultar carga docente - Portal de docente para visualizar asignaturas y registrar asistencias

import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import ChangePasswordPanel from '../../components/ChangePasswordPanel.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roleLabel: {
        type: String,
        required: true,
    }
});

const emit = defineEmits(['logout', 'navigate']);
const docente = ref(null);
const carga = ref([]);
const resumenAsistencia = ref({
    clases_registradas: 0,
    registros_estudiantes: 0,
    porcentaje_asistencia_estudiantes: 0,
    presente: 0,
    ausente: 0,
    tardanza: 0,
    justificado: 0,
});
const cargando = ref(false);
const mensaje = ref('');
const fechaAsistencia = ref(new Date().toISOString().slice(0, 10));
const grupoMateriaSeleccionado = ref('');
const planilla = ref(null);
const cargandoPlanilla = ref(false);
const guardandoAsistencia = ref(false);
const mensajeAsistencia = ref('');
const diasBoleta = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
const cargaConHorarios = computed(() => carga.value.flatMap((item) => (item.horarios || []).map((horario) => ({
    ...item,
    horario,
}))));

onMounted(async () => {
    cargando.value = true;
    try {
        const { data } = await axios.get('/api/docente/carga');
        if (data.ok) {
            docente.value = data.data.docente;
            carga.value = data.data.carga;
            resumenAsistencia.value = data.data.resumen_asistencia || resumenAsistencia.value;
            mensaje.value = data.message || '';
        }
    } catch (error) {
        mensaje.value = 'No se pudo cargar la carga academica docente.';
    } finally {
        cargando.value = false;
    }
});

async function cargarPlanillaAsistencia() {
    if (!grupoMateriaSeleccionado.value || !fechaAsistencia.value) {
        return;
    }

    cargandoPlanilla.value = true;
    mensajeAsistencia.value = '';

    try {
        const { data } = await axios.get(`/api/docente/asistencias/${grupoMateriaSeleccionado.value}`, {
            params: { fecha: fechaAsistencia.value },
        });

        if (data.ok) {
            planilla.value = data.data;
        }
    } catch (error) {
        planilla.value = null;
        mensajeAsistencia.value = error.response?.data?.message ?? 'No se pudo cargar la planilla de asistencia.';
    } finally {
        cargandoPlanilla.value = false;
    }
}

async function guardarAsistencia() {
    if (!planilla.value) {
        return;
    }

    guardandoAsistencia.value = true;
    mensajeAsistencia.value = '';

    try {
        const { data } = await axios.post(`/api/docente/asistencias/${grupoMateriaSeleccionado.value}`, {
            fecha: fechaAsistencia.value,
            asistencias: planilla.value.estudiantes.map((estudiante) => ({
                inscripcion_id: estudiante.inscripcion_id,
                estado: estudiante.estado === 'pendiente' ? 'presente' : estudiante.estado,
                observacion: estudiante.observacion || null,
            })),
        });

        mensajeAsistencia.value = data.message;
        await cargarPlanillaAsistencia();
    } catch (error) {
        mensajeAsistencia.value = error.response?.data?.message ?? 'No se pudo guardar la asistencia.';
    } finally {
        guardandoAsistencia.value = false;
    }
}

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const abrirImpresion = (html) => {
    const ventana = window.open('', '_blank', 'width=1100,height=800');
    if (!ventana) return;
    ventana.document.write(html);
    ventana.document.close();
    ventana.focus();
    setTimeout(() => ventana.print(), 250);
};

const estilosBoleta = (orientacion = 'portrait') => `
    <style>
        @page { size: ${orientacion}; margin: 10mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; font-size: 13px; }
        .sheet { max-width: 1120px; margin: 0 auto; padding: 8px 16px; }
        .title { text-align: center; font-weight: 700; margin-bottom: 8px; }
        .header { display: grid; grid-template-columns: 1fr 92px; gap: 16px; align-items: start; margin-bottom: 14px; }
        .meta p { margin: 4px 0; }
        .qr { width: 86px; height: 86px; border: 4px solid #111; background: repeating-linear-gradient(45deg,#111 0 3px,#fff 3px 6px); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dff0d8; text-align: left; font-weight: 700; padding: 9px 8px; }
        td { border-bottom: 1px solid #ddd; padding: 9px 8px; vertical-align: top; }
        tbody tr:nth-child(even) { background: #fbf7df; }
        .center { text-align: center; }
        .schedule th, .schedule td { text-align: center; border-bottom: 1px solid #ddd; padding: 8px; height: 34px; }
        .schedule .time { width: 145px; background: #fff; font-weight: 400; }
        .cell-0 { background: #f68af0; }
        .cell-1 { background: #65eec6; }
        .cell-2 { background: #c9ff58; }
        .cell-3 { background: #ff9830; }
        .cell-4 { background: #fff98b; }
        .cell-5 { background: #c6c4ff; }
    </style>
`;

const cabeceraBoleta = () => `
    <div class="title">HORARIO DOCENTE CUP FICCT</div>
    <div class="header">
        <div class="meta">
            <p><b>Docente:</b> ${escapeHtml(props.user.name)}</p>
            <p><b>Registro:</b> ${escapeHtml(props.user.numero_registro || '')}</p>
            <p><b>Correo:</b> ${escapeHtml(props.user.email)}</p>
        </div>
        <div class="qr"></div>
    </div>
`;

const imprimirBoletaNormal = () => {
    const getModalidades = (item) => {
        if (!item.horarios?.length) return 'PRESENCIAL';
        const modalidades = [...new Set(item.horarios.map(h => h.modalidad || 'presencial'))];
        return modalidades.map(m => m.toUpperCase()).join(' / ');
    };
    const formatHorariosList = (item) => {
        if (!item.horarios?.length) return 'Horario por publicar';
        return item.horarios.map((h) => {
            const esVirtual = h.modalidad === 'virtual' || h.dia === 'Sab' || h.dia === 'Sabado' || h.dia === 'Sábado';
            const virtualLabel = esVirtual ? ' (Virtual)' : '';
            const text = `${h.dia} ${h.hora_inicio}-${h.hora_fin}${h.aula ? ` ${h.aula}` : ''}${virtualLabel}`;
            return escapeHtml(text);
        }).join('<br>');
    };
    const filas = carga.value.map((item) => `
        <tr>
            <td>${escapeHtml(item.materia_codigo)}</td>
            <td>${escapeHtml(item.grupo)}</td>
            <td>${escapeHtml(item.materia)}</td>
            <td>${escapeHtml(item.gestion)}</td>
            <td>${formatHorariosList(item)}</td>
            <td class="center">${escapeHtml(getModalidades(item))}</td>
        </tr>
    `).join('');

    abrirImpresion(`
        <!doctype html><html><head><title>Horario docente</title>${estilosBoleta('portrait')}</head>
        <body><div class="sheet">
            ${cabeceraBoleta()}
            <table>
                <thead><tr><th>SIGLA</th><th>GRUPO</th><th>MATERIA</th><th>GESTION</th><th>HORARIO / AULA</th><th>MODALIDAD</th></tr></thead>
                <tbody>${filas || '<tr><td colspan="6" class="center">Sin carga horaria asignada</td></tr>'}</tbody>
            </table>
        </div></body></html>
    `);
};

const imprimirBoletaHorario = () => {
    const eventos = cargaConHorarios.value.map((item, index) => {
        const esVirtual = item.horario.modalidad === 'virtual' || item.horario.dia === 'Sab' || item.horario.dia === 'Sabado' || item.horario.dia === 'Sábado';
        return {
            materia: `${item.materia_codigo} - ${item.grupo}`,
            dia: item.horario.dia,
            bloque: `${item.horario.hora_inicio} - ${item.horario.hora_fin}`,
            clase: `cell-${index % 6}`,
            esVirtual: esVirtual,
        };
    });
    const bloques = [...new Set(eventos.map((evento) => evento.bloque))].sort();
    const filas = bloques.map((bloque) => `
        <tr>
            <td class="time">${escapeHtml(bloque)}</td>
            ${diasBoleta.map((dia) => {
                const evento = eventos.find((item) => item.bloque === bloque && item.dia === dia);
                return `<td class="${evento?.clase || ''}">
                    ${evento ? `<div>${escapeHtml(evento.materia)}</div>` : ''}
                    ${evento?.esVirtual ? `<div style="font-size: 10px; font-weight: bold; color: #b91c1c; margin-top: 2px;">(VIRTUAL)</div>` : ''}
                </td>`;
            }).join('')}
        </tr>
    `).join('');

    abrirImpresion(`
        <!doctype html><html><head><title>Grilla docente</title>${estilosBoleta('landscape')}</head>
        <body><div class="sheet">
            ${cabeceraBoleta()}
            <table class="schedule">
                <thead><tr><th>HORARIO</th>${diasBoleta.map((dia) => `<th>${dia}</th>`).join('')}</tr></thead>
                <tbody>${filas || '<tr><td colspan="7" class="center">Sin carga horaria asignada</td></tr>'}</tbody>
            </table>
        </div></body></html>
    `);
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 w-full">
        <header class="bg-blue-900 text-white shadow-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-3">
                    <svg class="h-8 w-8 bg-white text-blue-900 rounded-full p-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-red-400">CUP FICCT</p>
                        <h1 class="text-base font-semibold text-white">Panel {{ roleLabel }}</h1>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-blue-700 bg-blue-800 px-4 py-2 text-sm font-medium text-blue-100 transition hover:bg-red-600 hover:border-red-600 hover:text-white"
                    @click="emit('logout')"
                >
                    Cerrar sesion
                </button>
            </div>
        </header>

        <div class="mx-auto grid max-w-6xl gap-6 px-6 py-8 lg:grid-cols-[0.8fr_1.2fr]">
            <aside class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="h-16 w-16 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-2xl font-bold mb-4">
                    {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <p class="text-xs font-bold text-red-500 uppercase tracking-wide">Usuario autenticado</p>
                <h2 class="mt-1 text-2xl font-bold text-blue-950">{{ user.name }}</h2>
                <dl class="mt-6 space-y-4 text-sm border-t border-gray-100 pt-6">
                    <div class="flex justify-between gap-4 items-center">
                        <dt class="text-gray-500">Correo</dt>
                        <dd class="font-medium text-gray-900">{{ user.email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 items-center">
                        <dt class="text-gray-500">Rol</dt>
                        <dd class="font-semibold text-blue-700 bg-blue-50 px-2 py-1 rounded-md">{{ roleLabel }}</dd>
                    </div>
                </dl>
                <ChangePasswordPanel />
            </aside>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-blue-950">Carga Academica Docente</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-500">
                        Materias y grupos vinculados al docente autenticado.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-5">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Estado Contrato</p>
                            <p class="mt-1 text-xs text-green-600 font-semibold">
                                {{ docente?.activo === false ? 'Inactivo' : 'Activo' }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Nivel de Acceso</p>
                            <p class="mt-1 text-xs text-blue-600 font-semibold">{{ roleLabel }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Materias Asignadas</p>
                            <p class="mt-1 text-xs text-gray-500 font-medium">{{ carga.length }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Clases con Asistencia</p>
                            <p class="mt-1 text-xs font-semibold text-emerald-700">{{ resumenAsistencia.clases_registradas }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Asistencia Estudiantil</p>
                            <p class="mt-1 text-xs font-semibold text-emerald-700">{{ resumenAsistencia.porcentaje_asistencia_estudiantes }}%</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-lg font-bold text-blue-950">Carga Horaria y Grupos Asignados</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800 transition hover:bg-blue-100" @click="imprimirBoletaNormal">
                                Boleta Normal
                            </button>
                            <button type="button" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100" @click="imprimirBoletaHorario">
                                Boleta Horario
                            </button>
                        </div>
                    </div>
                    <div v-if="cargando" class="text-sm text-gray-500 py-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        Cargando carga academica...
                    </div>
                    <div v-else-if="carga.length === 0" class="text-sm text-gray-500 py-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        {{ mensaje || 'No hay grupos asignados para la gestion actual.' }}
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Gestion</th>
                                    <th class="px-4 py-3">Grupo</th>
                                    <th class="px-4 py-3">Materia</th>
                                    <th class="px-4 py-3">Aula</th>
                                    <th class="px-4 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in carga" :key="item.id">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ item.gestion || 'Sin gestion' }}</td>
                                    <td class="px-4 py-3">{{ item.grupo }}</td>
                                    <td class="px-4 py-3">{{ item.materia_codigo }} - {{ item.materia }}</td>
                                    <td class="px-4 py-3">{{ item.aula || 'Sin aula' }}</td>
                                    <td class="px-4 py-3">{{ item.estado_grupo }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-blue-950">Registrar Asistencia</h3>
                            <p class="mt-1 text-sm text-gray-500">Selecciona una materia, fecha y marca la asistencia del grupo.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                            <label class="text-sm font-semibold text-slate-700">
                                Materia y grupo
                                <select v-model="grupoMateriaSeleccionado" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Seleccione...</option>
                                    <option v-for="item in carga" :key="item.id" :value="item.id">
                                        {{ item.grupo }} - {{ item.materia_codigo }} {{ item.materia }}
                                    </option>
                                </select>
                            </label>
                            <label class="text-sm font-semibold text-slate-700">
                                Fecha
                                <input v-model="fechaAsistencia" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <button
                                type="button"
                                class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-900 disabled:opacity-60"
                                :disabled="!grupoMateriaSeleccionado || !fechaAsistencia || cargandoPlanilla"
                                @click="cargarPlanillaAsistencia"
                            >
                                {{ cargandoPlanilla ? 'Cargando...' : 'Cargar' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="mensajeAsistencia" class="mt-4 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-800">
                        {{ mensajeAsistencia }}
                    </div>

                    <div v-if="planilla" class="mt-5 overflow-x-auto">
                        <div class="mb-4 grid gap-3 sm:grid-cols-5">
                            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase text-slate-500">Registrados</p>
                                <p class="mt-1 text-sm font-bold text-slate-900">{{ planilla.resumen.registrados }}/{{ planilla.resumen.total_estudiantes }}</p>
                            </div>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                                <p class="text-xs font-semibold uppercase text-emerald-700">Presentes</p>
                                <p class="mt-1 text-sm font-bold text-emerald-800">{{ planilla.resumen.presente }}</p>
                            </div>
                            <div class="rounded-lg border border-red-100 bg-red-50 p-3">
                                <p class="text-xs font-semibold uppercase text-red-700">Ausentes</p>
                                <p class="mt-1 text-sm font-bold text-red-800">{{ planilla.resumen.ausente }}</p>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50 p-3">
                                <p class="text-xs font-semibold uppercase text-amber-700">Pendientes</p>
                                <p class="mt-1 text-sm font-bold text-amber-800">{{ planilla.resumen.pendientes }}</p>
                            </div>
                            <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                                <p class="text-xs font-semibold uppercase text-blue-700">Asistencia</p>
                                <p class="mt-1 text-sm font-bold text-blue-800">{{ planilla.resumen.porcentaje_asistencia }}%</p>
                            </div>
                        </div>
                        <div class="mb-3 text-sm text-slate-600">
                            {{ planilla.grupo_materia.gestion }} · Grupo {{ planilla.grupo_materia.grupo }} · {{ planilla.grupo_materia.materia }}
                        </div>
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Codigo</th>
                                    <th class="px-4 py-3">Postulante</th>
                                    <th class="px-4 py-3">Historico</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Observacion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="estudiante in planilla.estudiantes" :key="estudiante.inscripcion_id">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ estudiante.codigo }}</td>
                                    <td class="px-4 py-3">{{ estudiante.postulante }}</td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-slate-900">{{ estudiante.porcentaje_asistencia }}%</span>
                                        <span class="block text-xs text-slate-400">{{ estudiante.asistencias_validas }}/{{ estudiante.total_clases }} clases</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select v-model="estudiante.estado" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                            <option value="presente">Presente</option>
                                            <option value="ausente">Ausente</option>
                                            <option value="tardanza">Tardanza</option>
                                            <option value="justificado">Justificado</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input v-model="estudiante.observacion" type="text" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm" placeholder="Opcional">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4 flex justify-end">
                            <button
                                type="button"
                                class="rounded-lg bg-green-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-green-800 disabled:opacity-60"
                                :disabled="guardandoAsistencia"
                                @click="guardarAsistencia"
                            >
                                {{ guardandoAsistencia ? 'Guardando...' : 'Guardar asistencia' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
