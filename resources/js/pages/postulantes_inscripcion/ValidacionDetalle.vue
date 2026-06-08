<script setup>
// [CU03] Validar requisitos documentales - Pantalla de revisión y aprobación de documentos individuales

import { computed, ref, onMounted } from 'vue';
import { fetchExpedienteDocumental, submitValidacionDocumental } from '../../api/validacion-documental';
import { urlDocumentoPostulante } from '../../api/documentos';

const props = defineProps({
    inscripcionId: {
        type: [String, Number],
        required: true
    }
});

const emit = defineEmits(['back']);

const loading = ref(true);
const submitting = ref(false);
const serverMessage = ref('');
const fieldErrors = ref({});
const success = ref(false);
const resultadoValidacion = ref(null);

const expediente = ref(null);
const revisiones = ref([]);

const labelTipoDocumento = {
    ci: 'Carnet de Identidad',
    carnet_identidad: 'Carnet de Identidad',
    titulo_bachiller: 'Titulo de Bachiller',
    libreta_digitalizada: 'Libreta Digitalizada',
    certificado_nacimiento: 'Certificado de Nacimiento',
    fotografia: 'Fotografia 3x3 Fondo Rojo',
};

const postulante = computed(() => expediente.value?.inscripcion?.postulante ?? {});
const inscripcion = computed(() => expediente.value?.inscripcion ?? {});
const credenciales = computed(() => resultadoValidacion.value?.data?.credenciales ?? null);

const datosPostulante = computed(() => [
    ['Nombre completo', nombreCompleto(postulante.value)],
    ['C.I.', `${postulante.value.ci ?? '-'}${postulante.value.complemento ? ` ${postulante.value.complemento}` : ''}`],
    ['Correo electronico', postulante.value.correo ?? '-'],
    ['Telefono', postulante.value.telefono ?? '-'],
    ['Fecha de nacimiento', formatDate(postulante.value.fecha_nacimiento)],
    ['Genero', postulante.value.genero ?? '-'],
    ['Ciudad', postulante.value.ciudad ?? '-'],
    ['Direccion', postulante.value.direccion ?? '-'],
    ['Colegio de procedencia', postulante.value.colegio_procedencia ?? '-'],
    ['Gestion', inscripcion.value.gestion?.nombre ?? '-'],
    ['Codigo de solicitud', inscripcion.value.codigo ?? '-'],
    ['Estado de inscripcion', inscripcion.value.estado ?? '-'],
]);

onMounted(async () => {
    try {
        const payload = await fetchExpedienteDocumental(props.inscripcionId);
        expediente.value = payload.data;

        revisiones.value = expediente.value.documentos.map(doc => ({
            id: doc.id,
            tipo: doc.tipo,
            estado: doc.estado === 'pendiente' ? '' : doc.estado,
            observacion: doc.observacion || ''
        }));
    } catch (error) {
        serverMessage.value = 'No se pudo cargar el expediente documental.';
    } finally {
        loading.value = false;
    }
});

function nombreCompleto(persona) {
    return [
        persona.nombres,
        persona.apellido_paterno,
        persona.apellido_materno,
    ].filter(Boolean).join(' ') || '-';
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('es-BO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
}

function documentUrl(doc) {
    if (!doc.archivo_path || !doc.id) {
        return null;
    }

    return urlDocumentoPostulante(doc.id);
}

function documentFileName(doc) {
    if (!doc.archivo_path) {
        return 'Sin archivo cargado';
    }

    return doc.archivo_path.split('/').pop();
}

function estadoClass(estado) {
    return {
        pendiente: 'bg-slate-100 text-slate-600',
        aprobado: 'bg-emerald-100 text-emerald-700',
        observado: 'bg-amber-100 text-amber-700',
        rechazado: 'bg-red-100 text-red-700',
    }[estado] ?? 'bg-slate-100 text-slate-600';
}

function prevalidacionClass(estado) {
    return {
        ok: 'bg-emerald-100 text-emerald-700',
        observado: 'bg-amber-100 text-amber-800',
        critico: 'bg-red-100 text-red-700',
    }[estado] ?? 'bg-slate-100 text-slate-600';
}

async function handleSubmit() {
    submitting.value = true;
    serverMessage.value = '';
    fieldErrors.value = {};

    try {
        const payloadToSubmit = revisiones.value.map(rev => ({
            id: rev.id,
            estado: rev.estado,
            observacion: rev.observacion
        }));

        resultadoValidacion.value = await submitValidacionDocumental(props.inscripcionId, payloadToSubmit);
        success.value = true;
    } catch (error) {
        serverMessage.value = error.response?.data?.message ?? 'Error al procesar la validacion.';
        fieldErrors.value = error.response?.data?.errors ?? {};
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div>
        <div v-if="loading" class="flex items-center justify-center py-12">
            <span class="text-sm text-slate-500">Cargando expediente...</span>
        </div>

        <div v-else-if="success" class="rounded-lg border border-emerald-200 bg-emerald-50 p-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-xl font-semibold text-emerald-800">Validacion completada</h2>
                <p class="mt-2 text-sm text-emerald-700">{{ resultadoValidacion?.message }}</p>
            </div>

            <div v-if="credenciales" class="mx-auto mt-6 max-w-2xl rounded-lg border border-emerald-200 bg-white p-5 text-left shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Credenciales generadas</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">Numero de registro</dt>
                        <dd class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-lg font-bold text-slate-900">{{ credenciales.numero_registro }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Contrasena temporal</dt>
                        <dd class="mt-1 rounded border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-lg font-bold text-slate-900">{{ credenciales.password_temporal }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-sm" :class="credenciales.correo_enviado ? 'text-emerald-700' : 'text-amber-700'">
                    {{ credenciales.correo_enviado ? 'El correo fue enviado al postulante.' : 'No se pudo enviar el correo automaticamente; use estos datos para comunicarlos manualmente.' }}
                </p>
            </div>

            <div class="mt-6 text-center">
                <button @click="$emit('back')" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                    Volver a la bandeja
                </button>
            </div>
        </div>

        <div v-else-if="expediente">
            <header class="mb-6 flex items-center justify-between">
                <div>
                    <button @click="$emit('back')" class="mb-2 text-sm text-cyan-600 hover:underline">Volver a pendientes</button>
                    <h1 class="text-2xl font-bold text-slate-900">Revision Documental</h1>
                    <p class="mt-1 text-sm text-slate-500">Inscripcion: <span class="font-semibold text-slate-700">{{ inscripcion.codigo }}</span></p>
                </div>
            </header>

            <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Datos del Postulante</h3>
                <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="[label, value] in datosPostulante" :key="label" class="min-w-0">
                        <dt class="text-xs text-slate-500">{{ label }}</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ value }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="serverMessage" class="mb-6 rounded bg-red-50 p-4 text-sm text-red-700">
                {{ serverMessage }}
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div v-for="(rev, index) in revisiones" :key="rev.id" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h4 class="font-semibold text-slate-800">{{ labelTipoDocumento[rev.tipo] || rev.tipo }}</h4>
                            <p class="mt-1 text-xs text-slate-500">{{ documentFileName(expediente.documentos[index]) }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="prevalidacionClass(expediente.documentos[index].prevalidacion_estado)">
                                    Auto: {{ expediente.documentos[index].prevalidacion_estado || 'sin prevalidar' }}
                                    <template v-if="expediente.documentos[index].prevalidacion_puntaje !== null">
                                        ({{ expediente.documentos[index].prevalidacion_puntaje }}%)
                                    </template>
                                </span>
                            </div>
                            <ul v-if="expediente.documentos[index].prevalidacion_observaciones?.length" class="mt-2 list-disc pl-5 text-xs text-amber-700">
                                <li v-for="observacion in expediente.documentos[index].prevalidacion_observaciones" :key="observacion">
                                    {{ observacion }}
                                </li>
                            </ul>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="estadoClass(expediente.documentos[index].estado)">
                                {{ expediente.documentos[index].estado }}
                            </span>
                            <a
                                v-if="documentUrl(expediente.documentos[index])"
                                :href="documentUrl(expediente.documentos[index])"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-medium text-cyan-600 hover:underline"
                            >
                                Ver documento
                            </a>
                            <span v-else class="text-xs text-slate-400">Sin documento</span>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Dictamen <span class="text-red-500">*</span></span>
                            <select v-model="rev.estado" required class="mt-1.5 w-full rounded border border-slate-300 p-2 text-sm outline-none focus:border-cyan-600">
                                <option value="" disabled>Seleccionar resultado</option>
                                <option value="aprobado">Aprobado</option>
                                <option value="observado">Observado (Corregible)</option>
                                <option value="rechazado">Rechazado (No valido)</option>
                            </select>
                            <span v-if="fieldErrors[`revisiones.${index}.estado`]" class="mt-1 text-xs text-red-600">{{ fieldErrors[`revisiones.${index}.estado`][0] }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Observacion <span v-if="rev.estado === 'observado' || rev.estado === 'rechazado'" class="text-red-500">*</span></span>
                            <input v-model="rev.observacion" type="text" :required="rev.estado === 'observado' || rev.estado === 'rechazado'" placeholder="Detalle si hay problema..." class="mt-1.5 w-full rounded border border-slate-300 p-2 text-sm outline-none focus:border-cyan-600">
                            <span v-if="fieldErrors[`revisiones.${index}.observacion`]" class="mt-1 text-xs text-red-600">{{ fieldErrors[`revisiones.${index}.observacion`][0] }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" :disabled="submitting" class="rounded bg-cyan-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-800 disabled:opacity-50">
                        {{ submitting ? 'Guardando...' : 'Guardar Revision Documental' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
