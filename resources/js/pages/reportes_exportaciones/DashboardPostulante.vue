<script setup>
// [CU17] Consultar información del postulante / [CU06] Repostular - Portal privado y boleta de inscripción (Sábados Virtuales)

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

const gestionesAbiertas = ref([]);
const carreras = ref([]);
const academico = ref(null);
const cargandoAcademico = ref(false);
const cargandoRepostulacion = ref(false);
const formRepostulacion = ref({
    gestion_id: '',
    opcion1_carrera_id: '',
    opcion2_carrera_id: '',
});
const mensajeExito = ref('');
const errorMensaje = ref('');

const examenCup = computed(() => academico.value?.examen_cup ?? {
    estado: 'pendiente',
    siguiente_examen: null,
    motivo: 'La informacion del examen CUP se publicara cuando tu inscripcion este confirmada.',
});
const materiasCup = computed(() => academico.value?.materias_cup ?? []);
const estaHabilitadoExamen = computed(() => examenCup.value.estado === 'habilitado');
const estadoExamenLabel = computed(() => {
    if (examenCup.value.estado === 'habilitado') return 'Habilitado';
    if (examenCup.value.estado === 'no_habilitado') return 'No habilitado';
    if (examenCup.value.estado === 'finalizado') return 'Finalizado';
    return 'Pendiente';
});

onMounted(async () => {
    cargandoAcademico.value = true;
    try {
        const { data: academicoData } = await axios.get('/api/postulante/academico');
        if (academicoData.ok) {
            academico.value = academicoData.data;
        }
    } catch (e) {
        console.error('Error loading academic dashboard data', e);
    } finally {
        cargandoAcademico.value = false;
    }

    cargandoRepostulacion.value = true;
    try {
        const { data: formData } = await axios.get('/api/postulaciones/create');
        if (formData.ok) {
            carreras.value = formData.data.carreras;
            gestionesAbiertas.value = formData.data.gestion ? [formData.data.gestion] : [];
        }
    } catch (e) {
        gestionesAbiertas.value = [];
        console.info('No hay gestion abierta para repostulacion en este momento.');
    } finally {
        cargandoRepostulacion.value = false;
    }
});

const repostular = async () => {
    errorMensaje.value = '';
    mensajeExito.value = '';
    try {
        const payload = {
            ...formRepostulacion.value,
            postulante_id: props.user.postulante?.id || academico.value?.postulante?.id || props.user.id,
        };
        const { data } = await axios.post('/api/postulantes/repostular', payload);
        if (data.ok) {
            mensajeExito.value = data.message;
        }
    } catch (error) {
        errorMensaje.value = error.response?.data?.message || 'Error al procesar la solicitud.';
    }
};

const formatoNota = (nota) => nota === null || nota === undefined ? '-' : Number(nota).toFixed(2);
const formatoHorario = (materia) => {
    if (!materia.horarios?.length) return 'Horario por publicar';

    return materia.horarios
        .map((horario) => {
            const esVirtual = horario.modalidad === 'virtual' || horario.dia === 'Sabado' || horario.dia === 'Sábado' || horario.dia === 'Sab';
            const virtualLabel = esVirtual ? ' (Virtual)' : '';
            return `${horario.dia} ${horario.hora_inicio}-${horario.hora_fin}${horario.aula ? ` ${horario.aula}` : ''}${virtualLabel}`;
        })
        .join(' / ');
};
const asegurarAcademico = async () => {
    if (cargandoAcademico.value) return;

    if (!academico.value) {
        try {
            cargandoAcademico.value = true;
            const { data } = await axios.get('/api/postulante/academico');
            if (data.ok) {
                academico.value = data.data;
            }
        } finally {
            cargandoAcademico.value = false;
        }
    }
};

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const diasBoleta = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
const diaCorto = (dia) => ({
    Lunes: 'Lun',
    Martes: 'Mar',
    Miercoles: 'Mie',
    Miércoles: 'Mie',
    Jueves: 'Jue',
    Viernes: 'Vie',
    Sabado: 'Sab',
    Sábado: 'Sab',
}[dia] || dia);

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
        .small { font-size: 12px; color: #374151; }
    </style>
`;

const cabeceraBoleta = () => {
    const postulante = academico.value?.postulante || {};
    const inscripcion = academico.value?.inscripcion || {};
    const nombre = [postulante.nombres, postulante.apellido_paterno, postulante.apellido_materno].filter(Boolean).join(' ') || props.user.name;

    return `
        <div class="title">BOLETA DE INSCRIPCION ${escapeHtml(inscripcion.gestion || '')}</div>
        <div class="header">
            <div class="meta">
                <p><b>Registro:</b> ${escapeHtml(inscripcion.codigo || props.user.numero_registro || '')} <b>Nombre:</b> ${escapeHtml(nombre)}</p>
                <p><b>Carrera:</b> ${escapeHtml(academico.value?.asignacion_carrera?.carrera || 'Pendiente')}</p>
                <p><b>Lugar:</b> SANTA CRUZ</p>
            </div>
            <div class="qr"></div>
        </div>
    `;
};

const imprimirBoletaNormal = async () => {
    await asegurarAcademico();
    const getModalidades = (materia) => {
        if (!materia.horarios?.length) return 'PRESENCIAL';
        const modalidades = [...new Set(materia.horarios.map(h => h.modalidad || 'presencial'))];
        return modalidades.map(m => m.toUpperCase()).join(' / ');
    };
    const filas = materiasCup.value.map((materia) => `
        <tr>
            <td>${escapeHtml(materia.materia.slice(0, 3).toUpperCase())}</td>
            <td>${escapeHtml(academico.value?.grupo?.codigo || '-')}</td>
            <td>${escapeHtml(materia.materia)}</td>
            <td class="center">10</td>
            <td class="center">CUP</td>
            <td>${escapeHtml(formatoHorario(materia)).replaceAll(' / ', '<br>')}</td>
            <td class="center">0</td>
            <td class="center">${escapeHtml(getModalidades(materia))}</td>
        </tr>
    `).join('');

    abrirImpresion(`
        <!doctype html><html><head><title>Boleta normal</title>${estilosBoleta('portrait')}</head>
        <body><div class="sheet">
            ${cabeceraBoleta()}
            <table>
                <thead><tr><th>SIGLA</th><th>GRUPO</th><th>NOMBRE MATERIA</th><th>CRED</th><th>SEM</th><th>HORARIO</th><th>REPR</th><th>MODALIDAD</th></tr></thead>
                <tbody>${filas || '<tr><td colspan="8" class="center">Sin horarios asignados</td></tr>'}</tbody>
            </table>
        </div></body></html>
    `);
};

const imprimirBoletaHorario = async () => {
    await asegurarAcademico();
    const eventos = materiasCup.value.flatMap((materia, materiaIndex) => (materia.horarios || []).map((horario) => {
        const esVirtual = horario.modalidad === 'virtual' || horario.dia === 'Sabado' || horario.dia === 'Sábado' || horario.dia === 'Sab';
        return {
            materia: `${materia.materia.slice(0, 3).toUpperCase()} - ${academico.value?.grupo?.codigo || '-'}`,
            dia: diaCorto(horario.dia),
            bloque: `${horario.hora_inicio} - ${horario.hora_fin}`,
            clase: `cell-${materiaIndex % 6}`,
            esVirtual: esVirtual,
        };
    }));
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
        <!doctype html><html><head><title>Boleta horario</title>${estilosBoleta('landscape')}</head>
        <body><div class="sheet">
            ${cabeceraBoleta()}
            <table class="schedule">
                <thead><tr><th>HORARIO</th>${diasBoleta.map((dia) => `<th>${dia}</th>`).join('')}</tr></thead>
                <tbody>${filas || '<tr><td colspan="7" class="center">Sin horarios asignados</td></tr>'}</tbody>
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
                        <h1 class="text-base font-semibold text-white">Portal del Postulante</h1>
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
                    <div class="flex justify-between gap-4 items-center">
                        <dt class="text-gray-500">Codigo</dt>
                        <dd class="font-medium text-gray-900">{{ academico?.inscripcion?.codigo || 'Pendiente' }}</dd>
                    </div>
                </dl>
                <ChangePasswordPanel />
            </aside>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-blue-950">
                        Acceso {{ academico?.inscripcion?.estado === 'reprobado' || user.postulante?.inscripcion_estado === 'reprobado' ? 'Restringido' : 'Habilitado' }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-500">
                        Estado academico y seguimiento de tu inscripcion CUP.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Estado Admision</p>
                            <p class="mt-1 text-xs font-bold" :class="academico?.inscripcion?.estado === 'reprobado' ? 'text-red-600' : 'text-green-600'">
                                {{ academico?.inscripcion?.estado || user.postulante?.inscripcion_estado || 'HABILITADO' }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Nivel de Acceso</p>
                            <p class="mt-1 text-xs text-blue-600 font-semibold">{{ roleLabel }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Gestion</p>
                            <p class="mt-1 text-xs text-gray-500 font-medium">{{ academico?.inscripcion?.gestion || 'Sin inscripcion vigente' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm print:border-0 print:shadow-none">
                    <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-red-500">Evaluacion CUP</p>
                            <h3 class="mt-1 text-xl font-bold text-blue-950">Mi Proximo Examen</h3>
                            <p class="mt-2 text-sm text-gray-500">{{ examenCup.motivo }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 print:hidden">
                            <button
                                type="button"
                                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800 transition hover:bg-blue-100"
                                @click="imprimirBoletaNormal"
                            >
                                Boleta Normal
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100"
                                @click="imprimirBoletaHorario"
                            >
                                Boleta Horario
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-4">
                        <div class="rounded-xl border p-4" :class="estaHabilitadoExamen ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'">
                            <p class="text-xs font-bold uppercase text-gray-500">Estado</p>
                            <p class="mt-1 text-lg font-bold" :class="estaHabilitadoExamen ? 'text-emerald-700' : 'text-red-700'">
                                {{ estadoExamenLabel }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Siguiente examen</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ examenCup.siguiente_examen?.nombre || 'No asignado' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Fecha y hora</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ examenCup.siguiente_examen?.fecha || 'Por publicar' }}
                                <span v-if="examenCup.siguiente_examen?.hora"> - {{ examenCup.siguiente_examen.hora }}</span>
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Aula</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ examenCup.siguiente_examen?.aula || academico?.grupo?.aula || 'Por publicar' }}</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Materia CUP</th>
                                    <th class="px-4 py-3">Preguntas</th>
                                    <th class="px-4 py-3">Examen 1</th>
                                    <th class="px-4 py-3">Examen 2</th>
                                    <th class="px-4 py-3">Examen 3</th>
                                    <th class="px-4 py-3">Horario / Aula</th>
                                    <th class="px-4 py-3">Habilitacion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="materia in materiasCup" :key="materia.materia">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ materia.materia }}</td>
                                    <td class="px-4 py-3">{{ materia.preguntas }}</td>
                                    <td class="px-4 py-3">{{ formatoNota(materia.examen_1) }}</td>
                                    <td class="px-4 py-3">{{ formatoNota(materia.examen_2) }}</td>
                                    <td class="px-4 py-3">{{ formatoNota(materia.examen_3) }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ formatoHorario(materia) }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-bold"
                                            :class="materia.habilitacion === 'no_habilitado' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                        >
                                            {{ materia.habilitacion === 'no_habilitado' ? 'No habilitado' : 'Habilitado' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                        Cada examen CUP evalua 40 preguntas: 10 de Matematica, 10 de Computacion, 10 de Ingles y 10 de Fisica. Si una materia queda reprobada, el sistema bloquea automaticamente los siguientes examenes.
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-blue-950 mb-4">Mis Notas y Evaluaciones</h3>
                    <div v-if="cargandoAcademico" class="text-sm text-gray-500 py-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        Cargando informacion academica...
                    </div>
                    <div v-else-if="!materiasCup.length" class="text-sm text-gray-500 py-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        Las notas se cargaran una vez que se completen las evaluaciones.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Materia</th>
                                    <th class="px-4 py-3">Examen 1</th>
                                    <th class="px-4 py-3">Examen 2</th>
                                    <th class="px-4 py-3">Examen 3</th>
                                    <th class="px-4 py-3">Promedio</th>
                                    <th class="px-4 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="nota in materiasCup" :key="nota.materia">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ nota.materia }}</td>
                                    <td class="px-4 py-3">{{ formatoNota(nota.examen_1) }}</td>
                                    <td class="px-4 py-3">{{ formatoNota(nota.examen_2) }}</td>
                                    <td class="px-4 py-3">{{ formatoNota(nota.examen_3) }}</td>
                                    <td class="px-4 py-3">{{ formatoNota(nota.promedio) }}</td>
                                    <td class="px-4 py-3">{{ nota.estado }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="academico?.inscripcion?.estado !== 'reprobado'" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-blue-950 mb-4">Mi Grupo, Aula y Carrera</h3>
                    <div v-if="!academico?.grupo" class="text-sm text-gray-500 py-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        La asignacion de grupos y horarios se publicara al inicio del curso.
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Grupo</p>
                            <p class="mt-1 text-sm text-gray-600">{{ academico.grupo.codigo }} {{ academico.grupo.nombre || '' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Aula</p>
                            <p class="mt-1 text-sm text-gray-600">{{ academico.grupo.aula || 'Sin aula' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Carrera</p>
                            <p class="mt-1 text-sm text-gray-600">{{ academico.asignacion_carrera?.carrera || 'Pendiente' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm mt-6">
                    <h3 class="text-lg font-bold text-blue-950 mb-4">Repostular a Nueva Gestion</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Si participaste en gestiones anteriores y deseas volver a postular en una gestion actual, puedes hacerlo aqui manteniendo tu historial intacto.
                    </p>

                    <div v-if="cargandoRepostulacion" class="text-sm text-gray-500 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        Verificando gestiones abiertas...
                    </div>
                    <div v-else-if="gestionesAbiertas.length === 0" class="text-sm text-yellow-600 bg-yellow-50 p-4 rounded-xl border border-yellow-200">
                        No hay gestiones de admision abiertas en este momento.
                    </div>
                    <form v-else @submit.prevent="repostular" class="space-y-4">
                        <div v-if="mensajeExito" class="p-3 bg-green-50 text-green-700 rounded text-sm border border-green-200">{{ mensajeExito }}</div>
                        <div v-if="errorMensaje" class="p-3 bg-red-50 text-red-700 rounded text-sm border border-red-200">{{ errorMensaje }}</div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Seleccionar Gestion Abierta</label>
                            <select v-model="formRepostulacion.gestion_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="" disabled>Seleccione una gestion...</option>
                                <option v-for="gestion in gestionesAbiertas" :key="gestion.id" :value="gestion.id">
                                    {{ gestion.nombre }}
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">1ra Opcion de Carrera</label>
                                <select v-model="formRepostulacion.opcion1_carrera_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="" disabled>Seleccione una carrera...</option>
                                    <option v-for="carrera in carreras" :key="carrera.id" :value="carrera.id">
                                        {{ carrera.nombre }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">2da Opcion de Carrera</label>
                                <select v-model="formRepostulacion.opcion2_carrera_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="" disabled>Seleccione una carrera...</option>
                                    <option v-for="carrera in carreras" :key="carrera.id" :value="carrera.id">
                                        {{ carrera.nombre }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-blue-700 transition">
                                Confirmar Repostulacion
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
