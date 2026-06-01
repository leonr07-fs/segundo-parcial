<script setup>
import { computed, onMounted, ref } from 'vue';
import { fetchAuthenticatedUser, logout } from './api/auth';

// PACKAGED VIEWS (VISTAS)
// 1. Seguridad y Usuarios
import Login from './pages/seguridad_usuarios/Login.vue';

// 2. Postulantes e Inscripción
import PostulacionForm from './pages/postulantes_inscripcion/PostulacionForm.vue';
import ValidacionBandeja from './pages/postulantes_inscripcion/ValidacionBandeja.vue';
import ValidacionDetalle from './pages/postulantes_inscripcion/ValidacionDetalle.vue';
import PagosBandeja from './pages/postulantes_inscripcion/PagosBandeja.vue';
import PagoForm from './pages/postulantes_inscripcion/PagoForm.vue';

// 3. Gestión Académica
import ImportarResultados from './pages/gestion_academica/ImportarResultados.vue';
import ValidacionAcademicaBandeja from './pages/gestion_academica/ValidacionAcademicaBandeja.vue';
import AsignacionCarreraBandeja from './pages/gestion_academica/AsignacionCarreraBandeja.vue';

// 4. Reportes y Exportaciones (Dashboards)
import DashboardAdmin from './pages/reportes_exportaciones/DashboardAdmin.vue';
import DashboardDocente from './pages/reportes_exportaciones/DashboardDocente.vue';
import DashboardPostulante from './pages/reportes_exportaciones/DashboardPostulante.vue';

const user = ref(null);
const loading = ref(true);

const roleLabels = {
    admin: 'Administrador General',
    docente: 'Docente',
    postulante: 'Postulante',
    autoridad: 'Autoridad Académica',
    coordinador: 'Coordinador Académico',
};

const currentPath = ref(window.location.pathname);

// Rutas simples (Simulando un Router)
const isLoginPage = computed(() => currentPath.value === '/login' || currentPath.value === '/');
const isPostulacionPage = computed(() => currentPath.value === '/postulaciones/crear');

// CU03: Rutas de admin (Validación Documental)
const isAdminValidacionBandeja = computed(() => currentPath.value === '/admin/validacion-documental');
const isAdminValidacionDetalleId = computed(() => {
    const match = currentPath.value.match(/^\/admin\/validacion-documental\/(\d+)$/);
    return match ? match[1] : null;
});

// CU04: Rutas de admin (Pagos)
const isAdminPagosBandeja = computed(() => currentPath.value === '/admin/pagos');
const isAdminPagoDetalleId = computed(() => {
    const match = currentPath.value.match(/^\/admin\/pagos\/(\d+)$/);
    return match ? match[1] : null;
});

// CU09: Importar Resultados
const isImportarResultadosPage = computed(() => currentPath.value === '/admin/evaluaciones/importar');

// CU10: Validaciones Academicas
const isValidacionesAcademicasPage = computed(() => currentPath.value === '/admin/validaciones-academicas');

// CU12: Asignaciones Carrera
const isAsignacionesCarreraPage = computed(() => currentPath.value === '/admin/asignaciones-carrera');

const isDashboardPage = computed(() => currentPath.value.includes('/dashboard'));

const roleLabel = computed(() => roleLabels[user.value?.role] ?? 'Usuario');
const hasAdminAccess = computed(() => user.value && ['admin', 'autoridad', 'coordinador'].includes(user.value.role));

onMounted(async () => {
    await loadSession();
});

async function loadSession() {
    loading.value = true;
    try {
        const payload = await fetchAuthenticatedUser();
        user.value = payload.data.user;

        if (isLoginPage.value) {
            navigateTo(user.value.dashboard_path);
        }
    } catch {
        user.value = null;
    } finally {
        loading.value = false;
    }
}

function navigateTo(path) {
    currentPath.value = path;
    window.history.pushState({}, '', path);
}

function handleLoginSuccess(authenticatedUser) {
    user.value = authenticatedUser;
    navigateTo(authenticatedUser.dashboard_path);
}

async function submitLogout() {
    await logout();
    user.value = null;
    navigateTo('/login');
}

// Handle browser back/forward navigation
window.addEventListener('popstate', () => {
    currentPath.value = window.location.pathname;
});
</script>

<template>
    <main class="min-h-screen">
        <section v-if="loading" class="flex min-h-screen items-center justify-center">
            <div class="rounded border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600 shadow-sm">
                Cargando sesion...
            </div>
        </section>

        <!-- PÁGINA: Formulario de postulación (público) -->
        <section v-else-if="isPostulacionPage" class="min-h-screen bg-slate-100">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700">CUP FICCT</p>
                        <h1 class="text-lg font-semibold text-slate-950">Registro de Postulación</h1>
                    </div>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="navigateTo('/login')"
                    >
                        Volver al inicio
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-6">
                <PostulacionForm @back="navigateTo('/login')" />
            </div>
        </section>

        <!-- PÁGINA: Login -->
        <Login
            v-else-if="!user"
            @login-success="handleLoginSuccess"
            @navigate="navigateTo"
        />

        <!-- PÁGINA: Dashboard (usuario autenticado) -->
        <template v-else-if="isDashboardPage">
            <DashboardAdmin
                v-if="hasAdminAccess"
                :user="user"
                :role-label="roleLabel"
                @logout="submitLogout"
                @navigate="navigateTo"
            />
            <DashboardDocente
                v-else-if="user.role === 'docente'"
                :user="user"
                :role-label="roleLabel"
                @logout="submitLogout"
                @navigate="navigateTo"
            />
            <DashboardPostulante
                v-else
                :user="user"
                :role-label="roleLabel"
                @logout="submitLogout"
                @navigate="navigateTo"
            />
        </template>

        <!-- PÁGINAS: CU03 - Validación Documental -->
        <section v-else-if="hasAdminAccess && (isAdminValidacionBandeja || isAdminValidacionDetalleId)" class="min-h-screen bg-slate-100">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700">Administración CUP</p>
                        <h1 class="text-lg font-semibold text-slate-950">Validación de Requisitos</h1>
                    </div>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="navigateTo(user.dashboard_path)"
                    >
                        Volver al Panel
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-6 py-8">
                <ValidacionBandeja v-if="isAdminValidacionBandeja" @navigate="navigateTo" />
                <ValidacionDetalle v-else-if="isAdminValidacionDetalleId" :inscripcion-id="isAdminValidacionDetalleId" @back="navigateTo('/admin/validacion-documental')" />
            </div>
        </section>

        <!-- PÁGINAS: CU04 - Pagos -->
        <section v-else-if="hasAdminAccess && (isAdminPagosBandeja || isAdminPagoDetalleId)" class="min-h-screen bg-slate-100">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Administración CUP</p>
                        <h1 class="text-lg font-semibold text-slate-950">Pagos e Inscripción</h1>
                    </div>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="navigateTo(user.dashboard_path)"
                    >
                        Volver al Panel
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-6 py-8">
                <PagosBandeja v-if="isAdminPagosBandeja" @navigate="navigateTo" />
                <PagoForm v-else-if="isAdminPagoDetalleId" :inscripcion-id="isAdminPagoDetalleId" @back="navigateTo('/admin/pagos')" />
            </div>
        </section>

        <!-- PÁGINA: CU09 - Importar Resultados -->
        <section v-else-if="hasAdminAccess && isImportarResultadosPage" class="min-h-screen bg-slate-100">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-700">Administración CUP</p>
                        <h1 class="text-lg font-semibold text-slate-950">Académico</h1>
                    </div>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="navigateTo(user.dashboard_path)"
                    >
                        Volver al Panel
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-6 py-8">
                <ImportarResultados @navigate="navigateTo" />
            </div>
        </section>

        <!-- PÁGINA: CU10 - Validaciones Académicas -->
        <section v-else-if="hasAdminAccess && isValidacionesAcademicasPage" class="min-h-screen bg-slate-100">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Administración CUP</p>
                        <h1 class="text-lg font-semibold text-slate-950">Supervisión Académica</h1>
                    </div>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="navigateTo(user.dashboard_path)"
                    >
                        Volver al Panel
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-6 py-8">
                <ValidacionAcademicaBandeja @navigate="navigateTo" />
            </div>
        </section>

        <!-- PÁGINA: CU12 - Asignaciones de Carrera -->
        <section v-else-if="hasAdminAccess && isAsignacionesCarreraPage" class="min-h-screen bg-slate-100">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-orange-700">Administración CUP</p>
                        <h1 class="text-lg font-semibold text-slate-950">Asignación de Cupos</h1>
                    </div>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="navigateTo(user.dashboard_path)"
                    >
                        Volver al Panel
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-6xl px-6 py-8">
                <AsignacionCarreraBandeja @navigate="navigateTo" />
            </div>
        </section>
    </main>
</template>
