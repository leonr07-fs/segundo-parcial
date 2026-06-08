<script setup>
/**
 * Flujo público de repostulación docente.
 * Solicita CI + correo y registra solicitud pendiente de aprobación.
 */
import { ref } from 'vue';
import { registrarRepostulacionDocente } from '../../api/repostulacion-docente';

const form = ref({ ci: '', correo: '' });
const cargando = ref(false);
const error = ref('');
const exito = ref(null);

async function enviar() {
    if (!form.value.ci.trim() || !form.value.correo.trim()) return;

    cargando.value = true;
    error.value = '';
    exito.value = null;

    try {
        const resultado = await registrarRepostulacionDocente({
            ci: form.value.ci.trim(),
            correo: form.value.correo.trim(),
        });

        if (!resultado.ok) {
            error.value = resultado.message || 'No se pudo registrar la solicitud.';
            return;
        }

        exito.value = resultado;
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al procesar la solicitud.';
    } finally {
        cargando.value = false;
    }
}

function reiniciar() {
    form.value = { ci: '', correo: '' };
    error.value = '';
    exito.value = null;
}
</script>

<template>
    <div class="mx-auto max-w-2xl py-10">
        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-cyan-50">
                    <svg class="h-7 w-7 text-cyan-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-slate-900">Repostulación Docente</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Para docentes que participaron en gestiones anteriores y desean continuar en la gestión vigente.
                </p>
            </div>

            <div v-if="error" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ error }}
            </div>

            <div v-if="exito" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800">
                <p class="font-semibold">{{ exito.message }}</p>
                <p class="mt-2">
                    Docente: {{ exito.data?.docente?.nombres }} {{ exito.data?.docente?.apellidos || '' }}
                </p>
                <p class="mt-1">Gestión: {{ exito.data?.gestion?.nombre }}</p>
                <p class="mt-1">Estado: <strong>{{ exito.data?.repostulacion?.estado }}</strong></p>
                <button
                    type="button"
                    class="mt-4 text-sm font-semibold text-emerald-700 hover:underline"
                    @click="reiniciar"
                >
                    Registrar otra solicitud
                </button>
            </div>

            <form v-else class="space-y-5" @submit.prevent="enviar">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Cédula de Identidad (CI)</span>
                    <input
                        v-model="form.ci"
                        type="text"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-cyan-600 focus:ring-4 focus:ring-cyan-600/10"
                        placeholder="Ej. 12345678"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Correo electrónico registrado</span>
                    <input
                        v-model="form.correo"
                        type="email"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-cyan-600 focus:ring-4 focus:ring-cyan-600/10"
                        placeholder="correo@gmail.com"
                    >
                </label>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-cyan-700 px-4 py-3.5 text-sm font-bold text-white transition hover:bg-cyan-800 disabled:opacity-70"
                    :disabled="cargando"
                >
                    {{ cargando ? 'Registrando...' : 'Solicitar repostulación' }}
                </button>
            </form>
        </div>
    </div>
</template>
