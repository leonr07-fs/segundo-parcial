<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Asignacion de Cupos</h1>
        <p class="mt-1 text-sm text-gray-500">
          Configura cupos por carrera y distribuyelos entre postulantes aprobados por estricto orden de merito.
        </p>
      </div>
      <div class="flex flex-wrap gap-3">
        <select
          v-model.number="gestionSeleccionadaId"
          @change="cargarDatos"
          class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
        >
          <option v-for="gestion in gestiones" :key="gestion.id" :value="gestion.id">
            {{ gestion.nombre }} - {{ gestion.estado }}
          </option>
        </select>
        <button
          type="button"
          @click="cargarDatos"
          class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
        >
          Actualizar
        </button>
        <button
          type="button"
          @click="ejecutar"
          :disabled="ejecutando || !cuposListos"
          class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:bg-blue-300"
        >
          <svg v-if="ejecutando" class="-ml-1 mr-2 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          Ejecutar Asignacion
        </button>
      </div>
    </div>

    <div class="rounded-lg border border-gray-100 bg-white shadow">
      <div class="border-b border-gray-100 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-900">Cupos por gestion y carrera</h2>
        <p class="mt-1 text-sm text-gray-500">
          Antes de asignar, registra cuantos cupos tiene cada carrera habilitada en la gestion seleccionada.
        </p>
      </div>

      <div v-if="cargando" class="p-6 text-sm text-gray-500">
        Cargando carreras y cupos...
      </div>
      <div v-else-if="carreras.length === 0" class="p-6 text-sm text-gray-500">
        No hay carreras activas para configurar cupos.
      </div>

      <form v-else @submit.prevent="guardarCupos" class="divide-y divide-gray-100">
        <div
          v-for="carrera in carreras"
          :key="carrera.id"
          class="grid gap-3 px-6 py-4 md:grid-cols-[1fr_160px_190px] md:items-center"
        >
          <div>
            <p class="text-sm font-semibold text-gray-900">{{ carrera.nombre }}</p>
            <p class="text-xs text-gray-500">{{ carrera.codigo }}</p>
          </div>
          <div>
            <label class="sr-only" :for="`cupo-${carrera.id}`">Cupos</label>
            <input
              :id="`cupo-${carrera.id}`"
              v-model.number="formCupos[carrera.id]"
              type="number"
              min="0"
              class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
              placeholder="0"
            >
          </div>
          <div class="text-sm text-gray-500">
            <span v-if="cupoPorCarrera[carrera.id]">
              Disponible:
              <strong class="text-gray-900">{{ cupoPorCarrera[carrera.id].cupo_disponible }}</strong>
              / {{ cupoPorCarrera[carrera.id].cupo_total }}
            </span>
            <span v-else class="font-medium text-amber-600">Sin cupo configurado</span>
          </div>
        </div>

        <div class="flex flex-col gap-3 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm text-gray-500">
            El sistema ordena por promedio final: intenta 1ra opcion, luego 2da opcion y descuenta el cupo disponible.
          </p>
          <button
            type="submit"
            :disabled="guardandoCupos"
            class="inline-flex items-center justify-center rounded-md border border-transparent bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:bg-emerald-300"
          >
            {{ guardandoCupos ? 'Guardando...' : 'Guardar cupos' }}
          </button>
        </div>
      </form>
    </div>

    <div v-if="!cuposListos" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
      Configura los cupos de todas las carreras activas antes de ejecutar la asignacion final.
    </div>

    <div v-if="stats" class="rounded-md border border-blue-200 bg-blue-50 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-blue-800">Resultado de la ultima ejecucion</h3>
          <div class="mt-2 text-sm text-blue-700">
            <ul class="list-disc space-y-1 pl-5">
              <li>Postulantes procesados: {{ stats.procesados }}</li>
              <li>Asignados en 1ra opcion: {{ stats.asignados_1ra }}</li>
              <li>Asignados en 2da opcion: {{ stats.asignados_2da }}</li>
              <li>Quedaron sin cupo: {{ stats.sin_cupo }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <div v-for="cupo in cupos" :key="cupo.id" class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow">
        <div class="px-4 py-5 sm:p-6">
          <dt class="truncate text-sm font-medium text-gray-500">
            {{ cupo.carrera.nombre }}
          </dt>
          <dd class="mt-1 text-3xl font-semibold text-gray-900">
            {{ cupo.cupo_disponible }} <span class="text-lg font-normal text-gray-500">/ {{ cupo.cupo_total }}</span>
          </dd>
          <div class="mt-4">
            <div class="h-2 w-full rounded-full bg-gray-200">
              <div
                class="h-2 rounded-full bg-blue-600"
                :style="`width: ${porcentajeOcupacion(cupo)}%`"
              />
            </div>
            <p class="mt-2 text-right text-xs text-gray-500">Ocupacion</p>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-md">
      <div v-if="cargando" class="p-12 text-center text-gray-500">
        <p>Cargando datos de asignacion...</p>
      </div>

      <div v-else-if="asignaciones.length === 0" class="p-12 text-center text-gray-500">
        <p class="text-lg font-medium">Aun no hay asignaciones</p>
        <p class="mt-1 text-sm">Guarda cupos y haz clic en "Ejecutar Asignacion" para procesar a los postulantes aprobados.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Merito</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Postulante</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Promedio</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Asignacion final</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="(asig, index) in asignaciones" :key="asig.id" class="hover:bg-gray-50">
              <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                #{{ index + 1 }}
              </td>
              <td class="whitespace-nowrap px-6 py-4">
                <div class="text-sm font-medium text-gray-900">
                  {{ asig.inscripcion?.postulante?.nombres }} {{ asig.inscripcion?.postulante?.apellidos }}
                </div>
                <div class="text-sm text-gray-500">CUP-{{ asig.inscripcion?.id.toString().padStart(5, '0') }}</div>
              </td>
              <td class="whitespace-nowrap px-6 py-4">
                <div class="text-sm font-bold text-gray-900">{{ asig.promedio_usado }}</div>
              </td>
              <td class="whitespace-nowrap px-6 py-4">
                <div v-if="asig.carrera">
                  <div class="text-sm font-medium text-gray-900">{{ asig.carrera.nombre }}</div>
                  <div class="text-xs text-gray-500">
                    Logrado en su {{ asig.opcion_prioridad }} opcion
                  </div>
                </div>
                <div v-else class="text-sm text-red-500">Ninguna</div>
              </td>
              <td class="whitespace-nowrap px-6 py-4">
                <span
                  :class="[
                    'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                    asig.estado === 'asignado' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                  ]"
                >
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
// [CU12] Asignar carrera por cupos - Panel de control para definir cupos y correr el algoritmo

import { computed, ref, onMounted } from 'vue';
import { getAsignaciones, ejecutarAsignacion, guardarCuposCarrera } from '../../api/asignacion-carrera';
import { useToast } from '../../api/toast';

const carreras = ref([]);
const cupos = ref([]);
const gestiones = ref([]);
const gestionSeleccionadaId = ref(null);
const formCupos = ref({});
const asignaciones = ref([]);
const cargando = ref(true);
const ejecutando = ref(false);
const guardandoCupos = ref(false);
const stats = ref(null);
const toast = useToast();

const cupoPorCarrera = computed(() => cupos.value.reduce((map, cupo) => {
  map[cupo.carrera_id] = cupo;
  return map;
}, {}));

const cuposListos = computed(() => (
  carreras.value.length > 0
  && carreras.value.every((carrera) => cupoPorCarrera.value[carrera.id])
));

const cargarDatos = async () => {
  cargando.value = true;
  try {
    const response = await getAsignaciones(gestionSeleccionadaId.value);
    if (response.ok) {
      gestiones.value = response.data.gestiones || [];
      gestionSeleccionadaId.value = response.data.gestion?.id ?? gestionSeleccionadaId.value;
      carreras.value = response.data.carreras || [];
      cupos.value = response.data.cupos || [];
      asignaciones.value = response.data.asignaciones.data;
      formCupos.value = carreras.value.reduce((form, carrera) => {
        form[carrera.id] = cupoPorCarrera.value[carrera.id]?.cupo_total ?? 0;
        return form;
      }, {});
    }
  } catch (error) {
    console.error('Error cargando asignaciones:', error);
  } finally {
    cargando.value = false;
  }
};

const guardarCupos = async () => {
  guardandoCupos.value = true;
  try {
    const payload = carreras.value.map((carrera) => ({
      carrera_id: carrera.id,
      cupo_total: Number(formCupos.value[carrera.id] ?? 0),
    }));
    const response = await guardarCuposCarrera(payload, gestionSeleccionadaId.value);
    if (response.ok) {
      cupos.value = response.data.cupos;
      toast.success('Cupos guardados', 'La gestion ya tiene cupos por carrera configurados.');
      await cargarDatos();
    }
  } catch (error) {
    console.error('Error guardando cupos:', error);
    toast.error('No se pudo guardar', 'Verifica que todos los cupos sean numeros validos.');
  } finally {
    guardandoCupos.value = false;
  }
};

const ejecutar = async () => {
  const confirmado = await toast.confirm({
    title: 'Ejecutar asignacion automatica',
    message: 'Esta accion procesara los cupos disponibles por orden de merito y no se podra deshacer.',
    confirmText: 'Ejecutar asignacion',
    tone: 'danger',
  });

  if (!confirmado) return;

  ejecutando.value = true;
  stats.value = null;
  try {
    const response = await ejecutarAsignacion(gestionSeleccionadaId.value);
    if (response.ok) {
      stats.value = response.stats;
      toast.success('Asignacion completada', 'Los cupos fueron procesados correctamente.');
      await cargarDatos();
    }
  } catch (error) {
    console.error('Error ejecutando asignaciones:', error);
    toast.error('No se pudo asignar', 'Ocurrio un error al procesar las asignaciones.');
  } finally {
    ejecutando.value = false;
  }
};

const porcentajeOcupacion = (cupo) => {
  if (!cupo.cupo_total) return 0;
  return ((cupo.cupo_total - cupo.cupo_disponible) / cupo.cupo_total) * 100;
};

onMounted(() => {
  cargarDatos();
});
</script>
