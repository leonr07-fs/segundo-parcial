<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Validaciones Académicas</h1>
        <p class="mt-1 text-sm text-gray-500">
          Supervisión de evaluaciones incompletas u observadas que requieren atención.
        </p>
      </div>
      <button 
        @click="cargarValidaciones"
        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
      >
        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Actualizar Lista
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-100">
      <div v-if="cargando" class="p-12 text-center text-gray-500">
        <svg class="animate-spin h-8 w-8 mx-auto text-blue-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p>Cargando evaluaciones...</p>
      </div>

      <div v-else-if="evaluaciones.length === 0" class="p-12 text-center text-gray-500">
        <div class="bg-green-100 text-green-700 h-16 w-16 mx-auto rounded-full flex items-center justify-center mb-4">
          <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <p class="text-lg font-medium">Todo está en orden</p>
        <p class="text-sm mt-1">No hay evaluaciones incompletas ni observadas en este momento.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Postulante</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Materia</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notas (E1, E2, E3)</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Observación</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="ev in evaluaciones" :key="ev.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="text-sm font-medium text-gray-900">
                    {{ ev.inscripcion?.postulante?.nombres }} {{ ev.inscripcion?.postulante?.apellidos }}
                  </div>
                </div>
                <div class="text-sm text-gray-500">CUP-{{ ev.inscripcion?.id.toString().padStart(5, '0') }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ ev.grupo_materia?.materia?.nombre }}</div>
                <div class="text-sm text-gray-500">Sigla: {{ ev.grupo_materia?.materia?.codigo }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                <span class="inline-block w-8 text-center" :class="!ev.examen_1 ? 'text-gray-300' : ''">{{ ev.examen_1 ?? '-' }}</span> | 
                <span class="inline-block w-8 text-center" :class="!ev.examen_2 ? 'text-gray-300' : ''">{{ ev.examen_2 ?? '-' }}</span> | 
                <span class="inline-block w-8 text-center" :class="!ev.examen_3 ? 'text-gray-300' : ''">{{ ev.examen_3 ?? '-' }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="[
                  'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                  ev.estado === 'observado' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'
                ]">
                  {{ ev.estado.toUpperCase() }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" :title="ev.observacion">
                {{ ev.observacion }}
              </td>
            </tr>
          </tbody>
        </table>
        
        <!-- Paginador simple -->
        <div v-if="paginacion.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Mostrando <span class="font-medium">{{ paginacion.from }}</span> a <span class="font-medium">{{ paginacion.to }}</span> de <span class="font-medium">{{ paginacion.total }}</span> resultados
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <button 
                  @click="cambiarPagina(paginacion.current_page - 1)" 
                  :disabled="paginacion.current_page === 1"
                  class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                >
                  <span class="sr-only">Anterior</span>
                  <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </button>
                <button 
                  @click="cambiarPagina(paginacion.current_page + 1)" 
                  :disabled="paginacion.current_page === paginacion.last_page"
                  class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                >
                  <span class="sr-only">Siguiente</span>
                  <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                  </svg>
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getValidacionesPendientes } from '../../api/validacion-academica';

const evaluaciones = ref([]);
const cargando = ref(true);
const paginacion = ref({});

const cargarValidaciones = async (page = 1) => {
  cargando.value = true;
  try {
    const response = await getValidacionesPendientes(page);
    if (response.ok) {
      evaluaciones.value = response.data.data;
      paginacion.value = {
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        from: response.data.from,
        to: response.data.to,
        total: response.data.total
      };
    }
  } catch (error) {
    console.error("Error cargando validaciones:", error);
  } finally {
    cargando.value = false;
  }
};

const cambiarPagina = (page) => {
  if (page >= 1 && page <= paginacion.value.last_page) {
    cargarValidaciones(page);
  }
};

onMounted(() => {
  cargarValidaciones();
});
</script>
