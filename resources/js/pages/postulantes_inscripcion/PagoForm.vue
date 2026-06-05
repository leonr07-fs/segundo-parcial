<script setup>
// [CU04] Registrar/verificar pago - Formulario de cobro y registro de referencia de pago

import { ref } from 'vue';
import { registrarPago } from '../../api/pagos';

const props = defineProps({
    inscripcionId: {
        type: [String, Number],
        required: true
    }
});

const emit = defineEmits(['back']);

const submitting = ref(false);
const serverMessage = ref('');
const fieldErrors = ref({});
const success = ref(false);

const reciboDetails = ref(null);
const credenciales = ref(null);

const form = ref({
    monto: '300.00', // Arancel por defecto
    metodo: 'Transferencia Bancaria',
    referencia: '',
});

async function handleSubmit() {
    submitting.value = true;
    serverMessage.value = '';
    fieldErrors.value = {};

    try {
        const payload = await registrarPago(props.inscripcionId, form.value);
        reciboDetails.value = payload.data.recibo;
        credenciales.value = payload.data.credenciales;
        success.value = true;
    } catch (error) {
        if (error.response?.status === 422) {
            if (error.response.data.errors) {
                fieldErrors.value = error.response.data.errors;
            } else {
                serverMessage.value = error.response.data.message; // DomainException (Monto, etc)
            }
        } else {
            serverMessage.value = error.response?.data?.message ?? 'Error al registrar el pago.';
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <div v-if="success" class="rounded-lg border border-emerald-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="mt-4 text-xl font-bold text-slate-900">Pago Verificado y Registrado</h2>
            <p class="mt-2 text-sm text-slate-500">El pago ha sido procesado exitosamente y la inscripción está confirmada.</p>
            
            <div class="mt-6 rounded-lg bg-slate-50 p-4 text-left border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-900">Detalles del Comprobante</h3>
                <dl class="mt-3 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">No. Recibo</dt>
                        <dd class="font-semibold text-emerald-700">{{ reciboDetails.numero }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Monto</dt>
                        <dd class="font-medium text-slate-900">{{ form.monto }} BOB</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Referencia</dt>
                        <dd class="font-medium text-slate-900">{{ form.referencia }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Mostrar Credenciales del Estudiante -->
            <div v-if="credenciales" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50/50 p-5 text-left shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Credenciales del Estudiante Generadas</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">Número de Registro</dt>
                        <dd class="mt-1 rounded border border-slate-200 bg-white px-3 py-2 font-mono text-lg font-bold text-slate-900">{{ credenciales.numero_registro }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Contraseña Temporal</dt>
                        <dd class="mt-1 rounded border border-slate-200 bg-white px-3 py-2 font-mono text-lg font-bold text-slate-900">{{ credenciales.password_temporal }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs" :class="credenciales.correo_enviado ? 'text-emerald-700' : 'text-amber-700'">
                    {{ credenciales.correo_enviado ? '✓ Las credenciales han sido enviadas al correo del postulante.' : '⚠ No se pudo enviar el correo automáticamente. Por favor comunique estas credenciales manualmente.' }}
                </p>
            </div>

            <button @click="$emit('back')" class="mt-6 w-full rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Volver a la bandeja
            </button>
        </div>

        <div v-else>
            <header class="mb-6">
                <button @click="$emit('back')" class="mb-4 flex items-center text-sm font-medium text-emerald-600 hover:text-emerald-700">
                    &larr; Volver a pendientes
                </button>
                <h1 class="text-2xl font-bold text-slate-900">Registrar Pago</h1>
                <p class="mt-1 text-sm text-slate-500">Verifica la transacción y emite el recibo CUP para confirmar la inscripción.</p>
            </header>

            <form @submit.prevent="handleSubmit" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div v-if="serverMessage" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {{ serverMessage }}
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <!-- Monto -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Monto (BOB) <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.monto"
                            type="number"
                            step="0.01"
                            required
                            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            :class="{'border-red-500': fieldErrors.monto}"
                        />
                        <p v-if="fieldErrors.monto" class="mt-1 text-xs text-red-600">{{ fieldErrors.monto[0] }}</p>
                        <p v-else class="mt-1 text-xs text-slate-500">Arancel oficial del CUP: 300.00 BOB.</p>
                    </div>

                    <!-- Método de Pago -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Método de Pago <span class="text-red-500">*</span></label>
                        <select
                            v-model="form.metodo"
                            required
                            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        >
                            <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                            <option value="Depósito en Ventanilla">Depósito en Ventanilla</option>
                            <option value="Caja Universidad">Caja de la Universidad</option>
                            <option value="Pago Online">Pago Online (Pasarela)</option>
                        </select>
                        <p v-if="fieldErrors.metodo" class="mt-1 text-xs text-red-600">{{ fieldErrors.metodo[0] }}</p>
                    </div>

                    <!-- Referencia -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Referencia o Transacción <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.referencia"
                            type="text"
                            required
                            placeholder="Ej: TX-987654321"
                            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 uppercase"
                            :class="{'border-red-500': fieldErrors.referencia}"
                        />
                        <p v-if="fieldErrors.referencia" class="mt-1 text-xs text-red-600">{{ fieldErrors.referencia[0] }}</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ submitting ? 'Procesando...' : 'Verificar y Emitir Recibo' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
