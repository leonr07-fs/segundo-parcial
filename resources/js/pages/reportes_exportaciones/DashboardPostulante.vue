<script setup>
// [CU17] Consultar información del postulante / [CU06] Repostular - Portal privado y boleta de inscripción (Sábados Virtuales)

import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import ChangePasswordPanel from '../../components/ChangePasswordPanel.vue';
import PayPalCheckout from '../../components/PayPalCheckout.vue';

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

const academico = ref(null);
const cargandoAcademico = ref(false);
const pagoPayPalExitoso = ref(false);

const estadoInscripcion = computed(() => academico.value?.inscripcion?.estado || '');
const mostrarPayPal = computed(() => estadoInscripcion.value === 'documentos_aprobados' && !pagoPayPalExitoso.value);
const estaPagado = computed(() => estadoInscripcion.value === 'pagado' || estadoInscripcion.value === 'inscrito' || pagoPayPalExitoso.value);
const estaEnRevision = computed(() => estadoInscripcion.value === 'prepostulado');
const tieneObservaciones = computed(() => estadoInscripcion.value === 'documentos_pendientes');
const validacionDocumental = computed(() => academico.value?.inscripcion?.validacion_documental || null);
const documentosInscritos = computed(() => academico.value?.inscripcion?.documentos || []);
const observacionesDocumentos = computed(() => {
    return documentosInscritos.value
        .filter(d => d.observacion && (d.estado === 'observado' || d.estado === 'rechazado'))
        .map(d => ({ tipo: d.tipo?.replace(/_/g, ' '), estado: d.estado, observacion: d.observacion }));
});
const estadoPostulacionLabel = computed(() => {
    const labels = {
        prepostulado: 'Postulación enviada — En revisión',
        documentos_pendientes: 'Documentación observada — Requiere corrección',
        documentos_aprobados: 'Documentación aprobada — Pendiente de pago',
        pagado: 'Pago confirmado',
        inscrito: 'Inscripción completada',
        reprobado: 'Gestión reprobada',
    };
    return labels[estadoInscripcion.value] || 'Sin postulación activa';
});

function handlePagoExitoso(result) {
    pagoPayPalExitoso.value = true;
    // Recargar datos académicos para reflejar el nuevo estado
    setTimeout(async () => {
        try {
            const { data: academicoData } = await axios.get('/api/postulante/academico');
            if (academicoData.ok) {
                academico.value = academicoData.data;
            }
        } catch (e) {
            console.error('Error recargando datos tras pago', e);
        }
    }, 2000);
}

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

});

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

                <!-- BLOQUE: Estado de Postulación y Pago PayPal -->
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Estado de Postulación</p>
                            <h3 class="mt-1 text-lg font-bold text-blue-950">{{ estadoPostulacionLabel }}</h3>
                        </div>
                        <span
                            class="mt-1 shrink-0 rounded-full px-3 py-1 text-xs font-bold"
                            :class="{
                                'bg-amber-100 text-amber-700': estaEnRevision,
                                'bg-blue-100 text-blue-700': mostrarPayPal,
                                'bg-emerald-100 text-emerald-700': estaPagado,
                                'bg-red-100 text-red-700': estadoInscripcion === 'reprobado',
                            }"
                        >
                            {{ estadoInscripcion || 'N/A' }}
                        </span>
                        <span v-if="tieneObservaciones"
                            class="mt-1 shrink-0 rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700"
                        >
                            observado
                        </span>
                    </div>

                    <!-- Sub-estado: Postulación enviada, esperando revisión -->
                    <div v-if="estaEnRevision" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                        <p class="font-semibold">📋 Postulación enviada — Validación de documentos pendiente</p>
                        <p class="mt-1">Tu documentación fue recibida y está siendo revisada por el equipo administrativo. Una vez aprobada, se habilitará la opción de pago.</p>
                    </div>

                    <!-- Sub-estado: Documentos observados / requieren corrección -->
                    <div v-else-if="tieneObservaciones" class="mt-5 space-y-3">
                        <div class="rounded-xl border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800">
                            <p class="font-semibold">⚠️ Documentación observada</p>
                            <p class="mt-1">El equipo administrativo ha revisado tu documentación y encontró observaciones. Revisa los detalles a continuación.</p>
                        </div>
                        <div v-if="validacionDocumental?.observacion" class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Observación general</p>
                            <p class="text-slate-700">{{ validacionDocumental.observacion }}</p>
                        </div>
                        <div v-if="observacionesDocumentos.length" class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Detalle por documento</p>
                            <ul class="space-y-2">
                                <li v-for="(doc, idx) in observacionesDocumentos" :key="idx" class="flex items-start gap-2">
                                    <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                        :class="doc.estado === 'rechazado' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'"
                                    >{{ doc.estado }}</span>
                                    <span class="text-slate-700"><strong class="capitalize">{{ doc.tipo }}:</strong> {{ doc.observacion }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Sub-estado: Documentos aprobados → Pagar con PayPal -->
                    <div v-else-if="mostrarPayPal" class="mt-5 space-y-4">
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
                            <p class="font-semibold">✅ Documentos aprobados</p>
                            <p class="mt-1">Tu documentación ha sido verificada. Realiza el pago de inscripción para completar tu registro CUP.</p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                            <div class="mb-4 flex items-center justify-between">
                                <p class="text-sm font-bold text-gray-900">Monto a pagar</p>
                                <p class="text-2xl font-bold text-blue-700">Bs. 200.00</p>
                            </div>
                            <PayPalCheckout @pago-exitoso="handlePagoExitoso" />
                        </div>
                    </div>

                    <!-- Sub-estado: Pagado / Inscrito -->
                    <div v-else-if="estaPagado" class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                            <svg class="h-7 w-7 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="text-lg font-bold text-emerald-800">Inscripción confirmada</p>
                        <p class="mt-1 text-sm text-emerald-600">Tu pago ha sido registrado exitosamente. Revisa la sección de exámenes para más información.</p>
                    </div>

                    <!-- Sub-estado: Reprobado -->
                    <div v-else-if="estadoInscripcion === 'reprobado' || academico?.resultado?.estado_final === 'reprobado'" class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">Gestión reprobada</p>
                        <p class="mt-1">Debe realizar una repostulación desde la página inicial pública del sistema.</p>
                    </div>

                    <!-- Sin postulación -->
                    <div v-else class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 text-center">
                        No tienes una postulación activa en este momento.
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

            </div>
        </div>
    </div>
</template>
