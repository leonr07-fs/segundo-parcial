<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Asignación de Cupos</h1>
        <p class="mt-1 text-sm text-gray-500">
          Distribuye los cupos disponibles entre los postulantes aprobados por estricto orden de mérito.
        </p>
      </div>
      <div class="space-x-3">
        <button 
          @click="cargarDatos"
          class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
        >
          Actualizar
        </button>
        <button 
          @click="ejecutar"
          :disabled="ejecutando"
          class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300"
        >
          <svg v-if="ejecutando" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Ejecutar Asignación
        </button>
      </div>
    </div>

    <!-- Resultados Resumen -->
    <div v-if="stats" class="bg-blue-50 border border-blue-200 rounded-md p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-blue-800">Resultado de la última ejecución</h3>
          <div class="mt-2 text-sm text-blue-700">
            <ul class="list-disc pl-5 space-y-1">
              <li>Postulantes procesados: {{ stats.procesados }}</li>
              <li>Asignados en 1ra opción: {{ stats.asignados_1ra }}</li>
              <li>Asignados en 2da opción: {{ stats.asignados_2da }}</li>
              <li>Quedaron sin cupo: {{ stats.sin_cupo }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Cupos Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div v-for="cupo in cupos" :key="cupo.id" class="bg-white overflow-hidden shadow rounded-lg border border-gray-100">
        <div class="px-4 py-5 sm:p-6">
          <dt class="text-sm font-medium text-gray-500 truncate">
            {{ cupo.carrera.nombre }}
          </dt>
          <dd class="mt-1 text-3xl font-semibold text-gray-900">
            {{ cupo.cupo_disponible }} <span class="text-lg text-gray-500 font-normal">/ {{ cupo.cupo_total }}</span>
          </dd>
          <div class="mt-4">
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div 
                class="bg-blue-600 h-2 rounded-full" 
                :style="`width: ${((cupo.cupo_total - cupo.cupo_disponible) / cupo.cupo_total) * 100}%`"
              ></div>
            </div>
            <p class="text-xs text-gray-500 mt-2 text-right">Ocupación</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-100">
      <div v-if="cargando" class="p-12 text-center text-gray-500">
        <p>Cargando datos de asignación...</p>
      </div>

      <div v-else-if="asignaciones.length === 0" class="p-12 text-center text-gray-500">
        <p class="text-lg font-medium">Aún no hay asignaciones</p>
        <p class="text-sm mt-1">Haz clic en "Ejecutar Asignación" para procesar a los postulantes aprobados.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mérito</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Postulante</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promedio</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asignación Final</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(asig, index) in asignaciones" :key="asig.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                #{{ index + 1 }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  {{ asig.inscripcion?.postulante?.nombres }} {{ asig.inscripcion?.postulante?.apellidos }}
                </div>
                <div class="text-sm text-gray-500">CUP-{{ asig.inscripcion?.id.toString().padStart(5, '0') }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-bold text-gray-900">{{ asig.promedio_usado }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div v-if="asig.carrera">
                  <div class="text-sm font-medium text-gray-900">{{ asig.carrera.nombre }}</div>
                  <div class="text-xs text-gray-500">
                    Logrado en su {{ asig.opcion_prioridad }}° opción
                  </div>
                </div>
                <div v-else class="text-sm text-red-500">Ninguna</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="[
                  'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                  asig.estado === 'asignado' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ asig.estado.toUpperCase().replace('_', ' ') }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getAsignaciones, ejecutarAsignacion } from '../../api/asignacion-carrera';

const cupos = ref([]);
const asignaciones = ref([]);
const cargando = ref(true);
const ejecutando = ref(false);
const stats = ref(null);

const cargarDatos = async () => {
  cargando.value = true;
  try {
    const response = await getAsignaciones();
    if (response.ok) {
      cupos.value = response.data.cupos;
      asignaciones.value = response.data.asignaciones.data; // Para paginación simple
    }
  } catch (error) {
    console.error("Error cargando asignaciones:", error);
  } finally {
    cargando.value = false;
  }
};

const ejecutar = async () => {
  if (!confirm('¿Estás seguro de ejecutar la asignación automática? Esta acción no se puede deshacer.')) return;
  
  ejecutando.value = true;
  stats.value = null;
  try {
    const response = await ejecutarAsignacion();
    if (response.ok) {
      stats.value = response.stats;
      await cargarDatos(); // Refrescar tablas y cupos
    }
  } catch (error) {
    console.error("Error ejecutando asignaciones:", error);
    alert('Ocurrió un error al procesar las asignaciones.');
  } finally {
    ejecutando.value = false;
  }
};

onMounted(() => {
  cargarDatos();
});
</script>
