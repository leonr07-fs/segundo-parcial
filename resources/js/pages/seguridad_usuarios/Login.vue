<script setup>
import { ref } from 'vue';
import { login } from '../../api/auth';

const emit = defineEmits(['login-success', 'navigate']);

const form = ref({
    email: '',
    password: '',
});
const submitting = ref(false);
const message = ref('');
const fieldErrors = ref({});

async function submitLogin() {
    submitting.value = true;
    message.value = '';
    fieldErrors.value = {};

    try {
        const payload = await login(form.value);
        emit('login-success', payload.data.user);
    } catch (error) {
        message.value = error.response?.data?.message ?? 'No se pudo iniciar sesion.';
        fieldErrors.value = error.response?.data?.errors ?? {};
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="grid min-h-screen lg:grid-cols-[1.1fr_0.9fr] w-full">
        <div class="flex items-center bg-gradient-to-br from-blue-950 to-blue-900 px-8 py-12 text-white sm:px-14 lg:px-20 relative overflow-hidden">
            <!-- Abstract circles for dynamic look -->
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-red-600 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
            
            <div class="max-w-2xl relative z-10">
                <p class="text-sm font-bold uppercase tracking-widest text-red-400 mb-2">Universidad Autónoma Gabriel René Moreno</p>
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-300">Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones</p>
                <h1 class="mt-6 text-4xl font-bold leading-tight sm:text-5xl drop-shadow-md">
                    Sistema de Admisión y Seguimiento Académico
                </h1>
                <p class="mt-5 max-w-xl text-lg leading-7 text-blue-100">
                    Acceso seguro para administradores, docentes y postulantes del proceso CUP.
                </p>
                <button
                    type="button"
                    class="mt-8 rounded-full bg-red-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition-all hover:bg-red-700 hover:scale-105 hover:shadow-red-500/30"
                    @click="emit('navigate', '/postulaciones/crear')"
                >
                    Registrar nueva postulación →
                </button>
            </div>
        </div>

        <div class="flex items-center justify-center px-6 py-12 bg-slate-50 relative">
            <!-- Decorative background elements -->
            <div class="absolute inset-0 z-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPgo8cmVjdCB3aWR0aD0iOCIgaGVpZ2h0PSI4IiBmaWxsPSIjZmZmZmZmIj48L3JlY3Q+CjxwYXRoIGQ9Ik0wIDBMOCA4Wk04IDBMMCA4WiIgc3Ryb2tlPSIjZTJlOGYwIiBzdHJva2Utd2lkdGg9IjEiPjwvcGF0aD4KPC9zdmc+')] opacity-50"></div>
            
            <form class="relative z-10 w-full max-w-md rounded-2xl border border-white/40 bg-white/80 p-8 shadow-[0_8px_30px_rgb(0,0,0,0.08)] backdrop-blur-md" @submit.prevent="submitLogin">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-blue-950">Iniciar sesión</h2>
                    <p class="mt-2 text-sm text-slate-500">Ingresa tus credenciales institucionales.</p>
                </div>

                <div v-if="message" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 shadow-sm flex items-start gap-3">
                    <svg class="h-5 w-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ message }}</span>
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-blue-900">Correo Electrónico</span>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                        required
                        placeholder="usuario@uagrm.edu.bo"
                    >
                    <span v-if="fieldErrors.email" class="mt-1 block text-xs text-red-600 font-medium">{{ fieldErrors.email[0] }}</span>
                </label>

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-blue-900">Contraseña</span>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                        required
                        placeholder="••••••••"
                    >
                    <span v-if="fieldErrors.password" class="mt-1 block text-xs text-red-600 font-medium">{{ fieldErrors.password[0] }}</span>
                </label>

                <button
                    type="submit"
                    class="mt-8 w-full rounded-xl bg-blue-800 px-4 py-3.5 text-sm font-bold text-white shadow-lg transition-all hover:bg-blue-900 hover:shadow-blue-900/30 disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:scale-100 flex justify-center items-center gap-2"
                    :disabled="submitting"
                >
                    <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ submitting ? 'Verificando...' : 'Entrar al Sistema' }}
                </button>
            </form>
        </div>
    </section>
</template>
