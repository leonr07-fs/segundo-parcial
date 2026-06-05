<script setup>
// [CU09] Importar resultados académicos - Carga masiva de CSV de notas y navegación inmediata

import { ref } from 'vue';
import { uploadEvaluacionesCsv } from '../../api/evaluaciones';
import * as XLSX from 'xlsx';

const emit = defineEmits(['navigate']);

const fileInput = ref(null);
const selectedFile = ref(null);
const numeroExamen = ref(1);
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

async function parseExcelToCsv(file, examNum) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                
                let sheetName = workbook.SheetNames.find(name => name.toLowerCase() === 'notas_examenes');
                if (!sheetName) {
                    sheetName = workbook.SheetNames[0];
                }
                
                const worksheet = workbook.Sheets[sheetName];
                const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                
                if (rows.length === 0) {
                    throw new Error('El archivo Excel está vacío o no contiene hojas legibles.');
                }
                
                const headers = rows[0].map(h => String(h ?? '').trim().toLowerCase());
                
                let idxCodigoCup = headers.indexOf('codigo_cup');
                if (idxCodigoCup === -1) idxCodigoCup = headers.indexOf('inscripcion_codigo');
                
                let idxMateria = headers.indexOf('materia');
                if (idxMateria === -1) idxMateria = headers.indexOf('grupo_materia_id');
                
                let idxExamen = -1;
                if (headers.includes('nota')) {
                    idxExamen = headers.indexOf('nota');
                } else {
                    const idxExamen1 = headers.indexOf('examen_1');
                    const idxExamen2 = headers.indexOf('examen_2');
                    const idxExamen3 = headers.indexOf('examen_3');
                    
                    if (examNum === 1) idxExamen = idxExamen1;
                    else if (examNum === 2) idxExamen = idxExamen2;
                    else if (examNum === 3) idxExamen = idxExamen3;
                }
                
                if (idxCodigoCup === -1 || idxMateria === -1) {
                    throw new Error('El Excel debe contener columnas de identificación (ej. "codigo_cup" o "inscripcion_codigo") y materia (ej. "materia" o "grupo_materia_id").');
                }
                
                if (idxExamen === -1) {
                    throw new Error(`No se encontró la columna de notas o del Examen ${examNum} en la hoja.`);
                }
                
                let csvContent = 'inscripcion_codigo,grupo_materia_id,nota\n';
                
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    if (!row || row.length === 0) continue;
                    
                    const codigoCup = row[idxCodigoCup];
                    const materia = row[idxMateria];
                    const nota = row[idxExamen];
                    
                    if (codigoCup === undefined || codigoCup === null || String(codigoCup).trim() === '') {
                        continue;
                    }
                    
                    const cleanNota = (nota === undefined || nota === null || String(nota).trim() === '') ? '' : String(nota).trim();
                    
                    const escape = (val) => {
                        const str = String(val).replace(/"/g, '""');
                        return str.includes(',') || str.includes('\n') || str.includes('"') ? `"${str}"` : str;
                    };
                    
                    csvContent += `${escape(codigoCup)},${escape(materia)},${escape(cleanNota)}\n`;
                }
                
                resolve(csvContent);
            } catch (err) {
                reject(err);
            }
        };
        reader.onerror = (err) => reject(err);
        reader.readAsArrayBuffer(file);
    });
}

async function handleSubmit() {
    if (!selectedFile.value) {
        serverMessage.value = 'Debes seleccionar un archivo primero.';
        return;
    }

    submitting.value = true;
    serverMessage.value = '';
    fieldErrors.value = {};
    resultado.value = null;

    try {
        let fileToSend = selectedFile.value;
        const fileName = selectedFile.value.name;
        const extension = fileName.split('.').pop().toLowerCase();
        
        if (extension === 'xlsx' || extension === 'xls') {
            serverMessage.value = 'Procesando archivo Excel...';
            const csvData = await parseExcelToCsv(selectedFile.value, numeroExamen.value);
            serverMessage.value = '';
            
            const blob = new Blob([csvData], { type: 'text/csv' });
            fileToSend = new File([blob], fileName.replace(/\.xlsx?$/, '.csv'), { type: 'text/csv' });
        }

        const payload = await uploadEvaluacionesCsv(fileToSend, numeroExamen.value);
        resultado.value = payload.data;
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors || {};
            serverMessage.value = 'El archivo no cumple con los requisitos de la plataforma.';
        } else {
            serverMessage.value = error.message || error.response?.data?.message || 'Error al procesar el archivo.';
        }
    } finally {
        submitting.value = false;
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
            <p class="mt-1 text-sm text-slate-500">Sube un archivo CSV o Excel (.xlsx, .xls) con las calificaciones para registrar o actualizar evaluaciones masivamente.</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Formulario de Subida -->
            <div class="lg:col-span-1">
                <form @submit.prevent="handleSubmit" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Examen a importar</label>
                        <select
                            v-model.number="numeroExamen"
                            class="mb-4 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="1">Examen 1</option>
                            <option :value="2">Examen 2</option>
                            <option :value="3">Examen 3</option>
                        </select>
                        <p v-if="fieldErrors.numero_examen" class="mb-2 text-xs text-red-600">{{ fieldErrors.numero_examen[0] }}</p>

                        <label class="block text-sm font-medium text-slate-700 mb-2">Archivo de Notas (CSV o Excel)</label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
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
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-800">Formatos Soportados</h3>
                    <p class="mt-2 text-xs text-indigo-700"><b>Para CSV:</b> Debe contener columnas separadas por coma:</p>
                    <ul class="mt-1 list-disc pl-4 text-xs text-indigo-700 space-y-1">
                        <li><code>inscripcion_codigo</code></li>
                        <li><code>grupo_materia_id</code></li>
                        <li><code>nota</code></li>
                    </ul>
                    <p class="mt-3 text-xs text-indigo-700"><b>Para Excel (.xlsx):</b> El sistema leerá la pestaña <code>notas_examenes</code> mapeando las columnas <code>codigo_cup</code> (o <code>inscripcion_codigo</code>), <code>materia</code> (se resuelve al grupo del estudiante) y la nota del examen seleccionado.</p>
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
                        <div class="flex items-center gap-6">
                            <button
                                @click="$emit('navigate', '/admin/notas')"
                                class="rounded-lg bg-indigo-50 border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-600 hover:text-white transition"
                            >
                                Ver Notas de Evaluación &rarr;
                            </button>
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
                        <button
                            @click="$emit('navigate', '/admin/notas')"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                        >
                            Ver Notas en Consulta &rarr;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
