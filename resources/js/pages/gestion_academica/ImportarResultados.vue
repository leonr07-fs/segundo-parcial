<script setup>
import { ref } from 'vue';
import { uploadEvaluacionesCsv } from '../../api/evaluaciones';

const emit = defineEmits(['navigate']);

const fileInput = ref(null);
const selectedFile = ref(null);
const submitting = ref(false);
const serverMessage = ref('');
const fieldErrors = ref({});
const resultado = ref(null);

function handleFileChange(event) {
    const file = event.target.files[0];
    if (file) {
        selectedFile.value = file;
        serverMessage.value = '';
        fieldErrors.value = {};
    }
}

async function handleSubmit() {
    if (!selectedFile.value) {
        serverMessage.value = 'Debes seleccionar un archivo CSV primero.';
        return;
    }

    submitting.value = true;
    serverMessage.value = '';
    fieldErrors.value = {};
    resultado.value = null;

    try {
        const payload = await uploadEvaluacionesCsv(selectedFile.value);
        resultado.value = payload.data;
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors || {};
            serverMessage.value = 'El archivo no cumple con los requisitos.';
        } else {
            serverMessage.value = error.response?.data?.message || 'Error al procesar el archivo.';
        }
    } finally {
        submitting.value = false;
        // Limpiar el input
        if (fileInput.value) {
            fileInput.value.value = '';
        }
        selectedFile.value = null;
    }
}
</script>

<template>
    <div class="mx-auto max-w-4xl">
        <header class="mb-6">
            <button @click="$emit('navigate', '/admin/dashboard')" class="mb-4 flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-700">
                &larr; Volver al Panel
            </button>
            <h1 class="text-2xl font-bold text-slate-900">Importar Resultados Académicos</h1>
            <p class="mt-1 text-sm text-slate-500">Sube un archivo CSV con las calificaciones para registrar o actualizar evaluaciones masivamente.</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Formulario de Subida -->
            <div class="lg:col-span-1">
                <form @submit.prevent="handleSubmit" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Archivo CSV de Notas</label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,.txt"
                            required
                            @change="handleFileChange"
                            class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                        />
                        <p v-if="fieldErrors.archivo" class="mt-2 text-xs text-red-600">{{ fieldErrors.archivo[0] }}</p>
                    </div>

                    <div v-if="serverMessage" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {{ serverMessage }}
                    </div>

                    <button
                        type="submit"
                        :disabled="!selectedFile || submitting"
                        class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <span v-if="submitting" class="flex items-center justify-center">
                            <svg class="mr-2 h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Procesando...
                        </span>
                        <span v-else>Subir e Importar</span>
                    </button>
                </form>

                <div class="mt-6 rounded-lg bg-indigo-50 p-4 border border-indigo-100">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-800">Formato Esperado</h3>
                    <p class="mt-2 text-xs text-indigo-700">El archivo debe contener las siguientes columnas, separadas por coma:</p>
                    <ul class="mt-2 list-disc pl-4 text-xs text-indigo-700 space-y-1">
                        <li><code>inscripcion_codigo</code></li>
                        <li><code>grupo_materia_id</code></li>
                        <li><code>examen_1</code></li>
                        <li><code>examen_2</code></li>
                        <li><code>examen_3</code></li>
                        <li><code>promedio</code></li>
                    </ul>
                </div>
            </div>

            <!-- Panel de Resultados -->
            <div class="lg:col-span-2">
                <div v-if="!resultado" class="flex h-full min-h-[300px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-center p-6">
                    <svg class="h-10 w-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900">Sin resultados aún</h3>
                    <p class="mt-1 text-sm text-slate-500">Sube un archivo para ver el resumen de la importación.</p>
                </div>

                <div v-else class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="bg-slate-50 border-b border-slate-200 p-6 flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Resumen de Importación</h2>
                            <p class="text-sm text-slate-500">Reporte del procesamiento masivo.</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="text-center">
                                <span class="block text-2xl font-bold text-emerald-600">{{ resultado.exitosas }}</span>
                                <span class="text-xs uppercase text-slate-500">Exitosas</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-2xl font-bold" :class="resultado.errores.length > 0 ? 'text-red-600' : 'text-slate-400'">{{ resultado.errores.length }}</span>
                                <span class="text-xs uppercase text-slate-500">Errores</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="resultado.errores.length > 0" class="p-0">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 font-semibold text-slate-900 w-20">Fila CSV</th>
                                    <th class="px-6 py-3 font-semibold text-slate-900">Motivo del Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="(error, index) in resultado.errores" :key="index">
                                    <td class="whitespace-nowrap px-6 py-3 font-medium text-slate-900">{{ error.fila }}</td>
                                    <td class="px-6 py-3 text-red-600">{{ error.mensaje }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-8 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                            <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-slate-900">¡Importación perfecta!</h3>
                        <p class="mt-1 text-sm text-slate-500">Todas las filas fueron procesadas y registradas exitosamente.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
