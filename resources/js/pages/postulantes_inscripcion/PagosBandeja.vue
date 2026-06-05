<script setup>
// [CU04] Registrar/verificar pago - Listado de preinscritos listos para cobro

import { ref, onMounted } from 'vue';
import { fetchInscripcionesPendientesPago } from '../../api/pagos';

const emit = defineEmits(['navigate']);

const loading = ref(true);
const inscripciones = ref([]);
const serverMessage = ref('');

onMounted(async () => {
    try {
        const payload = await fetchInscripcionesPendientesPago();
        inscripciones.value = payload.data.inscripciones;
    } catch (error) {
        serverMessage.value = 'Error al cargar la lista de postulantes habilitados para pago.';
    } finally {
        loading.value = false;
    }
});

function verDetalle(id) {
    emit('navigate', `/admin/pagos/${id}`);
}
</script>

<template>
    <div>
        <header class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Verificar y Registrar Pagos</h1>
                <p class="mt-1 text-sm text-slate-500">Gestión de pagos CUP para postulantes con documentos aprobados.</p>
            </div>
        </header>

        <div v-if="loading" class="flex items-center justify-center py-12">
            <svg class="h-6 w-6 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="ml-3 text-sm text-slate-500">Cargando postulantes...</span>
        </div>

        <div v-else-if="serverMessage" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {{ serverMessage }}
        </div>

        <div v-else-if="inscripciones.length === 0" class="rounded-lg border border-slate-200 bg-white py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-sm font-medium text-slate-900">Sin pagos pendientes</h3>
            <p class="mt-1 text-sm text-slate-500">No hay postulantes listos para pago en este momento.</p>
        </div>

        <div v-else class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 font-semibold text-slate-900">Código CUP</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">Postulante</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">CI</th>
                        <th class="px-6 py-3 font-semibold text-slate-900">Gestión</th>
                        <th class="px-6 py-3 text-right font-semibold text-slate-900">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="ins in inscripciones" :key="ins.id" class="transition hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 font-medium text-emerald-700">{{ ins.codigo }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-slate-700">
                            {{ ins.postulante.apellido_paterno }} {{ ins.postulante.apellido_materno }} {{ ins.postulante.nombres }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-slate-700">{{ ins.postulante.ci }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-slate-700">{{ ins.gestion.nombre }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <button
                                @click="verDetalle(ins.id)"
                                class="inline-flex items-center rounded bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                            >
                                Registrar Pago
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
