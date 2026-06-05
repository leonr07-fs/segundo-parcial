<script setup>
// [CU18] Dashboard administrativo - Panel principal de indicadores rápidos del proceso CUP

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

const isAdmin = computed(() => props.user.role === 'admin');
const resumen = ref(null);
const cargandoResumen = ref(false);

onMounted(async () => {
    if (!isAdmin.value) {
        return;
    }

    cargandoResumen.value = true;
    try {
        const { data } = await axios.get('/api/admin/dashboard');
        if (data.ok) {
            resumen.value = data.data;
        }
    } catch (error) {
        console.error('Error cargando resumen administrativo', error);
    } finally {
        cargandoResumen.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 w-full">
        <!-- HEADER AZUL MARINO -->
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
                    Cerrar sesión
                </button>
            </div>
        </header>

        <div class="mx-auto grid max-w-6xl gap-6 px-6 py-8 lg:grid-cols-[0.8fr_1.2fr]">
            <!-- SIDEBAR PERFIL -->
            <aside class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="h-16 w-16 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-2xl font-bold mb-4">
                    {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <p class="text-xs font-bold text-red-500 uppercase tracking-wide">Usuario autenticado</p>
                <h2 class="mt-1 text-2xl font-bold text-blue-950">{{ user.name }}</h2>
                <dl class="mt-6 space-y-4 text-sm border-t border-gray-100 pt-6">
                    <div class="flex justify-between gap-4 items-center">
                        <dt class="text-gray-500 flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            Correo
                        </dt>
                        <dd class="font-medium text-gray-900">{{ user.email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 items-center">
                        <dt class="text-gray-500 flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                            Rol
                        </dt>
                        <dd class="font-semibold text-blue-700 bg-blue-50 px-2 py-1 rounded-md">{{ roleLabel }}</dd>
                    </div>
                </dl>
                <ChangePasswordPanel />
            </aside>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-blue-950 flex items-center gap-2">
                        <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Acceso Habilitado
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-gray-500">
                        La sesión fue creada correctamente y el sistema ya conoce el alcance de tus permisos.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Estado</p>
                            <p class="mt-1 text-xs text-green-600 font-semibold flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span> Activo
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Nivel de Acceso</p>
                            <p class="mt-1 text-xs text-blue-600 font-semibold">{{ roleLabel }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-900">Módulo Base</p>
                            <p class="mt-1 text-xs text-gray-500 font-medium">Gestión CUP</p>
                        </div>
                    </div>
                </div>

                <!-- Módulo CU05 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-blue-900">Resumen del Proceso CUP</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ resumen?.gestion_activa ? `Gestion activa: ${resumen.gestion_activa.nombre}` : 'Sin gestion activa para inscripcion' }}
                            </p>
                        </div>
                        <span v-if="cargandoResumen" class="text-xs font-semibold text-gray-400">Cargando...</span>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-5">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Postulantes</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ resumen?.resumen?.postulantes ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Inscripciones</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ resumen?.resumen?.inscripciones ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Evaluaciones</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ resumen?.resumen?.evaluaciones ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Pendientes</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ resumen?.resumen?.evaluaciones_pendientes ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Carreras</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ resumen?.resumen?.asignaciones_carrera ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <section v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-lg font-bold text-blue-950">Inscripcion y Pagos</h2>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Gestion de Postulantes</h3>
                                    <p class="text-sm text-slate-500">Busca, consulta expedientes y actualiza datos de postulantes (CU05).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/postulantes')" class="rounded-lg bg-purple-50 px-4 py-2 text-sm font-bold text-purple-700 transition hover:bg-purple-600 hover:text-white">Ver Postulantes</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Validacion Documental</h3>
                                    <p class="text-sm text-slate-500">Gestiona los requisitos documentales de los postulantes (CU03).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/validacion-documental')" class="rounded-lg bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white">Gestionar</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Pagos CUP</h3>
                                    <p class="text-sm text-slate-500">Verifica y registra pagos para confirmar inscripciones (CU04).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/pagos')" class="rounded-lg bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-600 hover:text-white">Gestionar Pagos</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Solicitudes Docentes</h3>
                                    <p class="text-sm text-slate-500">Revisa postulaciones docentes, documentos y credenciales de acceso.</p>
                                </div>
                                <button @click="emit('navigate', '/admin/solicitudes-docentes')" class="rounded-lg bg-cyan-50 px-4 py-2 text-sm font-bold text-cyan-700 transition hover:bg-cyan-600 hover:text-white">Revisar</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-lg font-bold text-blue-950">Gestion Academica</h2>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Parametros Academicos</h3>
                                    <p class="text-sm text-slate-500">Configura materias, aulas y grupos para la gestion (CU08).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/parametros')" class="rounded-lg bg-teal-50 px-4 py-2 text-sm font-bold text-teal-700 transition hover:bg-teal-600 hover:text-white">Configurar</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Asignar Carreras</h3>
                                    <p class="text-sm text-slate-500">Distribuir cupos por orden de merito (CU12).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/asignaciones-carrera')" class="rounded-lg bg-red-50 px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-600 hover:text-white">Gestionar Cupos</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-lg font-bold text-blue-950">Evaluaciones y Resultados</h2>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Evaluaciones Masivas</h3>
                                    <p class="text-sm text-slate-500">Importar resultados academicos desde Excel (CU09).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/evaluaciones/importar')" class="rounded-lg bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700 transition hover:bg-indigo-600 hover:text-white">Subir Notas</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Consultar Notas de Evaluacion</h3>
                                    <p class="text-sm text-slate-500">Busca y visualiza las notas registradas por grupo y materia (CU14).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/notas')" class="rounded-lg bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white">Ver Notas</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Validaciones Academicas</h3>
                                    <p class="text-sm text-slate-500">Supervisar evaluaciones incompletas u observadas (CU10).</p>
                                </div>
                                <button @click="emit('navigate', '/admin/validaciones-academicas')" class="rounded-lg bg-orange-50 px-4 py-2 text-sm font-bold text-orange-700 transition hover:bg-orange-600 hover:text-white">Supervisar</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-lg font-bold text-blue-950">Reportes y Auditoria</h2>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Reportes y Exportaciones</h3>
                                    <p class="text-sm text-slate-500">Genera reportes oficiales y dinamicos por gestion, fechas y columnas.</p>
                                </div>
                                <button @click="emit('navigate', '/admin/reportes')" class="rounded-lg bg-cyan-50 px-4 py-2 text-sm font-bold text-cyan-700 transition hover:bg-cyan-600 hover:text-white">Generar Reportes</button>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-blue-900">Bitacora Auditora</h3>
                                    <p class="text-sm text-slate-500">Ver registro detallado de movimientos del sistema.</p>
                                </div>
                                <button @click="emit('navigate', '/admin/bitacora')" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-700">Ver Historial</button>
                            </div>
                        </div>
                    </div>
                </section>

                <template v-if="false">
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-cyan-600">Reportes y Auditoria</p>
                            <h2 class="text-lg font-bold text-blue-900">Reportes y Exportaciones</h2>
                            <p class="text-sm text-gray-500 mt-1">Genera reportes oficiales y dinamicos por gestion, fechas y columnas.</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/reportes')"
                            class="rounded-xl bg-cyan-50 px-5 py-2.5 text-sm font-bold text-cyan-700 transition hover:bg-cyan-600 hover:text-white shrink-0"
                        >
                            Generar Reportes
                        </button>
                    </div>
                </div>

                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-purple-600">Inscripcion y Pagos</p>
                            <h2 class="text-lg font-bold text-blue-900">Gestión de Postulantes</h2>
                            <p class="text-sm text-gray-500 mt-1">Busca, consulta expedientes y actualiza datos de postulantes (CU05).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/postulantes')"
                            class="rounded-xl bg-purple-50 px-5 py-2.5 text-sm font-bold text-purple-700 transition hover:bg-purple-600 hover:text-white shrink-0"
                        >
                            Ver Postulantes
                        </button>
                    </div>
                </div>

                <!-- Módulo CU08 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-teal-600">Gestion Academica</p>
                            <h2 class="text-lg font-bold text-blue-900">Parámetros Académicos</h2>
                            <p class="text-sm text-gray-500 mt-1">Configura materias, aulas y grupos para la gestión (CU08).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/parametros')"
                            class="rounded-xl bg-teal-50 px-5 py-2.5 text-sm font-bold text-teal-700 transition hover:bg-teal-600 hover:text-white shrink-0"
                        >
                            Configurar
                        </button>
                    </div>
                </div>

                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-cyan-600">Inscripcion y Pagos</p>
                            <h2 class="text-lg font-bold text-blue-900">Solicitudes Docentes</h2>
                            <p class="max-w-xl text-sm text-gray-500 mt-1">Revisa postulaciones docentes, documentos y credenciales de acceso.</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/solicitudes-docentes')"
                            class="rounded-xl bg-cyan-50 px-5 py-2.5 text-sm font-bold text-cyan-700 transition hover:bg-cyan-600 hover:text-white shrink-0"
                        >
                            Revisar
                        </button>
                    </div>
                </div>

                <!-- Módulo CU14 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Evaluaciones y Resultados</p>
                            <h2 class="text-lg font-bold text-blue-900">Consultar Notas de Evaluación</h2>
                            <p class="text-sm text-gray-500 mt-1">Busca y visualiza las notas registradas por grupo y materia (CU14).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/notas')"
                            class="rounded-xl bg-blue-50 px-5 py-2.5 text-sm font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white shrink-0"
                        >
                            Ver Notas
                        </button>
                    </div>
                </div>

                <!-- Módulo CU03 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Inscripcion y Pagos</p>
                            <h2 class="text-lg font-bold text-blue-900">Validación Documental</h2>
                            <p class="text-sm text-gray-500 mt-1">Gestiona los requisitos documentales de los postulantes (CU03).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/validacion-documental')"
                            class="rounded-xl bg-blue-50 px-5 py-2.5 text-sm font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white shrink-0"
                        >
                            Gestionar
                        </button>
                    </div>
                </div>

                <!-- Módulo CU04 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Inscripcion y Pagos</p>
                            <h2 class="text-lg font-bold text-blue-900">Pagos CUP</h2>
                            <p class="text-sm text-gray-500 mt-1">Verifica y registra pagos para confirmar inscripciones (CU04).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/pagos')"
                            class="rounded-xl bg-emerald-50 px-5 py-2.5 text-sm font-bold text-emerald-700 transition hover:bg-emerald-600 hover:text-white shrink-0"
                        >
                            Gestionar Pagos
                        </button>
                    </div>
                </div>

                <!-- Módulo CU09 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">Evaluaciones y Resultados</p>
                            <h2 class="text-lg font-bold text-blue-900">Evaluaciones (Masivo)</h2>
                            <p class="text-sm text-gray-500 mt-1">Importar resultados académicos desde Excel (CU09).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/evaluaciones/importar')"
                            class="rounded-xl bg-indigo-50 px-5 py-2.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-600 hover:text-white shrink-0"
                        >
                            Subir Notas
                        </button>
                    </div>
                </div>

                <!-- Módulo CU10 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-orange-600">Evaluaciones y Resultados</p>
                            <h2 class="text-lg font-bold text-blue-900">Validaciones Académicas</h2>
                            <p class="text-sm text-gray-500 mt-1">Supervisar evaluaciones incompletas u observadas (CU10).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/validaciones-academicas')"
                            class="rounded-xl bg-orange-50 px-5 py-2.5 text-sm font-bold text-orange-700 transition hover:bg-orange-600 hover:text-white shrink-0"
                        >
                            Supervisar
                        </button>
                    </div>
                </div>

                <!-- Módulo CU12 (Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-red-600">Gestion Academica</p>
                            <h2 class="text-lg font-bold text-blue-900">Asignar Carreras</h2>
                            <p class="text-sm text-gray-500 mt-1">Distribuir cupos por orden de mérito (CU12).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/asignaciones-carrera')"
                            class="rounded-xl bg-red-50 px-5 py-2.5 text-sm font-bold text-red-700 transition hover:bg-red-600 hover:text-white shrink-0"
                        >
                            Gestionar Cupos
                        </button>
                    </div>
                </div>

                <!-- Bitácora Auditora (Solo Admin) -->
                <div v-if="isAdmin" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Reportes y Auditoria</p>
                            <h2 class="text-lg font-bold text-blue-900 flex items-center gap-2">
                                <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Bitácora Auditora
                            </h2>
                            <p class="text-sm text-gray-500 mt-1">Ver registro detallado de movimientos del sistema.</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/bitacora')"
                            class="rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700 shrink-0"
                        >
                            Ver Historial
                        </button>
                    </div>
                </div>
                </template>
            </div>
        </div>
    </div>
</template>
