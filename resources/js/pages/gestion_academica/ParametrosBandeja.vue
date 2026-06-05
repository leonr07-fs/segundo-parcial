<template>
  <div class="p-6 max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Parámetros y Configuración</h1>
    </div>

    <!-- Pestañas de navegación -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="-mb-px flex space-x-8">
        <button 
          v-for="tab in ['Gestiones', 'Materias', 'Aulas', 'Grupos', 'Docentes', 'Asignación', 'Automatica']" 
          :key="tab"
          @click="activeTab = tab"
          :class="[
            activeTab === tab 
              ? 'border-blue-500 text-blue-600' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
          ]"
        >
          {{ tab }}
        </button>
      </nav>
    </div>

    <!-- Contenido de Gestiones -->
    <div v-if="activeTab === 'Gestiones'" class="space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Añadir Nueva Gestión</h2>
        <form @submit.prevent="crearGestion" class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombre</label>
            <input v-model="formGestion.nombre" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Semestre 1-2026">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Año</label>
            <input v-model="formGestion.anio" required type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="2026">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Periodo</label>
            <input v-model="formGestion.periodo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="1">
          </div>
          <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full md:w-auto">Guardar Gestión</button>
          </div>
        </form>
      </div>
      <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Año</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="g in todasLasGestiones" :key="g.id">
              <td class="px-6 py-4 font-medium">{{ g.nombre }}</td>
              <td class="px-6 py-4">{{ g.anio }}</td>
              <td class="px-6 py-4">
                <span :class="{
                  'bg-green-100 text-green-800': g.estado === 'inscripcion',
                  'bg-gray-100 text-gray-800': g.estado === 'finalizado',
                  'bg-blue-100 text-blue-800': g.estado === 'planificado',
                  'bg-amber-100 text-amber-800': g.estado === 'inhabilitada',
                  'bg-slate-100 text-slate-800': g.estado === 'cerrada' || g.estado === 'en_curso'
                }" class="px-2 py-1 rounded text-xs font-semibold uppercase">
                  {{ g.estado }}
                </span>
              </td>
              <td class="px-6 py-4">
                <button v-if="g.estado !== 'inscripcion' && g.estado !== 'cerrada' && g.estado !== 'inhabilitada' && g.estado !== 'en_curso'" @click="habilitarGestion(g.id)" class="text-sm bg-indigo-50 text-indigo-600 px-3 py-1 rounded border border-indigo-200 hover:bg-indigo-100 font-medium">
                  Habilitar Inscripciones
                </button>
                <button v-if="g.estado === 'inscripcion'" @click="cerrarGestion(g.id)" class="ml-2 text-sm bg-amber-50 text-amber-700 px-3 py-1 rounded border border-amber-200 hover:bg-amber-100 font-medium">
                  Cerrar Inscripciones
                </button>
                <button v-if="g.estado === 'inhabilitada' || g.estado === 'en_curso'" @click="cerrarGestionFinal(g.id)" class="ml-2 text-sm bg-red-50 text-red-700 px-3 py-1 rounded border border-red-200 hover:bg-red-100 font-medium">
                  Cerrar Gestion Final
                </button>
              </td>
            </tr>
            <tr v-if="!todasLasGestiones.length"><td colspan="4" class="px-6 py-4 text-center text-gray-500">No hay gestiones</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Contenido de Materias -->
    <div v-if="activeTab === 'Materias'" class="space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Añadir Nueva Materia</h2>
        <form @submit.prevent="crearMateria" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Código</label>
            <input v-model="formMateria.codigo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="MAT-101">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombre de la Materia</label>
            <input v-model="formMateria.nombre" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </div>
          <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full md:w-auto">Guardar Materia</button>
          </div>
        </form>
      </div>
      <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="materia in materias" :key="materia.id">
              <td class="px-6 py-4 font-medium">{{ materia.codigo }}</td>
              <td class="px-6 py-4">{{ materia.nombre }}</td>
              <td class="px-6 py-4">
                <span :class="materia.activa ? 'text-green-600 bg-green-50 px-2 py-1 rounded text-xs font-semibold' : 'text-red-600 bg-red-50 px-2 py-1 rounded text-xs font-semibold'">
                  {{ materia.activa ? 'ACTIVA' : 'INACTIVA' }}
                </span>
              </td>
              <td class="px-6 py-4">
                <button
                  @click="cambiarEstadoMateria(materia)"
                  :class="materia.activa ? 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' : 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'"
                  class="text-sm px-3 py-1 rounded border font-medium"
                >
                  {{ materia.activa ? 'Inhabilitar' : 'Habilitar' }}
                </button>
              </td>
            </tr>
            <tr v-if="!materias.length"><td colspan="4" class="px-6 py-4 text-center text-gray-500">No hay materias</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Contenido de Aulas -->
    <div v-if="activeTab === 'Aulas'" class="space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-2">Catálogo fijo de aulas FICCT</h2>
        <p class="text-sm text-gray-600">Las aulas del Módulo 236 no se crean ni eliminan desde el sistema. La capacidad puede ajustarse segun la disponibilidad real de cada aula.</p>
      </div>
      <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ubicación</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacidad</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="aula in aulas" :key="aula.id">
              <td class="px-6 py-4 font-medium">{{ aula.codigo }}</td>
              <td class="px-6 py-4">{{ aula.nombre }}</td>
              <td class="px-6 py-4">{{ aula.ubicacion || '-' }}</td>
              <td class="px-6 py-4">
                <input
                  v-model.number="capacidadAulas[aula.id]"
                  type="number"
                  min="1"
                  max="500"
                  class="w-28 rounded-md border-gray-300 text-sm shadow-sm"
                >
                <span class="ml-2 text-sm text-gray-500">estudiantes</span>
              </td>
              <td class="px-6 py-4">
                <button
                  @click="actualizarCapacidadAula(aula)"
                  :disabled="capacidadAulas[aula.id] === aula.capacidad"
                  class="text-sm bg-blue-50 text-blue-700 px-3 py-1 rounded border border-blue-200 hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                >
                  Guardar
                </button>
              </td>
            </tr>
            <tr v-if="!aulas.length"><td colspan="5" class="px-6 py-4 text-center text-gray-500">No hay aulas</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Contenido de Grupos -->
    <div v-if="activeTab === 'Grupos'" class="space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Añadir Nuevo Grupo</h2>
        <form @submit.prevent="crearGrupo" class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Gestión Activa</label>
            <select v-model="formGrupo.gestion_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              <option value="" disabled>Cargando gestiones...</option>
              <option v-for="g in gestiones" :key="g.id" :value="g.id">{{ g.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Código</label>
            <input v-model="formGrupo.codigo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="G1">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Aula Base</label>
            <select v-model="formGrupo.aula_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              <option value="" disabled>Seleccione aula...</option>
              <option v-for="a in aulas" :key="a.id" :value="a.id">{{ a.codigo }} (aula {{ a.capacidad || CUPO_MAXIMO_GRUPO }}, grupo max {{ CUPO_MAXIMO_GRUPO }})</option>
            </select>
          </div>
          <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">Guardar Grupo</button>
          </div>
        </form>
      </div>
      <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gestión</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aula</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacidad</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="grupo in grupos" :key="grupo.id">
              <td class="px-6 py-4 font-bold text-blue-700">{{ grupo.codigo }}</td>
              <td class="px-6 py-4">{{ grupo.gestion?.nombre }}</td>
              <td class="px-6 py-4">{{ grupo.aula?.codigo }}</td>
              <td class="px-6 py-4">{{ grupo.cupo_maximo }}</td>
            </tr>
            <tr v-if="!grupos.length"><td colspan="4" class="px-6 py-4 text-center text-gray-500">No hay grupos</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Contenido de Docentes -->
    <div v-if="activeTab === 'Docentes'" class="space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Añadir Nuevo Docente</h2>
        <form @submit.prevent="crearDocente" class="grid grid-cols-1 md:grid-cols-6 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">CI</label>
            <input v-model="formDocente.ci" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="1234567">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombres</label>
            <input v-model="formDocente.nombres" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Apellidos</label>
            <input v-model="formDocente.apellidos" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Correo</label>
            <input v-model="formDocente.correo" required type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="docente@correo.com">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Telefono</label>
            <input v-model="formDocente.telefono" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="70000000">
          </div>
          <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">Guardar Docente</button>
          </div>
        </form>
      </div>
      <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CI</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre Completo</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Correo</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registro</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="docente in docentes" :key="docente.id">
              <td class="px-6 py-4 font-medium">{{ docente.ci || '-' }}</td>
              <td class="px-6 py-4">{{ docente.nombres }} {{ docente.apellidos }}</td>
              <td class="px-6 py-4">{{ docente.correo || '-' }}</td>
              <td class="px-6 py-4 text-sm text-blue-700 font-semibold">{{ docente.ci || '-' }}</td>
            </tr>
            <tr v-if="!docentes.length"><td colspan="4" class="px-6 py-4 text-center text-gray-500">No hay docentes</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Contenido de Asignación a Grupos -->
    <div v-if="activeTab === 'Asignación'" class="space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Asignar Materia a Grupo</h2>
        <form @submit.prevent="asignarMateria" class="grid grid-cols-1 md:grid-cols-7 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Grupo</label>
            <select v-model="formAsignacion.grupo_id" required @change="cargarMateriasGrupo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              <option value="" disabled>Seleccione grupo...</option>
              <option v-for="g in grupos" :key="g.id" :value="g.id">{{ g.codigo }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Materia</label>
            <select v-model="formAsignacion.materia_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              <option value="" disabled>Seleccione materia...</option>
              <option v-for="m in materiasActivas" :key="m.id" :value="m.id">{{ m.codigo }} - {{ m.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Docente (Opcional)</label>
            <select v-model="formAsignacion.docente_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              <option value="">Sin asignar</option>
              <option v-for="d in docentes" :key="d.id" :value="d.id">{{ d.nombres }} {{ d.apellidos }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Dia</label>
            <select v-model="formAsignacion.dia_semana" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              <option value="" disabled>Dia...</option>
              <option v-for="dia in diasSemana" :key="dia.id" :value="dia.id">{{ dia.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Inicio</label>
            <input v-model="formAsignacion.hora_inicio" required type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Fin</label>
            <input v-model="formAsignacion.hora_fin" required type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
          </div>
          <div class="flex items-end">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full" :disabled="!formAsignacion.grupo_id">Asignar</button>
          </div>
        </form>
      </div>
      
      <div v-if="formAsignacion.grupo_id" class="bg-white rounded shadow overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
            <h3 class="font-semibold text-gray-700">Materias del Grupo Seleccionado</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Materia</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Docente</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Horario</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="m in materiasGrupo" :key="m.id">
              <td class="px-6 py-4 font-bold">{{ m.codigo }} - {{ m.nombre }}</td>
              <td class="px-6 py-4">{{ m.docente_nombre || obtenerNombreDocente(m.docente_id) }}</td>
              <td class="px-6 py-4">{{ obtenerHorarioMateria(m) }}</td>
            </tr>
            <tr v-if="!materiasGrupo.length"><td colspan="3" class="px-6 py-4 text-center text-gray-500">No hay materias asignadas a este grupo</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Contenido de Asignacion Automatica -->
    <div v-if="activeTab === 'Automatica'" class="space-y-6">
      <div class="bg-white p-6 rounded shadow">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
          <div class="max-w-xl">
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Asignacion automatica de grupos y horarios</h2>
            <p class="text-sm text-gray-600">Se ejecuta cuando la gestion esta inhabilitada para nuevas inscripciones. La cantidad de grupos se calcula con CEIL(total de inscritos / 70). Las aulas FICCT son fijas y se reutilizan solo en horarios sin choque.</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-3">
            <select v-model="gestionAutomaticaId" class="rounded-md border-gray-300 shadow-sm min-w-56">
              <option value="" disabled>Seleccione gestion...</option>
              <option v-for="g in todasLasGestiones" :key="g.id" :value="g.id">{{ g.nombre }} - {{ g.estado }}</option>
            </select>
            <button @click="generarAsignacionAutomatica" :disabled="!gestionAutomaticaId || cargandoAsignacion" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
              Generar propuesta
            </button>
          </div>
        </div>
      </div>

      <div v-if="propuestaAutomatica" class="bg-white rounded shadow overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <h3 class="font-semibold text-gray-800">Propuesta generada</h3>
            <p class="text-sm text-gray-600">
              {{ propuestaAutomatica.total_inscripciones }} estudiantes, {{ propuestaAutomatica.total_grupos }} grupos.
            </p>
          </div>
          <button v-if="!propuestaAutomatica.errores.length" @click="confirmarAsignacionAutomatica" :disabled="cargandoAsignacion" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50">
            Confirmar asignacion
          </button>
        </div>

        <div v-if="propuestaAutomatica.errores.length" class="px-6 py-4 bg-red-50 border-b border-red-100">
          <p class="text-sm font-semibold text-red-700 mb-2">Errores</p>
          <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
            <li v-for="error in propuestaAutomatica.errores" :key="error">{{ error }}</li>
          </ul>
        </div>

        <div v-if="propuestaAutomatica.advertencias.length" class="px-6 py-4 bg-amber-50 border-b border-amber-100">
          <p class="text-sm font-semibold text-amber-800 mb-2">Advertencias</p>
          <ul class="list-disc list-inside text-sm text-amber-800 space-y-1">
            <li v-for="advertencia in propuestaAutomatica.advertencias" :key="advertencia">{{ advertencia }}</li>
          </ul>
        </div>

        <div class="divide-y divide-gray-200">
          <div v-for="grupo in propuestaAutomatica.grupos" :key="grupo.codigo" class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-4">
              <div>
                <h4 class="font-bold text-blue-700">{{ grupo.codigo }} - {{ grupo.nombre }}</h4>
                <p class="text-sm text-gray-600">Aula base: {{ grupo.aula_codigo }} | Estudiantes: {{ grupo.total_estudiantes }}</p>
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Materia</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Docente</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Horarios</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="materia in grupo.materias" :key="materia.materia_id">
                    <td class="px-4 py-3 font-semibold">{{ materia.materia_nombre }}</td>
                    <td class="px-4 py-3">{{ materia.docente_nombre }}</td>
                    <td class="px-4 py-3">
                      <div v-for="horario in materia.horarios" :key="`${materia.materia_id}-${horario.dia_semana}-${horario.hora_inicio}`">
                        {{ obtenerNombreDia(horario.dia_semana) }} {{ horario.hora_inicio }} - {{ horario.hora_fin }}
                        <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase">{{ horario.modalidad }}</span>
                        <span v-if="horario.aula_codigo" class="text-xs text-gray-500"> {{ horario.aula_codigo }}</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-if="!propuestaAutomatica.grupos.length" class="px-6 py-8 text-center text-gray-500">No hay grupos en la propuesta</div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
// [CU08], [CU13], [CU15] Gestión de parámetros, grupos, docentes y planificación horaria

import { computed, ref, onMounted } from 'vue';
import axios from 'axios';
import { useToast } from '../../api/toast';

const activeTab = ref('Gestiones');

const todasLasGestiones = ref([]);
const materias = ref([]);
const aulas = ref([]);
const capacidadAulas = ref({});
const grupos = ref([]);
const gestiones = ref([]);
const docentes = ref([]);
const materiasGrupo = ref([]);
const gestionAutomaticaId = ref('');
const propuestaAutomatica = ref(null);
const cargandoAsignacion = ref(false);
const CUPO_MAXIMO_GRUPO = 70;
const diasSemana = [
  { id: 1, nombre: 'Lunes' },
  { id: 2, nombre: 'Martes' },
  { id: 3, nombre: 'Miercoles' },
  { id: 4, nombre: 'Jueves' },
  { id: 5, nombre: 'Viernes' },
  { id: 6, nombre: 'Sabado' },
  { id: 7, nombre: 'Domingo' },
];

const formGestion = ref({ nombre: '', anio: '', periodo: '' });
const formMateria = ref({ codigo: '', nombre: '' });
const formAula = ref({ codigo: '', nombre: '', capacidad: '' });
const formGrupo = ref({ gestion_id: '', codigo: '', aula_id: '' });
const formDocente = ref({ ci: '', nombres: '', apellidos: '', correo: '', telefono: '' });
const formAsignacion = ref({ grupo_id: '', materia_id: '', docente_id: '', dia_semana: '', hora_inicio: '', hora_fin: '' });
const toast = useToast();
const materiasActivas = computed(() => materias.value.filter(materia => materia.activa));

const cargarTodasLasGestiones = async () => {
  try {
    const { data } = await axios.get('/api/gestiones');
    if (data.ok) todasLasGestiones.value = data.data.gestiones;
  } catch (e) {
    console.error('Error cargando gestiones', e);
  }
};

const crearGestion = async () => {
  try {
    const { data } = await axios.post('/api/gestiones', formGestion.value);
    if (data.ok) {
      toast.success('Gestion creada', 'La gestion fue registrada correctamente.');
      formGestion.value = { nombre: '', anio: '', periodo: '' };
      cargarTodasLasGestiones();
    }
  } catch (e) { toast.error('No se pudo crear', e.response?.data?.message || 'Error al crear gestion'); }
};

const habilitarGestion = async (id) => {
  const confirmado = await toast.confirm({
    title: 'Habilitar gestion',
    message: 'Esta gestion quedara activa y las demas se cerraran automaticamente.',
    confirmText: 'Habilitar',
  });

  if (!confirmado) return;

  try {
    const { data } = await axios.put(`/api/gestiones/${id}/habilitar`);
    if (data.ok) {
      toast.success('Gestion habilitada', data.message);
      cargarTodasLasGestiones();
      cargarGestiones();
    }
  } catch (e) { toast.error('No se pudo habilitar', e.response?.data?.message || 'Error al habilitar gestion'); }
};

const cerrarGestion = async (id) => {
  const confirmado = await toast.confirm({
    title: 'Cerrar inscripciones',
    message: 'La gestion dejara de recibir postulaciones y quedara habilitada para asignacion, horarios y evaluaciones.',
    confirmText: 'Cerrar inscripciones',
    tone: 'danger',
  });

  if (!confirmado) return;

  try {
    const { data } = await axios.put(`/api/gestiones/${id}/cerrar`);
    if (data.ok) {
      toast.success('Inscripciones cerradas', data.message);
      await cargarTodasLasGestiones();
      gestionAutomaticaId.value = id;
      activeTab.value = 'Automatica';
    }
  } catch (e) { toast.error('No se pudo cerrar', e.response?.data?.message || 'Error al cerrar gestion'); }
};

const cerrarGestionFinal = async (id) => {
  const confirmado = await toast.confirm({
    title: 'Cerrar gestion final',
    message: 'La gestion quedara solo para consulta administrativa y reportes. Los postulantes ya no podran ingresar a esa gestion.',
    confirmText: 'Cerrar definitivamente',
    tone: 'danger',
  });

  if (!confirmado) return;

  try {
    const { data } = await axios.put(`/api/gestiones/${id}/cerrar-final`);
    if (data.ok) {
      toast.success('Gestion cerrada', data.message);
      await cargarTodasLasGestiones();
    }
  } catch (e) { toast.error('No se pudo cerrar definitivamente', e.response?.data?.message || 'Error al cerrar gestion'); }
};

const cargarMaterias = async () => {
  const { data } = await axios.get('/api/materias');
  if (data.ok) materias.value = data.data.materias;
};

const cargarAulas = async () => {
  const { data } = await axios.get('/api/aulas');
  if (data.ok) {
    aulas.value = data.data.aulas;
    capacidadAulas.value = Object.fromEntries(aulas.value.map(aula => [aula.id, aula.capacidad]));
  }
};

const actualizarCapacidadAula = async (aula) => {
  const capacidad = Number(capacidadAulas.value[aula.id]);

  if (!Number.isInteger(capacidad) || capacidad < 1) {
    toast.error('Capacidad invalida', 'La capacidad debe ser un numero entero mayor a cero.');
    return;
  }

  try {
    const { data } = await axios.put(`/api/aulas/${aula.id}/capacidad`, { capacidad });
    if (data.ok) {
      toast.success('Capacidad actualizada', data.message);
      await cargarAulas();
      await cargarGrupos();
    }
  } catch (e) {
    toast.error('No se pudo actualizar', e.response?.data?.message || 'Error al actualizar capacidad');
  }
};

const cargarGrupos = async () => {
  const { data } = await axios.get('/api/grupos');
  if (data.ok) grupos.value = data.data.grupos;
};

const cargarGestiones = async () => {
  const { data } = await axios.get('/api/postulaciones/create');
  if (data.ok && data.data.gestion) {
    gestiones.value = [data.data.gestion];
    formGrupo.value.gestion_id = data.data.gestion.id;
  }
};

const crearMateria = async () => {
  try {
    const { data } = await axios.post('/api/materias', formMateria.value);
    if (data.ok) {
      toast.success('Materia guardada', 'La materia fue registrada correctamente.');
      formMateria.value = { codigo: '', nombre: '' };
      cargarMaterias();
    }
  } catch (e) { toast.error('No se pudo guardar', e.response?.data?.message || 'Error al crear materia'); }
};

const cambiarEstadoMateria = async (materia) => {
  const activa = !materia.activa;
  const confirmado = await toast.confirm({
    title: activa ? 'Habilitar materia' : 'Inhabilitar materia',
    message: activa
      ? `La materia ${materia.codigo} volvera a estar disponible para asignaciones.`
      : `La materia ${materia.codigo} dejara de estar disponible para asignaciones y horarios nuevos.`,
    confirmText: activa ? 'Habilitar' : 'Inhabilitar',
    tone: activa ? 'default' : 'danger',
  });

  if (!confirmado) return;

  try {
    const { data } = await axios.put(`/api/materias/${materia.id}/estado`, { activa });
    if (data.ok) {
      toast.success(activa ? 'Materia habilitada' : 'Materia inhabilitada', data.message);
      await cargarMaterias();
    }
  } catch (e) {
    toast.error('No se pudo actualizar', e.response?.data?.message || 'Error al cambiar estado de materia');
  }
};

const crearAula = async () => {
  try {
    const { data } = await axios.post('/api/aulas', formAula.value);
    if (data.ok) {
      toast.success('Aula guardada', 'El aula fue registrada correctamente.');
      formAula.value = { codigo: '', nombre: '', capacidad: '' };
      cargarAulas();
    }
  } catch (e) { toast.error('No se pudo guardar', e.response?.data?.message || 'Error al crear aula'); }
};

const crearGrupo = async () => {
  try {
    const aulaSeleccionada = aulas.value.find(a => a.id === formGrupo.value.aula_id);
    const { data } = await axios.post('/api/grupos', {
      ...formGrupo.value,
      cupo_maximo: Math.min(Number(aulaSeleccionada?.capacidad) || CUPO_MAXIMO_GRUPO, CUPO_MAXIMO_GRUPO)
    });
    if (data.ok) {
      toast.success('Grupo guardado', 'El grupo fue registrado correctamente.');
      formGrupo.value.codigo = '';
      cargarGrupos();
    }
  } catch (e) { toast.error('No se pudo guardar', e.response?.data?.message || 'Error al crear grupo'); }
};

const cargarDocentes = async () => {
  const { data } = await axios.get('/api/docentes');
  if (data.ok) docentes.value = data.data.docentes;
};

const crearDocente = async () => {
  try {
    const { data } = await axios.post('/api/docentes', formDocente.value);
    if (data.ok) {
      const docente = data.data.docente;
      const credenciales = data.data.credenciales;
      docentes.value = [docente, ...docentes.value.filter(d => d.id !== docente.id)];
      toast.success(
        'Docente creado',
        `${data.message}\nRegistro: ${credenciales.numero_registro}\nContrasena temporal: ${credenciales.password_temporal}`,
        { duration: 7000 }
      );
      formDocente.value = { ci: '', nombres: '', apellidos: '', correo: '', telefono: '' };
      await cargarDocentes();
    }
  } catch (e) { toast.error('No se pudo crear docente', e.response?.data?.message || 'Error al crear docente'); }
};

const cargarMateriasGrupo = async () => {
  if (!formAsignacion.value.grupo_id) return;
  const { data } = await axios.get(`/api/grupos/${formAsignacion.value.grupo_id}/materias`);
  if (data.ok) materiasGrupo.value = data.data.materias;
};

const asignarMateria = async () => {
  try {
    const payload = { ...formAsignacion.value };
    if (!payload.docente_id) delete payload.docente_id;
    const { data } = await axios.post(`/api/grupos/${payload.grupo_id}/materias`, payload);
    if (data.ok) {
      toast.success('Materia asignada', 'La materia fue asignada al grupo correctamente.');
      formAsignacion.value.materia_id = '';
      formAsignacion.value.docente_id = '';
      formAsignacion.value.dia_semana = '';
      formAsignacion.value.hora_inicio = '';
      formAsignacion.value.hora_fin = '';
      cargarMateriasGrupo();
    }
  } catch (e) { toast.error('No se pudo asignar', e.response?.data?.message || 'Error al asignar materia'); }
};

const generarAsignacionAutomatica = async () => {
  if (!gestionAutomaticaId.value) return;
  cargandoAsignacion.value = true;
  propuestaAutomatica.value = null;

  try {
    const { data } = await axios.post('/api/asignacion-automatica/generar', {
      gestion_id: gestionAutomaticaId.value,
    });
    propuestaAutomatica.value = data.data.propuesta;
  } catch (e) {
    propuestaAutomatica.value = e.response?.data?.data?.propuesta || null;
    toast.error('No se pudo generar propuesta', e.response?.data?.message || 'Error al generar propuesta');
  } finally {
    cargandoAsignacion.value = false;
  }
};

const confirmarAsignacionAutomatica = async () => {
  if (!gestionAutomaticaId.value) return;
  const confirmado = await toast.confirm({
    title: 'Confirmar asignacion automatica',
    message: 'Se guardaran grupos, estudiantes, materias, docentes y horarios.',
    confirmText: 'Confirmar asignacion',
  });

  if (!confirmado) return;

  cargandoAsignacion.value = true;
  try {
    const { data } = await axios.post('/api/asignacion-automatica/confirmar', {
      gestion_id: gestionAutomaticaId.value,
    });

    toast.success(
      'Asignacion confirmada',
      `${data.message}\nGrupos: ${data.data.resultado.grupos_creados}\nEstudiantes: ${data.data.resultado.estudiantes_asignados}`,
      { duration: 7000 }
    );
    propuestaAutomatica.value = null;
    await cargarTodasLasGestiones();
    await cargarGrupos();
  } catch (e) {
    toast.error('No se pudo confirmar', e.response?.data?.message || 'Error al confirmar asignacion');
  } finally {
    cargandoAsignacion.value = false;
  }
};
const obtenerNombreDocente = (docenteId) => {
  if (!docenteId) return 'Sin asignar';
  const doc = docentes.value.find(d => d.id === docenteId);
  return doc ? `${doc.nombres} ${doc.apellidos || ''}` : 'Desconocido';
};

const obtenerNombreDia = (diaId) => {
  return diasSemana.find(d => d.id === Number(diaId))?.nombre || 'Sin dia';
};

const obtenerHorarioMateria = (materiaGrupo) => {
  const horario = materiaGrupo.horarios?.[0];
  if (!horario) return 'Sin horario';

  return `${obtenerNombreDia(horario.dia_semana)} ${horario.hora_inicio} - ${horario.hora_fin}`;
};

onMounted(() => {
  cargarTodasLasGestiones();
  cargarMaterias();
  cargarAulas();
  cargarGrupos();
  cargarGestiones();
  cargarDocentes();
});
</script>
