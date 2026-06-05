<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useToast } from '../../api/toast';

const solicitudes = ref([]);
const cargando = ref(false);
const estadoFiltro = ref('');
const toast = useToast();
const imagenModalUrl = ref(null);

const documentUrl = (documento) => {
    if (!documento.archivo_path) return null;
    return `/storage/${documento.archivo_path}`;
};

const abrirImagen = (url) => {
    imagenModalUrl.value = url;
};

const cerrarImagen = () => {
    imagenModalUrl.value = null;
};

const estados = ['', 'pendiente', 'observada', 'aprobada', 'rechazada'];

const cargarSolicitudes = async () => {
    cargando.value = true;
    try {
        const params = estadoFiltro.value ? { estado: estadoFiltro.value } : {};
        const { data } = await axios.get('/api/admin/solicitudes-docentes', { params });
        solicitudes.value = data.data.solicitudes.data;
    } finally {
        cargando.value = false;
    }
};

const revisarDocumento = async (documento, estado) => {
    const observacion = estado === 'aprobado'
        ? null
        : await toast.prompt({
            title: 'Observacion del documento',
            message: 'Detalle el motivo para observar o rechazar este documento.',
            confirmText: 'Guardar observacion',
        });

    if (observacion === false) return;

    await axios.put(`/api/admin/documentos-docentes/${documento.id}/revisar`, { estado, observacion });
    toast.success('Documento revisado', 'El dictamen del documento fue guardado correctamente.');
    await cargarSolicitudes();
};

const aprobarSolicitud = async (solicitud) => {
    const confirmado = await toast.confirm({
        title: 'Aprobar solicitud docente',
        message: 'Se creara el docente, el usuario y se enviaran credenciales al correo.',
        confirmText: 'Aprobar solicitud',
    });

    if (!confirmado) return;

    try {
        const { data } = await axios.post(`/api/admin/solicitudes-docentes/${solicitud.id}/aprobar`);
        toast.success(
            'Solicitud aprobada',
            `${data.message}\nCodigo: ${data.data.credenciales.codigo_docente}\nContrasena temporal: ${data.data.credenciales.password_temporal}`,
            { duration: 7000 }
        );
        await cargarSolicitudes();
    } catch (error) {
        toast.error('No se pudo aprobar', error.response?.data?.message || 'No se pudo aprobar la solicitud');
    }
};

const cambiarEstadoSolicitud = async (solicitud, accion) => {
    const observacion = await toast.prompt({
        title: 'Observacion',
        message: 'Registre la observacion para continuar con esta accion.',
        confirmText: 'Guardar',
    });

    if (observacion === false) return;

    await axios.post(`/api/admin/solicitudes-docentes/${solicitud.id}/${accion}`, { observacion });
    toast.success('Solicitud actualizada', 'El estado de la solicitud fue actualizado correctamente.');
    await cargarSolicitudes();
};

const estadoClass = (estado) => {
    return {
        pendiente: 'bg-amber-50 text-amber-700 border-amber-200',
        observada: 'bg-blue-50 text-blue-700 border-blue-200',
        aprobada: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        rechazada: 'bg-red-50 text-red-700 border-red-200',
    }[estado] || 'bg-slate-50 text-slate-700 border-slate-200';
};

onMounted(cargarSolicitudes);
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Solicitudes Docentes</h2>
                    <p class="mt-1 text-sm text-slate-500">Revise profesion, materia y documentos obligatorios antes de aprobar.</p>
                </div>
                <div class="flex gap-3">
                    <select v-model="estadoFiltro" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="cargarSolicitudes">
                        <option v-for="estado in estados" :key="estado" :value="estado">{{ estado || 'todos' }}</option>
                    </select>
                    <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800" @click="cargarSolicitudes">
                        Actualizar
                    </button>
                </div>
            </div>
        </div>

        <div v-if="cargando" class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            Cargando solicitudes...
        </div>

        <div v-for="solicitud in solicitudes" :key="solicitud.id" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-bold text-slate-900">{{ solicitud.nombres }} {{ solicitud.apellidos || '' }}</h3>
                            <span class="rounded border px-2 py-1 text-xs font-semibold uppercase" :class="estadoClass(solicitud.estado)">
                                {{ solicitud.estado }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">CI: {{ solicitud.ci }} | {{ solicitud.correo }} | {{ solicitud.telefono || '-' }}</p>
                        <p class="mt-1 text-sm text-slate-600">Materia: <span class="font-semibold">{{ solicitud.materia?.nombre }}</span></p>
                        <p class="mt-1 text-sm text-slate-600">Profesion: <span class="font-semibold">{{ solicitud.profesion }}</span></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="aprobarSolicitud(solicitud)">
                            Aprobar
                        </button>
                        <button class="rounded-md bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100" @click="cambiarEstadoSolicitud(solicitud, 'observar')">
                            Observar
                        </button>
                        <button class="rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100" @click="cambiarEstadoSolicitud(solicitud, 'rechazar')">
                            Rechazar
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold text-slate-500 uppercase">Documento</th>
                            <th class="px-6 py-3 text-left font-semibold text-slate-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-left font-semibold text-slate-500 uppercase">Archivo</th>
                            <th class="px-6 py-3 text-left font-semibold text-slate-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="documento in solicitud.documentos" :key="documento.id">
                            <td class="px-6 py-3 font-semibold text-slate-800">{{ documento.tipo }}</td>
                            <td class="px-6 py-3">
                                <span class="rounded border px-2 py-1 text-xs font-semibold uppercase" :class="estadoClass(documento.estado)">
                                    {{ documento.estado }}
                                </span>
                            </td>
                             <td class="px-6 py-3 text-slate-500">
                                <div class="flex items-center gap-3">
                                    <!-- Miniatura si es imagen -->
                                    <img 
                                        v-if="documento.archivo_path && (documento.archivo_path.endsWith('.png') || documento.archivo_path.endsWith('.jpg') || documento.archivo_path.endsWith('.jpeg'))"
                                        :src="documentUrl(documento)" 
                                        class="h-10 w-10 rounded border object-cover cursor-pointer hover:opacity-80 transition shadow-sm"
                                        @click="abrirImagen(documentUrl(documento))"
                                        title="Click para ampliar"
                                    />
                                    <a
                                        v-if="documento.archivo_path"
                                        :href="documentUrl(documento)"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs font-semibold text-cyan-700 hover:underline flex items-center gap-1"
                                    >
                                        <span>Ver completo</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    <span v-else class="text-xs text-slate-400">Sin archivo</span>
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <button class="rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100" @click="revisarDocumento(documento, 'aprobado')">Aprobar</button>
                                    <button class="rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100" @click="revisarDocumento(documento, 'observado')">Observar</button>
                                    <button class="rounded bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-100" @click="revisarDocumento(documento, 'rechazado')">Rechazar</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="!cargando && !solicitudes.length" class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            No hay solicitudes docentes.
        </div>

        <!-- Modal de Vista Previa de Imagen -->
        <div v-if="imagenModalUrl" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @click="cerrarImagen">
            <div class="relative max-h-[90vh] max-w-[90vw] overflow-hidden rounded-xl bg-white p-2 shadow-2xl" @click.stop>
                <button 
                    type="button" 
                    class="absolute right-3 top-3 rounded-full bg-slate-800/80 p-2 text-white hover:bg-slate-700 transition"
                    @click="cerrarImagen"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <img :src="imagenModalUrl" class="max-h-[80vh] max-w-full rounded-lg object-contain" />
            </div>
        </div>
    </div>
</template>
