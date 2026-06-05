<script setup>
// [CU07] Auditoría / Bitácora - Visor de registros de auditoría de base de datos

import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const emit = defineEmits(['navigate']);

const logs = ref([]);
const resumen = ref(null);
const tablas = ref([]);
const acciones = ref([]);
const notas = ref(null);
const loading = ref(true);
const errorMensaje = ref('');
const searchQuery = ref('');

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/bitacora');
        if (data.ok) {
            logs.value = data.data.logs || [];
            resumen.value = data.data.resumen || {};
            tablas.value = data.data.tablas || [];
            acciones.value = data.data.acciones || [];
            notas.value = data.data.notas || null;
        }
    } catch (e) {
        errorMensaje.value = e.response?.data?.message || 'Error al cargar la bitacora.';
    } finally {
        loading.value = false;
    }
});

const filteredLogs = computed(() => {
    if (!searchQuery.value) return logs.value;
    const q = searchQuery.value.toLowerCase();

    return logs.value.filter(log => {
        return [
            log.accion,
            log.accion_legible,
            log.tabla,
            log.registro_id,
            log.usuario?.name,
            log.usuario?.role,
            JSON.stringify(log.detalles || {}),
        ].some(value => String(value || '').toLowerCase().includes(q));
    });
});

const notaBarras = computed(() => {
    const promedios = notas.value?.promedios || {};
    return [
        { label: 'Examen 1', value: Number(promedios.examen_1 || 0), color: 'bg-blue-600' },
        { label: 'Examen 2', value: Number(promedios.examen_2 || 0), color: 'bg-cyan-600' },
        { label: 'Examen 3', value: Number(promedios.examen_3 || 0), color: 'bg-indigo-600' },
        { label: 'Promedio', value: Number(promedios.general || 0), color: 'bg-emerald-600' },
    ];
});

const formatDate = (dateString) => {
    if (!dateString) return '-';

    return new Date(dateString).toLocaleString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const prettyJson = (value) => {
    if (!value || Object.keys(value).length === 0) return 'Sin detalles';
    return JSON.stringify(value, null, 2);
};

const badgeClass = (tabla) => {
    const color = {
        users: 'bg-slate-100 text-slate-800 border-slate-200',
        inscripciones: 'bg-blue-50 text-blue-800 border-blue-200',
        documentos: 'bg-amber-50 text-amber-800 border-amber-200',
        pagos: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        evaluaciones: 'bg-indigo-50 text-indigo-800 border-indigo-200',
        solicitudes_docentes: 'bg-cyan-50 text-cyan-800 border-cyan-200',
    }[tabla];

    return color || 'bg-gray-50 text-gray-700 border-gray-200';
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 w-full">
        <header class="bg-blue-950 text-white shadow-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-4">
                    <button
                        @click="emit('navigate', '/admin/dashboard')"
                        class="rounded-md p-2 transition hover:bg-blue-900"
                        title="Volver"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-cyan-200">Administracion CUP</p>
                        <h1 class="text-lg font-semibold">Bitacora Auditora</h1>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-8">
            <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                Cargando bitacora...
            </div>

            <div v-else-if="errorMensaje" class="rounded-lg border border-red-200 bg-red-50 p-10 text-center text-sm font-medium text-red-700">
                {{ errorMensaje }}
            </div>

            <div v-else class="space-y-6">
                <section class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Movimientos</p>
                        <p class="mt-2 text-3xl font-bold text-blue-950">{{ resumen?.total_movimientos || 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Usuarios activos</p>
                        <p class="mt-2 text-3xl font-bold text-blue-950">{{ resumen?.usuarios_activos || 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Tablas intervenidas</p>
                        <p class="mt-2 text-3xl font-bold text-blue-950">{{ resumen?.tablas_intervenidas || 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Ultima accion</p>
                        <p class="mt-2 truncate text-sm font-bold text-blue-950">{{ resumen?.ultima_accion?.accion_legible || 'Sin registros' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ formatDate(resumen?.ultima_accion?.fecha) }}</p>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[1fr_1fr]">
                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-lg font-bold text-blue-950">Tablas mas intervenidas</h2>
                            <p class="text-sm text-slate-500">Muestra que parte de la base de datos tuvo mas movimiento.</p>
                        </div>
                        <div class="space-y-4">
                            <div v-for="item in tablas" :key="item.nombre">
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="font-semibold text-slate-700">{{ item.nombre }}</span>
                                    <span class="text-slate-500">{{ item.total }} movimientos</span>
                                </div>
                                <div class="h-3 rounded-full bg-slate-100">
                                    <div class="h-3 rounded-full bg-cyan-600" :style="{ width: `${item.porcentaje}%` }"></div>
                                </div>
                            </div>
                            <p v-if="!tablas.length" class="text-sm text-slate-500">No hay tablas registradas.</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-lg font-bold text-blue-950">Comportamiento de notas</h2>
                            <p class="text-sm text-slate-500">Promedio de los examenes registrados en el sistema.</p>
                        </div>
                        <div class="space-y-4">
                            <div v-for="nota in notaBarras" :key="nota.label">
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="font-semibold text-slate-700">{{ nota.label }}</span>
                                    <span class="font-bold text-blue-950">{{ nota.value.toFixed(2) }}</span>
                                </div>
                                <div class="h-3 rounded-full bg-slate-100">
                                    <div class="h-3 rounded-full" :class="nota.color" :style="{ width: `${Math.min(100, nota.value)}%` }"></div>
                                </div>
                            </div>
                            <p class="pt-2 text-xs font-semibold uppercase text-slate-500">
                                Evaluaciones registradas: {{ notas?.total_evaluaciones || 0 }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-blue-950">Historial de movimientos</h2>
                                <p class="mt-1 text-sm text-slate-500">Cada registro indica accion, tabla intervenida, usuario, IP y detalles.</p>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por usuario, tabla, accion o detalle..."
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 lg:w-96"
                            >
                        </div>
                    </div>

                    <div v-if="filteredLogs.length === 0" class="p-10 text-center text-sm text-slate-500">
                        No se encontraron registros.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Fecha / Hora</th>
                                    <th class="px-6 py-4 font-semibold">Usuario</th>
                                    <th class="px-6 py-4 font-semibold">Accion</th>
                                    <th class="px-6 py-4 font-semibold">Tabla</th>
                                    <th class="px-6 py-4 font-semibold">Registro</th>
                                    <th class="px-6 py-4 font-semibold">IP / Agente</th>
                                    <th class="px-6 py-4 font-semibold">Detalles</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="log in filteredLogs" :key="log.id" class="align-top transition hover:bg-slate-50">
                                    <td class="px-6 py-4 font-medium text-blue-900">{{ formatDate(log.fecha) }}</td>
                                    <td class="px-6 py-4">
                                        <div v-if="log.usuario">
                                            <div class="font-bold text-slate-900">{{ log.usuario.name }}</div>
                                            <div class="text-xs uppercase text-slate-500">{{ log.usuario.role }}</div>
                                        </div>
                                        <span v-else class="text-slate-400">Sistema / Anonimo</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-900">{{ log.accion_legible }}</div>
                                        <div class="mt-1 font-mono text-xs text-slate-500">{{ log.accion }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="rounded border px-2.5 py-1 text-xs font-bold uppercase" :class="badgeClass(log.tabla)">
                                            {{ log.tabla || 'sin dato' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-slate-600">{{ log.registro_id || '-' }}</td>
                                    <td class="px-6 py-4 max-w-56 text-xs text-slate-500">
                                        <div class="font-mono font-semibold text-slate-700">{{ log.ip_address || '-' }}</div>
                                        <div class="mt-1 truncate" :title="log.user_agent">{{ log.user_agent || 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <pre class="max-h-28 max-w-sm overflow-auto rounded-md bg-slate-50 p-3 text-xs leading-5 text-slate-600">{{ prettyJson(log.detalles) }}</pre>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
