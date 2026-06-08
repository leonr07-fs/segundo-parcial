<script setup>
import { onMounted, ref } from 'vue';
import {
    fetchRepostulacionesDocentes,
    aprobarRepostulacionDocente,
    rechazarRepostulacionDocente,
} from '../../api/repostulacion-docente';
import { useToast } from '../../api/toast';

const repostulaciones = ref([]);
const cargando = ref(false);
const estadoFiltro = ref('pendiente');
const toast = useToast();

const estados = ['', 'pendiente', 'aprobada', 'rechazada'];

const cargar = async () => {
    cargando.value = true;
    try {
        const params = estadoFiltro.value ? { estado: estadoFiltro.value } : {};
        const { data } = await fetchRepostulacionesDocentes(params);
        repostulaciones.value = data.data.repostulaciones.data;
    } finally {
        cargando.value = false;
    }
};

const aprobar = async (item) => {
    const confirmado = await toast.confirm({
        title: 'Aprobar repostulación docente',
        message: `Se reactivará el acceso de ${item.docente?.nombres} para la gestión ${item.gestion?.nombre} y se enviarán credenciales por correo.`,
        confirmText: 'Aprobar',
    });

    if (!confirmado) return;

    try {
        const { data } = await aprobarRepostulacionDocente(item.id);
        toast.success(
            'Repostulación aprobada',
            `${data.message}\nCódigo: ${data.data.credenciales.codigo_docente}\nContraseña: ${data.data.credenciales.password_temporal}`,
            { duration: 7000 },
        );
        await cargar();
    } catch (error) {
        toast.error('No se pudo aprobar', error.response?.data?.message || 'Error al aprobar.');
    }
};

const rechazar = async (item) => {
    const observacion = await toast.prompt({
        title: 'Rechazar repostulación',
        message: 'Indique el motivo del rechazo (opcional).',
        confirmText: 'Rechazar',
        tone: 'danger',
    });

    if (observacion === false) return;

    try {
        const { data } = await rechazarRepostulacionDocente(item.id, observacion || null);
        toast.success('Repostulación rechazada', data.message);
        await cargar();
    } catch (error) {
        toast.error('No se pudo rechazar', error.response?.data?.message || 'Error al rechazar.');
    }
};

const estadoClass = (estado) => ({
    pendiente: 'bg-amber-50 text-amber-700 border-amber-200',
    aprobada: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rechazada: 'bg-red-50 text-red-700 border-red-200',
}[estado] || 'bg-slate-50 text-slate-700 border-slate-200');

onMounted(cargar);
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Repostulaciones Docentes</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Solicitudes de docentes de gestiones anteriores que desean participar en la gestión vigente.
                    </p>
                </div>
                <div class="flex gap-3">
                    <select v-model="estadoFiltro" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="cargar">
                        <option v-for="estado in estados" :key="estado" :value="estado">{{ estado || 'todos' }}</option>
                    </select>
                    <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800" @click="cargar">
                        Actualizar
                    </button>
                </div>
            </div>
        </div>

        <div v-if="cargando" class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            Cargando repostulaciones...
        </div>

        <div v-for="item in repostulaciones" :key="item.id" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-bold text-slate-900">
                            {{ item.docente?.nombres }} {{ item.docente?.apellidos || '' }}
                        </h3>
                        <span class="rounded border px-2 py-1 text-xs font-semibold uppercase" :class="estadoClass(item.estado)">
                            {{ item.estado }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">CI: {{ item.docente?.ci }} | {{ item.docente?.correo }}</p>
                    <p class="mt-1 text-sm text-slate-600">Gestión: <span class="font-semibold">{{ item.gestion?.nombre }}</span></p>
                    <p v-if="item.observacion" class="mt-2 text-sm text-red-600">Observación: {{ item.observacion }}</p>
                </div>
                <div v-if="item.estado === 'pendiente'" class="flex flex-wrap gap-2">
                    <button class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="aprobar(item)">
                        Aprobar
                    </button>
                    <button class="rounded-md bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100" @click="rechazar(item)">
                        Rechazar
                    </button>
                </div>
            </div>
        </div>

        <div v-if="!cargando && !repostulaciones.length" class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            No hay repostulaciones docentes con el filtro seleccionado.
        </div>
    </div>
</template>
