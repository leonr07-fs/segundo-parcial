<script setup>
/**
 * Consulta pública del estado de postulación.
 * Permite a un postulante sin cuenta consultar el estado de su documentación
 * e iniciar el pago PayPal cuando su documentación esté aprobada.
 */
import { ref, computed } from 'vue';
import axios from 'axios';

const emit = defineEmits(['back']);

const ci = ref('');
const buscando = ref(false);
const resultado = ref(null);
const libros = ref([]);
const error = ref('');
const pagoExitoso = ref(false);
const procesandoPago = ref(false);
const mensajePago = ref('');
const sdkLoaded = ref(false);
const sdkError = ref('');
let paypalRendered = false;

const librosPorMateria = computed(() => {
    const agrupados = {};
    
    libros.value.forEach(libro => {
        const materia = libro.materia || 'Sin Materia';
        if (!agrupados[materia]) {
            agrupados[materia] = [];
        }
        agrupados[materia].push(libro);
    });
    
    return agrupados;
});

const estadoInscripcion = computed(() => resultado.value?.inscripcion?.estado || '');

const estadoLabel = computed(() => {
    const labels = {
        prepostulado: 'Postulación enviada — En revisión',
        documentos_pendientes: 'Documentación observada — Requiere corrección',
        documentos_aprobados: 'Documentación aprobada — Pago pendiente',
        pagado: 'Pago confirmado — Inscripción en proceso',
        inscrito: 'Inscripción completada',
    };
    return labels[estadoInscripcion.value] || estadoInscripcion.value;
});

const estadoColor = computed(() => {
    const colors = {
        prepostulado: 'amber',
        documentos_pendientes: 'orange',
        documentos_aprobados: 'blue',
        pagado: 'emerald',
        inscrito: 'emerald',
    };
    return colors[estadoInscripcion.value] || 'slate';
});

const validacion = computed(() => resultado.value?.inscripcion?.validacion_documental || null);
const documentos = computed(() => resultado.value?.inscripcion?.documentos || []);
const observacionesDocumentos = computed(() =>
    documentos.value
        .filter(d => d.observacion && (d.estado === 'observado' || d.estado === 'rechazado'))
        .map(d => ({ tipo: d.tipo?.replace(/_/g, ' '), estado: d.estado, observacion: d.observacion }))
);
const mostrarPayPal = computed(() => estadoInscripcion.value === 'documentos_aprobados' && !pagoExitoso.value);
const estaPagado = computed(() => ['pagado', 'inscrito'].includes(estadoInscripcion.value) || pagoExitoso.value);

async function consultar() {
    if (!ci.value.trim()) return;
    buscando.value = true;
    error.value = '';
    resultado.value = null;
    libros.value = [];
    pagoExitoso.value = false;
    paypalRendered = false;

    try {
        const { data } = await axios.get(`/api/consulta-postulacion/${encodeURIComponent(ci.value.trim())}`);
        if (data.ok) {
            resultado.value = data.data;
            
            // Cargar libros disponibles
            try {
                const librosData = await axios.get(`/api/consulta-postulacion/${encodeURIComponent(ci.value.trim())}/libros`);
                if (librosData.data.ok) {
                    libros.value = librosData.data.data.libros;
                }
            } catch (e) {
                console.error('Error cargando libros:', e);
            }
            
            if (data.data?.inscripcion?.estado === 'documentos_aprobados') {
                await loadPayPalSdk();
            }
        }
    } catch (e) {
        if (e.response?.status === 404) {
            error.value = 'No se encontró ninguna postulación con el CI proporcionado.';
        } else {
            error.value = e.response?.data?.message || 'Error al consultar. Intente nuevamente.';
        }
    } finally {
        buscando.value = false;
    }
}

function loadPayPalSdk() {
    return new Promise((resolve) => {
        const clientId = import.meta.env.VITE_PAYPAL_CLIENT_ID;
        if (!clientId) {
            sdkError.value = 'PayPal Client ID no configurado. Contacte al administrador.';
            resolve();
            return;
        }

        if (window.paypal) {
            sdkLoaded.value = true;
            setTimeout(() => { renderPayPalButtons(); resolve(); }, 100);
            return;
        }

        const script = document.createElement('script');
        script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=USD&locale=es_ES&disable-funding=card`;
        script.async = true;
        script.onload = () => {
            sdkLoaded.value = true;
            setTimeout(() => { renderPayPalButtons(); resolve(); }, 100);
        };
        script.onerror = () => {
            sdkError.value = 'No se pudo cargar el SDK de PayPal.';
            resolve();
        };
        document.head.appendChild(script);
    });
}

function renderPayPalButtons() {
    if (!window.paypal || paypalRendered) return;
    const container = document.getElementById('consulta-paypal-container');
    if (!container) return;
    container.innerHTML = '';
    paypalRendered = true;

    window.paypal.Buttons({
        style: { layout: 'vertical', color: 'blue', shape: 'rect', label: 'pay', height: 45 },

        createOrder: async () => {
            procesandoPago.value = true;
            mensajePago.value = '';
            try {
                const { data } = await axios.post('/api/public/paypal/create-order', { ci: ci.value.trim() });
                return data.id;
            } catch (e) {
                mensajePago.value = e.response?.data?.message || 'Error al crear la orden de pago.';
                procesandoPago.value = false;
                throw e;
            }
        },

        onApprove: async (data) => {
            procesandoPago.value = true;
            try {
                const res = await axios.post('/api/public/paypal/capture-order', { orderID: data.orderID });
                if (res.data.status === 'COMPLETED') {
                    pagoExitoso.value = true;
                    // Recargar estado
                    setTimeout(consultar, 2000);
                } else {
                    mensajePago.value = 'El pago no se completó correctamente.';
                }
            } catch (e) {
                mensajePago.value = e.response?.data?.message || 'Error al procesar el pago.';
            } finally {
                procesandoPago.value = false;
            }
        },

        onCancel: () => {
            procesandoPago.value = false;
            mensajePago.value = 'Pago cancelado. Puede intentarlo nuevamente.';
        },

        onError: (err) => {
            procesandoPago.value = false;
            mensajePago.value = 'Error con PayPal. Intente nuevamente.';
            console.error('PayPal error', err);
        },
    }).render('#consulta-paypal-container');
}
</script>

<template>
    <div class="mx-auto max-w-2xl py-10">
        <!-- Formulario de consulta -->
        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-cyan-50">
                    <svg class="h-7 w-7 text-cyan-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-slate-900">Consultar Estado de Postulación</h2>
                <p class="mt-1 text-sm text-slate-500">Ingresa tu número de Cédula de Identidad para verificar el estado de tu documentación.</p>
            </div>

            <form @submit.prevent="consultar" class="flex gap-3">
                <input
                    v-model="ci"
                    type="text"
                    placeholder="Ej. 12345678"
                    class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-cyan-600 focus:bg-white focus:ring-4 focus:ring-cyan-600/10"
                    required
                />
                <button
                    type="submit"
                    :disabled="buscando || !ci.trim()"
                    class="shrink-0 rounded-xl bg-cyan-700 px-6 py-3 text-sm font-bold text-white shadow transition hover:bg-cyan-800 disabled:opacity-60"
                >
                    {{ buscando ? 'Consultando...' : 'Consultar' }}
                </button>
            </form>

            <!-- Error -->
            <div v-if="error" class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ error }}
            </div>
        </div>

        <!-- Resultado -->
        <div v-if="resultado" class="mt-6 space-y-5">
            <!-- Info del postulante -->
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Postulante</p>
                <p class="mt-1 text-lg font-bold text-slate-900">
                    {{ resultado.postulante?.nombres }} {{ resultado.postulante?.apellido_paterno }}
                </p>

                <template v-if="resultado.inscripcion">
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-400">Estado</span>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-bold"
                            :class="{
                                'bg-amber-100 text-amber-800': estadoColor === 'amber',
                                'bg-orange-100 text-orange-800': estadoColor === 'orange',
                                'bg-blue-100 text-blue-800': estadoColor === 'blue',
                                'bg-emerald-100 text-emerald-800': estadoColor === 'emerald',
                                'bg-slate-100 text-slate-700': estadoColor === 'slate',
                            }"
                        >
                            {{ estadoLabel }}
                        </span>
                    </div>
                    <div v-if="resultado.inscripcion.gestion" class="mt-2 text-xs text-slate-500">
                        Gestión: {{ resultado.inscripcion.gestion }}
                    </div>
                </template>
                <p v-else class="mt-3 text-sm text-slate-500">No se encontró una inscripción activa.</p>
            </div>

            <!-- Material de Estudio -->
            <div v-if="libros.length" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-blue-950 mb-6">Material de Estudio Universitario</h3>
                <div class="space-y-8">
                    <div v-for="(librosMateria, materia) in librosPorMateria" :key="materia" class="border-l-4 border-blue-500 pl-4">
                        <h4 class="text-base font-bold text-blue-900 mb-3">{{ materia }}</h4>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <a v-for="libro in librosMateria" :key="libro.id" :href="libro.url" target="_blank"
                                class="group flex flex-col rounded-xl border border-gray-200 bg-gray-50 p-4 transition-all hover:border-blue-300 hover:bg-blue-50 hover:shadow-md">
                                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-blue-600 group-hover:bg-blue-200">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                </div>
                                <h5 class="text-sm font-bold text-gray-900 group-hover:text-blue-800 line-clamp-2">{{ libro.titulo }}</h5>
                                <div class="mt-auto pt-3">
                                    <span class="inline-flex items-center text-xs font-semibold text-blue-600">
                                        Descargar PDF
                                        <svg class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Divider before estado messages -->
            <div v-if="estadoInscripcion === 'prepostulado'" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                <p class="font-semibold">📋 Validación de documentos pendiente</p>
                <p class="mt-1">Tu documentación fue recibida y está siendo revisada por el equipo administrativo. Una vez aprobada, se habilitará la opción de pago en esta misma página.</p>
            </div>

            <!-- Estado: Observado / Rechazado -->
            <template v-if="estadoInscripcion === 'documentos_pendientes'">
                <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5 text-sm text-orange-800">
                    <p class="font-semibold">⚠️ Documentación observada</p>
                    <p class="mt-1">El equipo administrativo revisó tu documentación y encontró observaciones. Revisa los detalles y comunícate con la administración para subsanar.</p>
                </div>
                <div v-if="validacion?.observacion" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Observación general</p>
                    <p class="text-sm text-slate-700">{{ validacion.observacion }}</p>
                </div>
                <div v-if="observacionesDocumentos.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-3">Detalle por documento</p>
                    <ul class="space-y-2">
                        <li v-for="(doc, idx) in observacionesDocumentos" :key="idx" class="flex items-start gap-2 text-sm">
                            <span
                                class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                :class="doc.estado === 'rechazado' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'"
                            >{{ doc.estado }}</span>
                            <span class="text-slate-700"><strong class="capitalize">{{ doc.tipo }}:</strong> {{ doc.observacion }}</span>
                        </li>
                    </ul>
                </div>
            </template>

            <!-- Estado: Documentos aprobados → Pago PayPal -->
            <template v-if="mostrarPayPal">
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-800">
                    <p class="font-semibold">✅ Documentación aprobada — Pago pendiente</p>
                    <p class="mt-1">Tu documentación ha sido verificada y aprobada. Realiza el pago de inscripción para completar tu registro CUP.</p>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-900">Monto a pagar</p>
                        <p class="text-2xl font-bold text-blue-700">Bs. 200.00</p>
                    </div>

                    <div v-if="sdkError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">⚠ Error de configuración</p>
                        <p class="mt-1">{{ sdkError }}</p>
                    </div>

                    <div v-if="procesandoPago" class="mb-3 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Procesando pago...
                    </div>

                    <div v-if="mensajePago" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                        {{ mensajePago }}
                    </div>

                    <div v-if="!sdkLoaded && !sdkError" class="flex items-center justify-center py-6 text-sm text-gray-400">
                        <svg class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Cargando pasarela de pagos...
                    </div>

                    <div id="consulta-paypal-container" class="min-h-[50px]"></div>
                </div>
            </template>

            <!-- Estado: Pago exitoso -->
            <div v-if="pagoExitoso" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                    <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-lg font-bold text-emerald-800">¡Pago realizado con éxito!</p>
                <p class="mt-1 text-sm text-emerald-600">Tu inscripción ha sido confirmada.</p>
            </div>

            <!-- Estado: Ya pagado (consultando después) -->
            <div v-else-if="estaPagado" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                    <svg class="h-7 w-7 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-lg font-bold text-emerald-800">Inscripción confirmada</p>
                <p class="mt-1 text-sm text-emerald-600">Tu pago fue registrado exitosamente.</p>
            </div>
        </div>
    </div>
</template>
