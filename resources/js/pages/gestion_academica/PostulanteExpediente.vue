<template>
  <div class="mx-auto max-w-5xl p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Expediente del Postulante</h1>
      <button @click="volver" class="text-gray-600 hover:text-gray-900">Volver a la Bandeja</button>
    </div>

    <div v-if="cargando" class="py-10 text-center">Cargando expediente...</div>

    <div v-else-if="serverMessage" class="rounded bg-red-50 p-4 text-sm text-red-700">
      {{ serverMessage }}
    </div>

    <div v-else-if="postulante" class="space-y-6">
      <div class="rounded bg-white p-6 shadow">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-700">Datos Personales</h2>
          <button @click="editando = !editando" class="rounded bg-blue-50 px-3 py-1 text-sm text-blue-600 hover:bg-blue-100">
            {{ editando ? 'Cancelar' : 'Editar' }}
          </button>
        </div>

        <div v-if="!editando" class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div><span class="block text-sm text-gray-500">Nombre completo</span>{{ nombreCompleto(postulante) }}</div>
          <div><span class="block text-sm text-gray-500">C.I.</span>{{ postulante.ci }} {{ postulante.complemento || '' }}</div>
          <div><span class="block text-sm text-gray-500">Correo</span>{{ postulante.correo }}</div>
          <div><span class="block text-sm text-gray-500">Telefono</span>{{ postulante.telefono || '-' }}</div>
          <div><span class="block text-sm text-gray-500">Fecha de nacimiento</span>{{ formatDate(postulante.fecha_nacimiento) }}</div>
          <div><span class="block text-sm text-gray-500">Genero</span>{{ postulante.genero || '-' }}</div>
          <div><span class="block text-sm text-gray-500">Colegio</span>{{ postulante.colegio_procedencia || '-' }}</div>
          <div><span class="block text-sm text-gray-500">Ciudad</span>{{ postulante.ciudad || '-' }}</div>
          <div class="md:col-span-2"><span class="block text-sm text-gray-500">Direccion</span>{{ postulante.direccion || '-' }}</div>
          <div>
            <span class="block text-sm text-gray-500">Numero de registro</span>
            <span class="font-mono font-semibold text-blue-700">{{ postulante.usuario?.numero_registro || 'Sin habilitar' }}</span>
          </div>
        </div>

        <form v-else @submit.prevent="guardarDatosPersonales" class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label>
            <span class="block text-sm font-medium text-gray-700">Nombres</span>
            <input v-model="form.nombres" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </label>
          <label>
            <span class="block text-sm font-medium text-gray-700">Apellido paterno</span>
            <input v-model="form.apellido_paterno" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </label>
          <label>
            <span class="block text-sm font-medium text-gray-700">Apellido materno</span>
            <input v-model="form.apellido_materno" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </label>
          <label>
            <span class="block text-sm font-medium text-gray-700">Telefono</span>
            <input v-model="form.telefono" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </label>
          <label>
            <span class="block text-sm font-medium text-gray-700">Colegio</span>
            <input v-model="form.colegio_procedencia" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </label>
          <label>
            <span class="block text-sm font-medium text-gray-700">Ciudad</span>
            <input v-model="form.ciudad" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </label>
          <label class="md:col-span-2">
            <span class="block text-sm font-medium text-gray-700">Direccion</span>
            <input v-model="form.direccion" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </label>
          <div class="flex justify-end md:col-span-2">
            <button type="submit" class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700">Guardar Cambios</button>
          </div>
        </form>
      </div>

      <div v-for="inscripcion in postulante.inscripciones" :key="inscripcion.id" class="rounded border-l-4 border-blue-500 bg-white p-6 shadow">
        <div class="mb-2 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-gray-700">
              Gestion: {{ inscripcion.gestion?.nombre ?? '-' }}
              <span class="ml-2 rounded bg-gray-100 px-2 py-1 text-sm text-gray-600">Estado: {{ inscripcion.estado }}</span>
            </h2>
            <p class="text-sm text-gray-500">Codigo CUP: <span class="font-semibold text-gray-700">{{ inscripcion.codigo }}</span></p>
            <p v-if="inscripcion.observacion" class="mt-1 text-sm text-amber-700">Observacion: {{ inscripcion.observacion }}</p>
          </div>
          <button
            v-if="inscripcion.estado !== 'cancelado'"
            type="button"
            class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
            @click="anularInscripcion(inscripcion)"
          >
            Anular postulacion
          </button>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <h3 class="mb-2 border-b pb-1 font-medium text-gray-800">Requisitos y Pago</h3>
            <ul class="space-y-1 text-sm">
              <li>
                Documentos:
                <span v-if="inscripcion.validacion_documental" class="font-semibold" :class="inscripcion.validacion_documental.estado === 'aprobada' ? 'text-green-600' : 'text-amber-600'">
                  {{ inscripcion.validacion_documental.estado }}
                </span>
                <span v-else class="text-gray-400">Sin revision</span>
              </li>
              <li>
                Pagos:
                <span v-if="inscripcion.pagos && inscripcion.pagos.length > 0">
                  {{ inscripcion.pagos[0].estado }} ({{ inscripcion.pagos[0].monto }} Bs)
                </span>
                <span v-else class="text-gray-400">Sin registro</span>
              </li>
            </ul>
          </div>

          <div>
            <h3 class="mb-2 border-b pb-1 font-medium text-gray-800">Opciones de Carrera</h3>
            <ul v-if="inscripcion.opciones_carrera?.length" class="space-y-1 text-sm">
              <li v-for="opcion in inscripcion.opciones_carrera" :key="opcion.id">
                Prioridad {{ opcion.prioridad }}: <span class="font-semibold">{{ opcion.carrera?.nombre ?? '-' }}</span>
              </li>
            </ul>
            <p v-else class="text-sm text-gray-400">Sin opciones registradas</p>
          </div>

          <div>
            <h3 class="mb-2 border-b pb-1 font-medium text-gray-800">Documentos</h3>
            <ul v-if="inscripcion.documentos?.length" class="space-y-2 text-sm">
              <li v-for="documento in inscripcion.documentos" :key="documento.id" class="flex items-center justify-between gap-3">
                <span>{{ labelDocumento(documento.tipo) }}: <strong>{{ documento.estado }}</strong></span>
                <a v-if="documento.archivo_path" :href="urlDocumentoPostulante(documento.id)" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                  Ver
                </a>
              </li>
            </ul>
            <p v-else class="text-sm text-gray-400">Sin documentos registrados</p>
          </div>

          <div v-if="inscripcion.grupos?.length">
            <h3 class="mb-2 border-b pb-1 font-medium text-gray-800">Grupos Asignados</h3>
            <ul class="space-y-1 text-sm">
              <li v-for="grupo in inscripcion.grupos" :key="grupo.id">
                {{ grupo.nombre }} <span class="text-gray-500">({{ grupo.codigo }})</span>
              </li>
            </ul>
          </div>

          <div v-if="inscripcion.resultado_cup">
            <h3 class="mb-2 border-b pb-1 font-medium text-gray-800">Resultado Final</h3>
            <p class="text-sm">Promedio: <span class="font-semibold">{{ inscripcion.resultado_cup.promedio_final }}</span></p>
            <p class="text-sm">
              Estado:
              <span :class="inscripcion.resultado_cup.estado_final === 'aprobado' ? 'text-green-600' : 'text-red-600'">
                {{ inscripcion.resultado_cup.estado_final?.toUpperCase() }}
              </span>
            </p>
            <p v-if="inscripcion.asignacion_carrera" class="mt-1 border-t pt-1 text-sm">
              Carrera Asignada:
              <span class="font-bold text-blue-700">{{ inscripcion.asignacion_carrera.carrera?.nombre ?? '-' }}</span>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// [CU05] Buscar, consultar y actualizar postulante - Vista de expediente completo del postulante

import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToast } from '../../api/toast';
import { urlDocumentoPostulante } from '../../api/documentos';

const postulante = ref(null);
const cargando = ref(true);
const editando = ref(false);
const form = ref({});
const serverMessage = ref('');
const toast = useToast();

const getIdFromUrl = () => {
  const parts = window.location.pathname.split('/');
  return parts[parts.length - 1];
};

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

function labelDocumento(tipo) {
  return {
    ci: 'Carnet de Identidad',
    carnet_identidad: 'Carnet de Identidad',
    titulo_bachiller: 'Titulo de Bachiller',
    libreta_digitalizada: 'Libreta Digitalizada',
    certificado_nacimiento: 'Certificado de Nacimiento',
    fotografia: 'Fotografia',
  }[tipo] ?? tipo;
}

const cargarExpediente = async () => {
  const id = getIdFromUrl();
  serverMessage.value = '';

  try {
    const { data } = await axios.get(`/api/admin/postulantes/${id}`);
    if (data.ok) {
      postulante.value = data.data.postulante;
      form.value = {
        nombres: postulante.value.nombres,
        apellido_paterno: postulante.value.apellido_paterno,
        apellido_materno: postulante.value.apellido_materno,
        telefono: postulante.value.telefono,
        colegio_procedencia: postulante.value.colegio_procedencia,
        ciudad: postulante.value.ciudad,
        direccion: postulante.value.direccion,
      };
    }
  } catch (error) {
    serverMessage.value = error.response?.data?.message ?? 'Error cargando expediente.';
  } finally {
    cargando.value = false;
  }
};

const guardarDatosPersonales = async () => {
  try {
    const { data } = await axios.put(`/api/admin/postulantes/${postulante.value.id}`, form.value);
    if (data.ok) {
      editando.value = false;
      await cargarExpediente();
      toast.success('Cambios guardados', 'Los datos del postulante fueron actualizados correctamente.');
    }
  } catch (error) {
    toast.error('No se pudo guardar', error.response?.data?.message ?? 'Ocurrio un error al actualizar los datos.');
  }
};

const anularInscripcion = async (inscripcion) => {
  const motivo = await toast.prompt({
    title: 'Anular postulacion',
    message: `Indique el motivo para anular la postulacion ${inscripcion.codigo}. Esta accion conserva el historial y marca la postulacion como cancelada.`,
    confirmText: 'Anular',
    cancelText: 'Cancelar',
    placeholder: 'Motivo de anulacion...',
    tone: 'danger',
  });

  if (!motivo || !String(motivo).trim()) {
    return;
  }

  try {
    const { data } = await axios.post(
      `/api/admin/postulantes/${postulante.value.id}/inscripciones/${inscripcion.id}/anular`,
      { motivo: String(motivo).trim() }
    );

    if (data.ok) {
      await cargarExpediente();
      toast.success('Postulacion anulada', data.message);
    }
  } catch (error) {
    const fieldError = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().find(Boolean)
      : null;
    toast.error('No se pudo anular', fieldError || error.response?.data?.message || 'Ocurrio un error al anular la postulacion.');
  }
};

const volver = () => {
  window.location.href = '/admin/postulantes';
};

onMounted(() => {
  cargarExpediente();
});
</script>
