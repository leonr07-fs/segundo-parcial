<script setup>
/**
 * Flujo público de repostulación para estudiantes reprobados.
 * Solicita CI + correo, valida elegibilidad y habilita pago PayPal.
 */
import { computed, ref } from 'vue';
import PayPalCheckout from '../../components/PayPalCheckout.vue';
import { validarRepostulacion, prepararRepostulacion } from '../../api/repostulacion';

const emit = defineEmits(['back']);

const form = ref({ ci: '', correo: '' });
const paso = ref('formulario');
const cargando = ref(false);
const error = ref('');
const datosValidados = ref(null);
const inscripcionPendiente = ref(null);

const puedePagar = computed(() => paso.value === 'pago' && inscripcionPendiente.value !== null);

async function validar() {
    if (!form.value.ci.trim() || !form.value.correo.trim()) return;

    cargando.value = true;
    error.value = '';
    datosValidados.value = null;
    inscripcionPendiente.value = null;

    try {
        const resultado = await validarRepostulacion({
            ci: form.value.ci.trim(),
            correo: form.value.correo.trim(),
        });

        if (!resultado.ok) {
            error.value = resultado.message || 'No es posible continuar con la repostulación.';
            return;
        }

        datosValidados.value = resultado.data;

        if (resultado.data.inscripcion_pendiente) {
            inscripcionPendiente.value = resultado.data.inscripcion_pendiente;
            paso.value = 'pago';
            return;
        }

        const preparacion = await prepararRepostulacion({
            ci: form.value.ci.trim(),
            correo: form.value.correo.trim(),
        });

        if (!preparacion.ok) {
            error.value = preparacion.message || 'No se pudo preparar la repostulación.';
            return;
        }

        inscripcionPendiente.value = preparacion.data.inscripcion;
        paso.value = 'pago';
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al validar los datos. Intente nuevamente.';
    } finally {
        cargando.value = false;
    }
}

function reiniciar() {
    paso.value = 'formulario';
    error.value = '';
    datosValidados.value = null;
    inscripcionPendiente.value = null;
    form.value = { ci: '', correo: '' };
}
</script>

<template>
    <div class="mx-auto max-w-2xl py-10">
        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50">
                    <svg class="h-7 w-7 text-indigo-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-slate-900">Repostulación CUP</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Proceso exclusivo para estudiantes reprobados que desean participar en la gestión vigente.
                </p>
            </div>

            <div v-if="error" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ error }}
            </div>

            <form v-if="paso === 'formulario'" class="space-y-5" @submit.prevent="validar">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Cédula de Identidad (CI)</span>
                    <input
                        v-model="form.ci"
                        type="text"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10"
                        placeholder="Ej. 12345678"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Correo electrónico registrado</span>
                    <input
                        v-model="form.correo"
                        type="email"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10"
                        placeholder="correo@gmail.com"
                    >
                </label>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-indigo-700 px-4 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-800 disabled:opacity-70"
                    :disabled="cargando"
                >
                    {{ cargando ? 'Validando...' : 'Continuar' }}
                </button>
            </form>

            <div v-else-if="paso === 'pago'" class="space-y-5">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    <p class="font-semibold">Validación exitosa</p>
                    <p class="mt-1">
                        {{ datosValidados?.postulante?.nombres }} {{ datosValidados?.postulante?.apellido_paterno }}
                        puede repostular en la gestión {{ datosValidados?.gestion?.nombre }}.
                    </p>
                    <p v-if="inscripcionPendiente?.codigo" class="mt-2 text-xs">
                        Código de inscripción: <strong>{{ inscripcionPendiente.codigo }}</strong>
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-900">Monto de repostulación</p>
                        <p class="text-2xl font-bold text-indigo-700">Bs. 200.00</p>
                    </div>

                    <PayPalCheckout
                        v-if="puedePagar"
                        mode="repostulacion"
                        :ci="form.ci"
                        :correo="form.correo"
                    />
                </div>

                <button
                    type="button"
                    class="text-sm font-semibold text-slate-500 hover:text-slate-700"
                    @click="reiniciar"
                >
                    Volver al formulario
                </button>
            </div>
        </div>
    </div>
</template>
