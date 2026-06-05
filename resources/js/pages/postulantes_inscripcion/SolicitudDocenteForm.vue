<script setup>
import { onMounted, ref } from 'vue';
import { fetchSolicitudDocenteFormData, storeSolicitudDocente } from '../../api/postulaciones';
import { useToast } from '../../api/toast';

const emit = defineEmits(['back']);
const toast = useToast();

const loading = ref(true);
const submitting = ref(false);
const success = ref(false);
const serverMessage = ref('');
const fieldErrors = ref({});
const materias = ref([]);

const form = ref({
    ci: '',
    nombres: '',
    apellidos: '',
    correo: '',
    telefono: '',
    materia_id: '',
    profesion: '',
    documentos: {
        ci: null,
        titulo_profesional: null,
        diplomado: null,
        maestria: null,
        cv: null,
    },
});

const documentos = [
    ['ci', 'Carnet de Identidad'],
    ['titulo_profesional', 'Titulo profesional'],
    ['diplomado', 'Diplomado'],
    ['maestria', 'Maestria'],
    ['cv', 'Curriculum Vitae'],
];

onMounted(async () => {
    try {
        const payload = await fetchSolicitudDocenteFormData();
        materias.value = payload.data.materias;
    } catch (error) {
        serverMessage.value = error.response?.data?.message ?? 'No se pudieron cargar las materias.';
    } finally {
        loading.value = false;
    }
});

function handleFileChange(event, tipo) {
    const file = event.target.files[0];
    if (file) {
        form.value.documentos[tipo] = file;
    }
}

async function handleSubmit() {
    submitting.value = true;
    serverMessage.value = '';
    fieldErrors.value = {};

    try {
        const formData = new FormData();
        ['ci', 'nombres', 'apellidos', 'correo', 'telefono', 'materia_id', 'profesion'].forEach(key => {
            if (form.value[key] !== null && form.value[key] !== '') {
                formData.append(key, form.value[key]);
            }
        });

        documentos.forEach(([tipo]) => {
            if (form.value.documentos[tipo]) {
                formData.append(`documentos[${tipo}]`, form.value.documentos[tipo]);
            }
        });

        const payload = await storeSolicitudDocente(formData);
        success.value = true;
        serverMessage.value = payload.message;
    } catch (error) {
        const data = error.response?.data;
        serverMessage.value = data?.message ?? 'Ocurrio un error al registrar la solicitud docente.';
        fieldErrors.value = data?.errors ?? {};
        await toast.alert({
            title: 'No se pudo registrar la solicitud docente',
            message: errorMessageForModal(),
            confirmText: 'Aceptar',
            tone: 'danger',
        });
    } finally {
        submitting.value = false;
    }
}

function errorMessageForModal() {
    const firstFieldError = Object.values(fieldErrors.value)
        .flat()
        .find(Boolean);

    return firstFieldError
        ? `${serverMessage.value}\n\n${firstFieldError}`
        : serverMessage.value;
}
</script>

<template>
    <div v-if="loading" class="flex min-h-[50vh] items-center justify-center text-sm text-slate-600">
        Cargando formulario docente...
    </div>

    <div v-else-if="success" class="mx-auto max-w-lg py-16">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-8 text-center">
            <h2 class="text-xl font-semibold text-emerald-800">Solicitud docente registrada</h2>
            <p class="mt-2 text-sm text-emerald-700">{{ serverMessage }}</p>
            <p class="mt-4 text-xs text-emerald-600">El administrador revisara CI, titulo profesional, diplomado, maestria y CV antes de habilitar su acceso.</p>
            <button type="button" class="mt-6 rounded-md bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" @click="emit('back')">
                Volver al inicio
            </button>
        </div>
    </div>

    <div v-else class="mx-auto max-w-3xl py-8">
        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700">CUP FICCT</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Postulacion Docente</h1>
            <p class="mt-1 text-sm text-slate-500">Para aprobar la solicitud debe acreditar ingenieria, diplomado y maestria.</p>
        </div>

        <div v-if="serverMessage" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ serverMessage }}
        </div>

        <form class="space-y-6" @submit.prevent="handleSubmit">
            <fieldset class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <legend class="px-2 text-sm font-semibold text-slate-700">Datos personales y academicos</legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">CI *</span>
                        <input v-model="form.ci" class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" maxlength="30">
                        <span v-if="fieldErrors.ci" class="mt-1 block text-xs text-red-600">{{ fieldErrors.ci[0] }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Nombres *</span>
                        <input v-model="form.nombres" class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" maxlength="120">
                        <span v-if="fieldErrors.nombres" class="mt-1 block text-xs text-red-600">{{ fieldErrors.nombres[0] }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Apellidos</span>
                        <input v-model="form.apellidos" class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" maxlength="120">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Correo *</span>
                        <input v-model="form.correo" type="email" class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" maxlength="150">
                        <span v-if="fieldErrors.correo" class="mt-1 block text-xs text-red-600">{{ fieldErrors.correo[0] }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Telefono</span>
                        <input v-model="form.telefono" class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" maxlength="30">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Materia a la que postula *</span>
                        <select v-model="form.materia_id" class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="" disabled>Seleccionar materia</option>
                            <option v-for="materia in materias" :key="materia.id" :value="materia.id">{{ materia.codigo }} - {{ materia.nombre }}</option>
                        </select>
                        <span v-if="fieldErrors.materia_id" class="mt-1 block text-xs text-red-600">{{ fieldErrors.materia_id[0] }}</span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Profesion *</span>
                        <input v-model="form.profesion" class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej: Ingeniera de Sistemas" maxlength="150">
                        <span v-if="fieldErrors.profesion" class="mt-1 block text-xs text-red-600">{{ fieldErrors.profesion[0] }}</span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <legend class="px-2 text-sm font-semibold text-slate-700">Documentos obligatorios</legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label v-for="[tipo, label] in documentos" :key="tipo" class="block">
                        <span class="text-sm font-medium text-slate-700">{{ label }} *</span>
                        <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-1.5 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-cyan-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-cyan-700" @change="handleFileChange($event, tipo)">
                        <span v-if="fieldErrors[`documentos.${tipo}`]" class="mt-1 block text-xs text-red-600">{{ fieldErrors[`documentos.${tipo}`][0] }}</span>
                    </label>
                </div>
            </fieldset>

            <div class="flex justify-end gap-3">
                <button type="button" class="rounded-md border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="emit('back')">
                    Cancelar
                </button>
                <button type="submit" :disabled="submitting" class="rounded-md bg-cyan-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800 disabled:bg-slate-400">
                    {{ submitting ? 'Enviando...' : 'Enviar solicitud docente' }}
                </button>
            </div>
        </form>
    </div>
</template>
