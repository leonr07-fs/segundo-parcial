<script setup>
import { computed } from 'vue';

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

                <!-- Módulo CU03 (Solo Admin para gestionar, Autoridad/Coordinador para ver) -->
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-blue-900">Validación Documental</h2>
                            <p class="text-sm text-gray-500 mt-1">Gestiona los requisitos documentales de los postulantes (CU03).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/validacion-documental')"
                            class="rounded-xl bg-blue-50 px-5 py-2.5 text-sm font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white shrink-0"
                        >
                            {{ isAdmin ? 'Gestionar' : 'Ver Lista' }}
                        </button>
                    </div>
                </div>

                <!-- Módulo CU04 (Solo Admin para gestionar, Autoridad/Coordinador para ver) -->
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-blue-900">Pagos CUP</h2>
                            <p class="text-sm text-gray-500 mt-1">Verifica y registra pagos para confirmar inscripciones (CU04).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/pagos')"
                            class="rounded-xl bg-emerald-50 px-5 py-2.5 text-sm font-bold text-emerald-700 transition hover:bg-emerald-600 hover:text-white shrink-0"
                        >
                            {{ isAdmin ? 'Gestionar Pagos' : 'Ver Pagos' }}
                        </button>
                    </div>
                </div>

                <!-- Módulo CU09 (Solo Admin para subir notas) -->
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-blue-900">Evaluaciones (Masivo)</h2>
                            <p class="text-sm text-gray-500 mt-1">Importar resultados académicos desde Excel (CU09).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/evaluaciones/importar')"
                            class="rounded-xl bg-indigo-50 px-5 py-2.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-600 hover:text-white shrink-0"
                            :disabled="!isAdmin"
                            :class="!isAdmin ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            Subir Notas
                        </button>
                    </div>
                </div>

                <!-- Módulo CU10 (Admin, Autoridad, Coordinador) -->
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
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

                <!-- Módulo CU12 (Solo Admin para ejecutar, Autoridad/Coordinador para ver) -->
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-blue-900">Asignar Carreras</h2>
                            <p class="text-sm text-gray-500 mt-1">Distribuir cupos por orden de mérito (CU12).</p>
                        </div>
                        <button
                            @click="emit('navigate', '/admin/asignaciones-carrera')"
                            class="rounded-xl bg-red-50 px-5 py-2.5 text-sm font-bold text-red-700 transition hover:bg-red-600 hover:text-white shrink-0"
                        >
                            {{ isAdmin ? 'Gestionar Cupos' : 'Ver Asignaciones' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
