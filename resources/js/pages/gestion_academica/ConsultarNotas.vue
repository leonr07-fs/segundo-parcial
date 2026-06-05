<template>
  <div class="p-6 max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Consulta de Notas de Evaluación</h1>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-6 rounded shadow mb-6">
      <h2 class="text-lg font-semibold text-gray-700 mb-4">Seleccionar Grupo y Materia</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Grupo</label>
          <select v-model="grupoSeleccionado" required @change="cargarMaterias" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="" disabled>Seleccione un grupo...</option>
            <option v-for="g in grupos" :key="g.id" :value="g.id">{{ g.codigo }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Materia (Docente)</label>
          <select v-model="materiaSeleccionada" required @change="cargarNotas" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="" disabled>Seleccione materia...</option>
            <option v-for="m in materias" :key="m.pivot.id" :value="m.pivot.id">
              {{ m.codigo }} - {{ m.nombre }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Resultados -->
    <div v-if="grupoMateriaInfo" class="bg-white rounded shadow overflow-hidden">
      <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
        <h3 class="font-semibold text-gray-700">
          Materia: <span class="font-bold text-blue-700">{{ grupoMateriaInfo.materia }}</span> | 
          Docente: <span class="font-bold text-gray-900">{{ grupoMateriaInfo.docente }}</span> | 
          Grupo: {{ grupoMateriaInfo.grupo_codigo }}
        </h3>
        <button @click="exportarActa" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm font-medium">
          Exportar Acta (CSV)
        </button>
      </div>
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CI Postulante</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre Completo</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Examen 1</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Examen 2</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Examen 3</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Promedio</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="estudiante in estudiantes" :key="estudiante.inscripcion_id">
            <td class="px-6 py-4 font-medium">{{ estudiante.postulante_ci }}</td>
            <td class="px-6 py-4">{{ estudiante.postulante_nombre }}</td>
            <td class="px-6 py-4 text-center">{{ estudiante.examen_1 ?? '-' }}</td>
            <td class="px-6 py-4 text-center">{{ estudiante.examen_2 ?? '-' }}</td>
            <td class="px-6 py-4 text-center">{{ estudiante.examen_3 ?? '-' }}</td>
            <td class="px-6 py-4 text-center font-bold" :class="getPromedioColor(estudiante.promedio)">
              {{ estudiante.promedio ?? '-' }}
            </td>
            <td class="px-6 py-4 text-center">
              <span :class="getEstadoBadgeClass(estudiante.estado)">
                {{ estudiante.estado.toUpperCase() }}
              </span>
            </td>
          </tr>
          <tr v-if="!estudiantes.length">
            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No hay estudiantes inscritos en este grupo</td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const grupos = ref([]);
const materias = ref([]);
const estudiantes = ref([]);
const grupoMateriaInfo = ref(null);

const grupoSeleccionado = ref('');
const materiaSeleccionada = ref('');

const cargarGrupos = async () => {
  const { data } = await axios.get('/api/grupos');
  if (data.ok) grupos.value = data.data.grupos;
};

const cargarMaterias = async () => {
  materiaSeleccionada.value = '';
  estudiantes.value = [];
  grupoMateriaInfo.value = null;
  if (!grupoSeleccionado.value) return;

  const { data } = await axios.get(`/api/grupos/${grupoSeleccionado.value}/materias`);
  if (data.ok) materias.value = data.data.materias;
};

const cargarNotas = async () => {
  if (!materiaSeleccionada.value) return;
  const { data } = await axios.get(`/api/evaluaciones/grupo-materia/${materiaSeleccionada.value}`);
  if (data.ok) {
    grupoMateriaInfo.value = data.data.grupo_materia;
    estudiantes.value = data.data.estudiantes;
  }
};

const getPromedioColor = (promedio) => {
  if (promedio === null) return 'text-gray-500';
  if (promedio >= 51) return 'text-green-600';
  return 'text-red-600';
};

const getEstadoBadgeClass = (estado) => {
  switch (estado) {
    case 'aprobado': return 'bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold';
    case 'reprobado': return 'bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-semibold';
    case 'observado': return 'bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-semibold';
    default: return 'bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-semibold';
  }
};

const exportarActa = () => {
  if (!materiaSeleccionada.value) return;
  // Abre la URL de exportación en una nueva pestaña para descargar el CSV
  window.open(`/api/reportes/evaluaciones/${materiaSeleccionada.value}/exportar`, '_blank');
};

onMounted(() => {
  cargarGrupos();
});
</script>
