<script setup>
// [CU03] Validar requisitos documentales - Bandeja de expedientes pendientes para cajero

import { ref, onMounted } from 'vue';
import { fetchInscripcionesPendientes } from '../../api/validacion-documental';

const emit = defineEmits(['navigate']);

const loading = ref(true);
const inscripciones = ref([]);
const serverMessage = ref('');
const estadoFiltro = ref('pendientes');

async function cargarBandeja() {
    loading.value = true;
    serverMessage.value = '';
    try {
        const payload = await fetchInscripcionesPendientes(estadoFiltro.value);
        inscripciones.value = payload.data.inscripciones;
    } catch (error) {
        serverMessage.value = 'Error al cargar la lista de inscripciones.';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await cargarBandeja();
});

function verDetalle(id) {
    emit('navigate', `/admin/validacion-documental/${id}`);
}

function resumenPrevalidacion(inscripcion) {
    const documentos = inscripcion.documentos ?? [];

    if (!documentos.length) {
        return { texto: 'Sin documentos', clase: 'bg-slate-100 text-slate-700' };
    }

    const criticos = documentos.filter(doc => doc.prevalidacion_estado === 'critico').length;
    const observados = documentos.filter(doc => doc.prevalidacion_estado === 'observado').length;

    if (criticos > 0) {
        return { texto: `${criticos} critico(s)`, clase: 'bg-red-100 text-red-700' };
    }

    if (observados > 0) {
        return { texto: `${observados} observado(s)`, clase: 'bg-amber-100 text-amber-800' };
    }

    return { texto: 'Prevalidado OK', clase: 'bg-emerald-100 text-emerald-700' };
}
</script>

<template>
    <div>
        <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Validacion Documental</h1>
                <p class="mt-1 text-sm text-slate-500">Gestion y revision de requisitos documentales de los postulantes.</p>
            </div>
            <div class="flex items-center gap-3">
                <select v-model="estadoFiltro" class="rounded-md border border-slate-300 px-3 py-2 text-sm bg-white" @change="cargarBandeja">
                    <option value="pendientes">Pendientes</option>
                    <option value="aprobados">Aprobados</option>
                    <option value="rechazados">Rechazados</option>
                    <option value="todos">Todos</option>
                </select>
                <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800" @click="cargarBandeja">
                    Actualizar
                </button>
            </div>
        </header>

        <div v-if="loading" class="flex items-center justify-center py-12">
            <svg class="h-6 w-6 animate-spin text-cyan-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="ml-3 text-sm text-slate-500">Cargando bandeja de pendientes...</span>
        </div>

        <div v-else-if="serverMessage" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {{ serverMessage }}
        </div>

        <div v-else-if="inscripciones.length === 0" class="rounded-lg border border-slate-200 bg-white py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-4 text-sm font-medium text-slate-900">Bandeja vacia</h3>
            <p class="mt-1 text-sm text-slate-500">No hay inscripciones pendientes de validacion documental en este momento.</p>
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 font-semibold text-slate-900">Codigo CUP</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">Postulante</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">CI</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">Gestion</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">Prevalidacion</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">Estado Actual</th>
                        <th class="px-6 py-3 text-right font-semibold text-slate-900">Accion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="ins in inscripciones" :key="ins.id" class="transition hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 font-medium text-cyan-700">{{ ins.codigo }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-slate-700">
                            {{ ins.postulante.apellido_paterno }} {{ ins.postulante.apellido_materno }} {{ ins.postulante.nombres }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-slate-700">{{ ins.postulante.ci }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-slate-700">{{ ins.gestion.nombre }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="resumenPrevalidacion(ins).clase">
                                {{ resumenPrevalidacion(ins).texto }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span v-if="ins.estado === 'prepostulado'" class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                                Pre-postulado
                            </span>
                            <span v-else-if="ins.estado === 'documentos_pendientes'" class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                Docs. Observados
                            </span>
                            <span v-else-if="['documentos_aprobados', 'pagado', 'inscrito', 'en_curso', 'finalizado'].includes(ins.estado)" class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                                Aprobado
                            </span>
                            <span v-else-if="ins.estado === 'documentos_rechazados'" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                Rechazado
                            </span>
                            <span v-else class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                                {{ ins.estado }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <button
                                @click="verDetalle(ins.id)"
                                class="inline-flex items-center text-sm font-semibold text-cyan-600 transition hover:text-cyan-800"
                            >
                                {{ ['prepostulado', 'documentos_pendientes'].includes(ins.estado) ? 'Validar' : 'Ver' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
