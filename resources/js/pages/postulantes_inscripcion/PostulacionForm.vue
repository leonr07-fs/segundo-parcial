<script setup>
// [CU02] Registrar postulación CUP - Formulario de captura de datos personales y carrera

import { onMounted, ref, computed } from 'vue';
import { fetchFormData, storePostulacion } from '../../api/postulaciones';
import { useToast } from '../../api/toast';

const emit = defineEmits(['back']);
const toast = useToast();

/* ------------------------------------------------------------------ */
/*  Estado reactivo                                                    */
/* ------------------------------------------------------------------ */

const loading = ref(true);
const submitting = ref(false);
const success = ref(false);
const serverMessage = ref('');
const fieldErrors = ref({});

const gestion = ref(null);
const carreras = ref([]);
const inscripcionCodigo = ref('');

const form = ref({
    gestion_id: '',
    ci: '',
    complemento: '',
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    fecha_nacimiento: '',
    genero: '',
    correo: '',
    telefono: '',
    direccion: '',
    colegio_procedencia: '',
    ciudad: '',
    carrera_primera_opcion_id: '',
    carrera_segunda_opcion_id: '',
    foto_ci: null,
    foto_libreta: null,
});

/* ------------------------------------------------------------------ */
/*  Computed                                                           */
/* ------------------------------------------------------------------ */

const carrerasSegundaOpcion = computed(() => {
    return carreras.value.filter(c => c.id !== Number(form.value.carrera_primera_opcion_id));
});

/* ------------------------------------------------------------------ */
/*  Lifecycle                                                          */
/* ------------------------------------------------------------------ */

onMounted(async () => {
    try {
        const payload = await fetchFormData();
        gestion.value = payload.data.gestion;
        carreras.value = payload.data.carreras;
        form.value.gestion_id = gestion.value.id;
    } catch (error) {
        serverMessage.value = error.response?.data?.message ?? 'No se pudieron cargar los datos del formulario.';
    } finally {
        loading.value = false;
    }
});

/* ------------------------------------------------------------------ */
/*  Submit                                                             */
/* ------------------------------------------------------------------ */

const handleFileChange = (e, field) => {
    const file = e.target.files[0];
    if (file) {
        form.value[field] = file;
    }
};

async function handleSubmit() {
    submitting.value = true;
    serverMessage.value = '';
    fieldErrors.value = {};

    try {
        const formData = new FormData();
        Object.keys(form.value).forEach(key => {
            if (form.value[key] !== null && form.value[key] !== '') {
                formData.append(key, form.value[key]);
            }
        });

        const payload = await storePostulacion(formData);
        success.value = true;
        inscripcionCodigo.value = payload.data.inscripcion.codigo;
        serverMessage.value = payload.message;
    } catch (error) {
        const data = error.response?.data;
        serverMessage.value = data?.message ?? 'Ocurrió un error al registrar la postulación.';
        fieldErrors.value = data?.errors ?? {};
        await toast.alert({
            title: 'No se pudo registrar la postulacion',
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

function resetForm() {
    success.value = false;
    serverMessage.value = '';
    fieldErrors.value = {};
    inscripcionCodigo.value = '';
    form.value = {
        gestion_id: gestion.value?.id ?? '',
        ci: '',
        complemento: '',
        nombres: '',
        apellido_paterno: '',
        apellido_materno: '',
        fecha_nacimiento: '',
        genero: '',
        correo: '',
        telefono: '',
        direccion: '',
        colegio_procedencia: '',
        ciudad: '',
        carrera_primera_opcion_id: '',
        carrera_segunda_opcion_id: '',
        foto_ci: null,
        foto_libreta: null,
    };
}
</script>

<template>
    <!-- Estado de carga -->
    <div v-if="loading" class="flex min-h-[60vh] items-center justify-center">
        <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-6 py-4 shadow-sm">
            <svg class="h-5 w-5 animate-spin text-cyan-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm text-slate-600">Cargando formulario de postulación...</span>
        </div>
    </div>

    <!-- Sin gestión habilitada -->
    <div v-else-if="!gestion && !success" class="mx-auto max-w-lg py-16 text-center">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-8">
            <svg class="mx-auto h-12 w-12 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <h2 class="mt-4 text-lg font-semibold text-amber-800">Sin gestión habilitada</h2>
            <p class="mt-2 text-sm text-amber-700">{{ serverMessage || 'No hay una gestión académica habilitada para inscripción en este momento.' }}</p>
        </div>
    </div>

    <!-- Registro exitoso -->
    <div v-else-if="success" class="mx-auto max-w-lg py-16">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-8 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="mt-5 text-xl font-semibold text-emerald-800">¡Postulación registrada!</h2>
            <p class="mt-2 text-sm text-emerald-700">{{ serverMessage }}</p>
            <div class="mt-5 rounded-md border border-emerald-300 bg-white px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Código de inscripción</p>
                <p class="mt-1 text-2xl font-bold tracking-wide text-slate-900">{{ inscripcionCodigo }}</p>
            </div>
            <p class="mt-4 text-xs text-emerald-600">Guarda este código. Lo necesitarás para los siguientes pasos.</p>
            <button
                type="button"
                class="mt-6 rounded-md bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                @click="resetForm"
            >
                Registrar otra postulación
            </button>
        </div>
    </div>

    <!-- Formulario de postulación -->
    <div v-else class="mx-auto max-w-3xl py-8">
        <div class="mb-6">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-md bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700 ring-1 ring-cyan-200 ring-inset">
                    {{ gestion.nombre }}
                </span>
            </div>
            <h1 class="mt-3 text-2xl font-bold text-slate-900">Registro de Postulación CUP</h1>
            <p class="mt-1 text-sm text-slate-500">Complete todos los campos obligatorios marcados con asterisco (*) para formalizar su postulación.</p>
        </div>

        <!-- Error global -->
        <div v-if="serverMessage && !success" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span>{{ serverMessage }}</span>
            </div>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-8">
            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECCIÓN 1: Datos Personales                            -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <fieldset class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <legend class="px-2 text-sm font-semibold text-slate-700">Datos Personales</legend>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- CI -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">CI <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.ci"
                            type="text"
                            maxlength="30"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.ci ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                            placeholder="Ej: 9876543"
                        >
                        <span v-if="fieldErrors.ci" class="mt-1 block text-xs text-red-600">{{ fieldErrors.ci[0] }}</span>
                    </label>

                    <!-- Complemento -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Complemento</span>
                        <input
                            v-model="form.complemento"
                            type="text"
                            maxlength="10"
                            class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100"
                            placeholder="Ej: 1A"
                        >
                    </label>

                    <!-- Nombres -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Nombres <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.nombres"
                            type="text"
                            maxlength="120"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.nombres ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                        <span v-if="fieldErrors.nombres" class="mt-1 block text-xs text-red-600">{{ fieldErrors.nombres[0] }}</span>
                    </label>

                    <!-- Apellido Paterno -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Apellido Paterno <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.apellido_paterno"
                            type="text"
                            maxlength="80"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.apellido_paterno ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                        <span v-if="fieldErrors.apellido_paterno" class="mt-1 block text-xs text-red-600">{{ fieldErrors.apellido_paterno[0] }}</span>
                    </label>

                    <!-- Apellido Materno -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Apellido Materno</span>
                        <input
                            v-model="form.apellido_materno"
                            type="text"
                            maxlength="80"
                            class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100"
                        >
                    </label>

                    <!-- Fecha de Nacimiento -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Fecha de Nacimiento <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.fecha_nacimiento"
                            type="date"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.fecha_nacimiento ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                        <span v-if="fieldErrors.fecha_nacimiento" class="mt-1 block text-xs text-red-600">{{ fieldErrors.fecha_nacimiento[0] }}</span>
                    </label>

                    <!-- Género -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Género <span class="text-red-500">*</span></span>
                        <select
                            v-model="form.genero"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.genero ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                            <option value="" disabled>Seleccionar</option>
                            <option value="masculino">Masculino</option>
                            <option value="femenino">Femenino</option>
                            <option value="otro">Otro</option>
                        </select>
                        <span v-if="fieldErrors.genero" class="mt-1 block text-xs text-red-600">{{ fieldErrors.genero[0] }}</span>
                    </label>

                    <!-- Correo -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Correo Electrónico <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.correo"
                            type="email"
                            maxlength="150"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.correo ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                            placeholder="correo@ejemplo.com"
                        >
                        <span v-if="fieldErrors.correo" class="mt-1 block text-xs text-red-600">{{ fieldErrors.correo[0] }}</span>
                    </label>

                    <!-- Teléfono -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Teléfono <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.telefono"
                            type="text"
                            maxlength="30"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.telefono ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                            placeholder="Ej: 71234567"
                        >
                        <span v-if="fieldErrors.telefono" class="mt-1 block text-xs text-red-600">{{ fieldErrors.telefono[0] }}</span>
                    </label>

                    <!-- Dirección -->
                    <label class="block sm:col-span-2 lg:col-span-3">
                        <span class="text-sm font-medium text-slate-700">Dirección</span>
                        <input
                            v-model="form.direccion"
                            type="text"
                            maxlength="255"
                            class="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100"
                            placeholder="Calle, número, zona"
                        >
                    </label>
                </div>
            </fieldset>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECCIÓN 2: Datos Académicos                            -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <fieldset class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <legend class="px-2 text-sm font-semibold text-slate-700">Datos Académicos</legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Colegio de procedencia -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Colegio de Procedencia <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.colegio_procedencia"
                            type="text"
                            maxlength="150"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.colegio_procedencia ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                        <span v-if="fieldErrors.colegio_procedencia" class="mt-1 block text-xs text-red-600">{{ fieldErrors.colegio_procedencia[0] }}</span>
                    </label>

                    <!-- Ciudad -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Ciudad <span class="text-red-500">*</span></span>
                        <input
                            v-model="form.ciudad"
                            type="text"
                            maxlength="100"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.ciudad ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                        <span v-if="fieldErrors.ciudad" class="mt-1 block text-xs text-red-600">{{ fieldErrors.ciudad[0] }}</span>
                    </label>
                </div>
            </fieldset>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECCIÓN: Documentos de Respaldo -->
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Documentos de Respaldo (Digitalizados)</h2>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Foto Carnet de Identidad <span class="text-red-500">*</span></label>
                        <input
                            type="file"
                            @change="handleFileChange($event, 'foto_ci')"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-cyan-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-cyan-700 hover:file:bg-cyan-100 focus:outline-none"
                        />
                        <span v-if="fieldErrors.foto_ci" class="mt-1 block text-xs text-red-600">{{ fieldErrors.foto_ci[0] }}</span>
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG o PDF. Máx 2MB.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Libreta Escolar <span class="text-red-500">*</span></label>
                        <input
                            type="file"
                            @change="handleFileChange($event, 'foto_libreta')"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-cyan-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-cyan-700 hover:file:bg-cyan-100 focus:outline-none"
                        />
                        <span v-if="fieldErrors.foto_libreta" class="mt-1 block text-xs text-red-600">{{ fieldErrors.foto_libreta[0] }}</span>
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG o PDF. Máx 2MB.</p>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECCIÓN 3: Opciones de Carrera                         -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <fieldset class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <legend class="px-2 text-sm font-semibold text-slate-700">Opciones de Carrera</legend>

                <p class="mb-4 text-xs text-slate-500">Seleccione dos carreras diferentes. Si no hay cupo en la primera, se intentará con la segunda.</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Primera opción -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Primera Opción <span class="text-red-500">*</span></span>
                        <select
                            v-model="form.carrera_primera_opcion_id"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.carrera_primera_opcion_id ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                            <option value="" disabled>Seleccionar carrera</option>
                            <option v-for="c in carreras" :key="c.id" :value="c.id">
                                {{ c.nombre }}
                            </option>
                        </select>
                        <span v-if="fieldErrors.carrera_primera_opcion_id" class="mt-1 block text-xs text-red-600">{{ fieldErrors.carrera_primera_opcion_id[0] }}</span>
                    </label>

                    <!-- Segunda opción -->
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Segunda Opción <span class="text-red-500">*</span></span>
                        <select
                            v-model="form.carrera_segunda_opcion_id"
                            class="mt-1.5 w-full rounded-md border px-3 py-2 text-sm outline-none transition"
                            :class="fieldErrors.carrera_segunda_opcion_id ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100' : 'border-slate-300 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100'"
                        >
                            <option value="" disabled>Seleccionar carrera</option>
                            <option v-for="c in carrerasSegundaOpcion" :key="c.id" :value="c.id">
                                {{ c.nombre }}
                            </option>
                        </select>
                        <span v-if="fieldErrors.carrera_segunda_opcion_id" class="mt-1 block text-xs text-red-600">{{ fieldErrors.carrera_segunda_opcion_id[0] }}</span>
                    </label>
                </div>
            </fieldset>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- Botón de envío                                         -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="flex items-center justify-end gap-3">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('back')"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="rounded-md bg-cyan-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                    :disabled="submitting"
                >
                    <span v-if="submitting" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Registrando...
                    </span>
                    <span v-else>Registrar Postulación</span>
                </button>
            </div>
        </form>
    </div>
</template>
