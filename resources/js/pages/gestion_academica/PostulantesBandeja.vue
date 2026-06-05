<template>
  <div class="p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Bandeja de Postulantes</h1>
    </div>

    <div class="mb-6 rounded bg-white p-4 shadow">
      <div class="flex flex-col gap-4 md:flex-row md:items-end">
        <label class="block flex-1">
          <span class="text-sm font-medium text-gray-700">Buscar por CI, nombre, correo, codigo CUP o registro</span>
          <input
            v-model="filtros.search"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            placeholder="Ej. Juan Perez, CUP-2026 o EST-2026"
            @keyup.enter="buscar"
          >
        </label>
        <button @click="buscar" class="rounded bg-blue-600 px-5 py-2 text-white hover:bg-blue-700">
          Buscar
        </button>
      </div>
    </div>

    <div v-if="serverMessage" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">
      {{ serverMessage }}
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">CI</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Postulante</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Correo</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Gestion</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Registro</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="loading">
            <td colspan="7" class="px-6 py-8 text-center text-gray-500">Cargando postulantes...</td>
          </tr>

          <tr v-for="postulante in postulantes" v-else :key="postulante.id">
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ postulante.ci }}</td>
            <td class="px-6 py-4">
              <div class="font-medium text-gray-900">{{ nombreCompleto(postulante) }}</div>
              <div class="text-xs text-gray-500">{{ inscripcionActual(postulante)?.codigo ?? 'Sin codigo CUP' }}</div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ postulante.correo }}</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
              {{ inscripcionActual(postulante)?.gestion?.nombre ?? 'Sin inscripcion' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <div class="flex flex-col gap-1">
                <span class="w-fit rounded-full px-3 py-1 text-xs font-semibold" :class="estadoClass(inscripcionActual(postulante)?.estado)">
                  {{ inscripcionActual(postulante)?.estado ?? 'sin_inscripcion' }}
                </span>
                <span v-if="inscripcionActual(postulante)?.validacion_documental" class="text-xs text-gray-500">
                  Doc.: {{ inscripcionActual(postulante).validacion_documental.estado }}
                </span>
              </div>
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <span v-if="postulante.usuario?.numero_registro" class="font-mono text-sm font-semibold text-blue-700">
                {{ postulante.usuario.numero_registro }}
              </span>
              <span v-else class="text-sm text-gray-400">Sin habilitar</span>
            </td>
            <td class="whitespace-nowrap px-6 py-4">
              <button @click="verExpediente(postulante.id)" class="text-sm font-medium text-blue-600 hover:text-blue-900">
                Expediente
              </button>
            </td>
          </tr>

          <tr v-if="!loading && postulantes.length === 0">
            <td colspan="7" class="px-6 py-8 text-center text-gray-500">No hay postulantes encontrados.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
// [CU05] Buscar, consultar y actualizar postulante - Grilla de búsqueda y control de inscripciones

import { ref, onMounted } from 'vue';
import axios from 'axios';

const postulantes = ref([]);
const loading = ref(false);
const serverMessage = ref('');
const filtros = ref({
  search: '',
  gestion_id: ''
});

function nombreCompleto(postulante) {
  return [
    postulante.nombres,
    postulante.apellido_paterno,
    postulante.apellido_materno,
  ].filter(Boolean).join(' ') || '-';
}

function inscripcionActual(postulante) {
  return postulante.inscripciones?.[0] ?? null;
}

function estadoClass(estado) {
  return {
    documentos_aprobados: 'bg-emerald-100 text-emerald-700',
    prepostulado: 'bg-blue-100 text-blue-700',
    documentos_pendientes: 'bg-amber-100 text-amber-700',
    rechazado: 'bg-red-100 text-red-700',
  }[estado] ?? 'bg-gray-100 text-gray-600';
}

const buscar = async () => {
  loading.value = true;
  serverMessage.value = '';

  try {
    const { data } = await axios.get('/api/admin/postulantes', { params: filtros.value });
    if (data.ok) {
      postulantes.value = data.data.postulantes.data;
    }
  } catch (error) {
    serverMessage.value = error.response?.data?.message ?? 'Error buscando postulantes.';
  } finally {
    loading.value = false;
  }
};

const verExpediente = (id) => {
  window.location.href = `/admin/postulantes/${id}`;
};

onMounted(() => {
  buscar();
});
</script>
