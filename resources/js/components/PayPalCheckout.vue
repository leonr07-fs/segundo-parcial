<script setup>
/**
 * Componente PayPal Checkout reutilizable.
 * Soporta flujos autenticado, consulta pública y repostulación pública.
 */
import { onMounted, ref } from 'vue';
import {
    createPayPalOrder,
    capturePayPalOrder,
    createPublicPayPalOrder,
    capturePublicPayPalOrder,
} from '../api/pagos';
import {
    createRepostulacionPayPalOrder,
    captureRepostulacionPayPalOrder,
} from '../api/repostulacion';

const props = defineProps({
    mode: {
        type: String,
        default: 'auth',
        validator: (value) => ['auth', 'public', 'repostulacion'].includes(value),
    },
    ci: {
        type: String,
        default: '',
    },
    correo: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['pago-exitoso', 'pago-error']);

const paypalContainerId = `paypal-button-container-${Math.random().toString(36).slice(2, 9)}`;
const sdkLoaded = ref(false);
const sdkError = ref('');
const procesando = ref(false);
const pagoCompletado = ref(false);
const mensajeError = ref('');

let scriptTag = null;

async function crearOrden() {
    if (props.mode === 'repostulacion') {
        return createRepostulacionPayPalOrder({ ci: props.ci.trim(), correo: props.correo.trim() });
    }
    if (props.mode === 'public') {
        return createPublicPayPalOrder(props.ci.trim());
    }
    return createPayPalOrder();
}

async function capturarOrden(orderID) {
    if (props.mode === 'repostulacion') {
        return captureRepostulacionPayPalOrder({
            orderID,
            ci: props.ci.trim(),
            correo: props.correo.trim(),
        });
    }
    if (props.mode === 'public') {
        return capturePublicPayPalOrder(orderID);
    }
    return capturePayPalOrder(orderID);
}

function loadPayPalSdk() {
    const clientId = import.meta.env.VITE_PAYPAL_CLIENT_ID;

    if (!clientId) {
        sdkError.value = 'PayPal Client ID no configurado. Contacte al administrador.';
        return;
    }

    if (window.paypal) {
        sdkLoaded.value = true;
        renderButtons();
        return;
    }

    scriptTag = document.createElement('script');
    scriptTag.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=USD&locale=es_ES&disable-funding=card`;
    scriptTag.async = true;
    scriptTag.onload = () => {
        sdkLoaded.value = true;
        renderButtons();
    };
    scriptTag.onerror = () => {
        sdkError.value = 'No se pudo cargar el SDK de PayPal. Verifique su conexión a internet.';
    };

    document.head.appendChild(scriptTag);
}

function renderButtons() {
    if (!window.paypal) return;

    const container = document.getElementById(paypalContainerId);
    if (!container) return;

    container.innerHTML = '';

    window.paypal.Buttons({
        style: {
            layout: 'vertical',
            color: 'blue',
            shape: 'rect',
            label: 'pay',
            height: 45,
        },

        createOrder: async () => {
            try {
                procesando.value = true;
                mensajeError.value = '';
                const data = await crearOrden();
                return data.id;
            } catch (error) {
                mensajeError.value = error.response?.data?.message || 'Error al crear la orden de pago.';
                procesando.value = false;
                throw error;
            }
        },

        onApprove: async (data) => {
            try {
                procesando.value = true;
                const result = await capturarOrden(data.orderID);
                if (result.status === 'COMPLETED') {
                    pagoCompletado.value = true;
                    emit('pago-exitoso', result);
                } else {
                    mensajeError.value = 'El pago no se completó correctamente. Intente de nuevo.';
                    emit('pago-error', result);
                }
            } catch (error) {
                mensajeError.value = error.response?.data?.message || 'Error al procesar el pago.';
                emit('pago-error', error);
            } finally {
                procesando.value = false;
            }
        },

        onCancel: () => {
            procesando.value = false;
            mensajeError.value = 'El pago fue cancelado. Puede intentarlo nuevamente.';
        },

        onError: (err) => {
            procesando.value = false;
            mensajeError.value = 'Ocurrió un error con PayPal. Intente de nuevo más tarde.';
            console.error('PayPal onError', err);
            emit('pago-error', err);
        },
    }).render(`#${paypalContainerId}`);
}

onMounted(() => {
    loadPayPalSdk();
});
</script>

<template>
    <div class="w-full">
        <div v-if="sdkError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-semibold">Error de configuración</p>
            <p class="mt-1">{{ sdkError }}</p>
        </div>

        <div v-else-if="pagoCompletado" class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <p class="text-lg font-bold text-emerald-800">¡Pago realizado con éxito!</p>
            <p class="mt-1 text-sm text-emerald-600">
                {{ mode === 'repostulacion'
                    ? 'Su repostulación ha sido confirmada. Ya puede ingresar al sistema.'
                    : 'Tu inscripción ha sido confirmada. Recarga la página para ver tu estado actualizado.' }}
            </p>
        </div>

        <template v-else>
            <div v-if="procesando" class="mb-3 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Procesando pago...
            </div>

            <div v-if="mensajeError" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                {{ mensajeError }}
            </div>

            <div v-if="!sdkLoaded && !sdkError" class="flex items-center justify-center py-6 text-sm text-gray-400">
                <svg class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Cargando pasarela de pagos...
            </div>

            <div :id="paypalContainerId" class="min-h-[50px]"></div>
        </template>
    </div>
</template>
