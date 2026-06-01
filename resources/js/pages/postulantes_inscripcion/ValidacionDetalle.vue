<script setup>
import { ref, onMounted } from 'vue';
import { fetchExpedienteDocumental, submitValidacionDocumental } from '../../api/validacion-documental';

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

const expediente = ref(null);
const revisiones = ref([]);

const labelTipoDocumento = {
    ci: 'Carnet de Identidad',
    titulo_bachiller: 'Título de Bachiller',
    certificado_nacimiento: 'Certificado de Nacimiento',
    fotografia: 'Fotografía 3x3 Fondo Rojo',
};

onMounted(async () => {
    try {
        const payload = await fetchExpedienteDocumental(props.inscripcionId);
        expediente.value = payload.data;

        // Inicializar formulario reactivo de revisiones
        revisiones.value = expediente.value.documentos.map(doc => ({
            id: doc.id,
            tipo: doc.tipo,
            estado: doc.estado === 'pendiente' ? '' : doc.estado, // vacío obliga a seleccionar
            observacion: doc.observacion || ''
        }));
    } catch (error) {
        serverMessage.value = 'No se pudo cargar el expediente documental.';
    } finally {
        loading.value = false;
    }
});

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

        await submitValidacionDocumental(props.inscripcionId, payloadToSubmit);
        success.value = true;
    } catch (error) {
        serverMessage.value = error.response?.data?.message ?? 'Error al procesar la validación.';
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

        <div v-else-if="success" class="rounded-lg border border-emerald-200 bg-emerald-50 p-8 text-center">
            <h2 class="text-xl font-semibold text-emerald-800">Validación completada</h2>
            <p class="mt-2 text-sm text-emerald-700">Los documentos han sido evaluados y se ha sincronizado el estado del postulante.</p>
            <button @click="$emit('back')" class="mt-6 rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                Volver a la bandeja
            </button>
        </div>

        <div v-else-if="expediente">
            <header class="mb-6 flex items-center justify-between">
                <div>
                    <button @click="$emit('back')" class="mb-2 text-sm text-cyan-600 hover:underline">← Volver a pendientes</button>
                    <h1 class="text-2xl font-bold text-slate-900">Revisión Documental</h1>
                    <p class="mt-1 text-sm text-slate-500">Inscripción: <span class="font-semibold text-slate-700">{{ expediente.inscripcion.codigo }}</span></p>
                </div>
            </header>

            <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Datos del Postulante</h3>
                <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-slate-500">Nombre completo</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ expediente.inscripcion.postulante.nombres }} {{ expediente.inscripcion.postulante.apellido_paterno }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">C.I.</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ expediente.inscripcion.postulante.ci }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Gestión</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ expediente.inscripcion.gestion.nombre }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="serverMessage" class="mb-6 rounded bg-red-50 p-4 text-sm text-red-700">
                {{ serverMessage }}
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div v-for="(rev, index) in revisiones" :key="rev.id" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h4 class="font-semibold text-slate-800">{{ labelTipoDocumento[rev.tipo] || rev.tipo }}</h4>
                        <a href="#" class="text-xs font-medium text-cyan-600 hover:underline">Ver documento (Simulado)</a>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Dictamen <span class="text-red-500">*</span></span>
                            <select v-model="rev.estado" required class="mt-1.5 w-full rounded border border-slate-300 p-2 text-sm outline-none focus:border-cyan-600">
                                <option value="" disabled>Seleccionar resultado</option>
                                <option value="aprobado">Aprobado</option>
                                <option value="observado">Observado (Corregible)</option>
                                <option value="rechazado">Rechazado (No válido)</option>
                            </select>
                            <span v-if="fieldErrors[`revisiones.${index}.estado`]" class="mt-1 text-xs text-red-600">{{ fieldErrors[`revisiones.${index}.estado`][0] }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Observación <span v-if="rev.estado === 'observado' || rev.estado === 'rechazado'" class="text-red-500">*</span></span>
                            <input v-model="rev.observacion" type="text" :required="rev.estado === 'observado' || rev.estado === 'rechazado'" placeholder="Detalle si hay problema..." class="mt-1.5 w-full rounded border border-slate-300 p-2 text-sm outline-none focus:border-cyan-600">
                            <span v-if="fieldErrors[`revisiones.${index}.observacion`]" class="mt-1 text-xs text-red-600">{{ fieldErrors[`revisiones.${index}.observacion`][0] }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" :disabled="submitting" class="rounded bg-cyan-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-800 disabled:opacity-50">
                        {{ submitting ? 'Guardando...' : 'Guardar Revisión Documental' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
