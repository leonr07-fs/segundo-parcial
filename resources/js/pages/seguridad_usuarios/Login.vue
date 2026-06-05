<script setup>
// [CU01] Autenticación de usuario (iniciar/cerrar sesión) - Formulario e inicio de sesión frontend

import { ref } from 'vue';
import { login, requestPasswordReset, resetPassword } from '../../api/auth';

const emit = defineEmits(['login-success', 'navigate']);

const params = new URLSearchParams(window.location.search);
const form = ref({
    numero_registro: '',
    password: '',
});
const resetEmail = ref(params.get('email') || '');
const resetToken = ref(params.get('reset_token') || '');
const resetForm = ref({
    password: '',
    password_confirmation: '',
});
const mode = ref(resetEmail.value && resetToken.value ? 'reset' : 'login');
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

async function submitForgotPassword() {
    submitting.value = true;
    message.value = '';
    fieldErrors.value = {};

    try {
        const payload = await requestPasswordReset({ email: resetEmail.value });
        message.value = payload.message;
        if (payload.data?.reset_token) {
            resetToken.value = payload.data.reset_token;
            mode.value = 'reset';
        }
    } catch (error) {
        message.value = error.response?.data?.message ?? 'No se pudo enviar el enlace.';
        fieldErrors.value = error.response?.data?.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

async function submitResetPassword() {
    submitting.value = true;
    message.value = '';
    fieldErrors.value = {};

    try {
        const payload = await resetPassword({
            email: resetEmail.value,
            token: resetToken.value,
            ...resetForm.value,
        });
        message.value = payload.message;
        mode.value = 'login';
        form.value.password = '';
        resetForm.value = { password: '', password_confirmation: '' };
    } catch (error) {
        message.value = error.response?.data?.message ?? 'No se pudo cambiar la contrasena.';
        fieldErrors.value = error.response?.data?.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

function submitCurrentMode() {
    if (mode.value === 'login') return submitLogin();
    if (mode.value === 'forgot') return submitForgotPassword();
    return submitResetPassword();
}
</script>

<template>
    <section class="grid min-h-screen w-full lg:grid-cols-[1.1fr_0.9fr]">
        <div class="relative flex items-center overflow-hidden bg-gradient-to-br from-blue-950 to-blue-900 px-8 py-12 text-white sm:px-14 lg:px-20">
            <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-red-600 opacity-20 blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-blue-500 opacity-20 blur-3xl"></div>

            <div class="relative z-10 max-w-2xl">
                <p class="mb-2 text-sm font-bold uppercase tracking-widest text-red-400">Universidad Autonoma Gabriel Rene Moreno</p>
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-300">Facultad de Ingenieria en Ciencias de la Computacion y Telecomunicaciones</p>
                <h1 class="mt-6 text-4xl font-bold leading-tight drop-shadow-md sm:text-5xl">
                    Sistema de Admision y Seguimiento Academico
                </h1>
                <p class="mt-5 max-w-xl text-lg leading-7 text-blue-100">
                    Acceso seguro para administradores, docentes y postulantes del proceso CUP.
                </p>
                <button
                    type="button"
                    class="mt-8 rounded-full bg-red-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition-all hover:bg-red-700 hover:shadow-red-500/30"
                    @click="emit('navigate', '/postulaciones/crear')"
                >
                    Registrar nueva postulacion
                </button>
            </div>
        </div>

        <div class="relative flex items-center justify-center bg-slate-50 px-6 py-12">
            <form
                class="relative z-10 w-full max-w-md rounded-2xl border border-white/40 bg-white/80 p-8 shadow-[0_8px_30px_rgb(0,0,0,0.08)] backdrop-blur-md"
                @submit.prevent="submitCurrentMode"
            >
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-blue-950">
                        {{ mode === 'login' ? 'Iniciar sesion' : (mode === 'forgot' ? 'Recuperar contrasena' : 'Nueva contrasena') }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ mode === 'login' ? 'Ingresa tus credenciales institucionales.' : 'Usa el Gmail registrado en el sistema CUP.' }}
                    </p>
                </div>

                <div v-if="message" class="mb-6 flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 shadow-sm">
                    <span>{{ message }}</span>
                </div>

                <template v-if="mode === 'login'">
                    <label class="block">
                        <span class="text-sm font-semibold text-blue-900">Numero de Registro</span>
                        <input
                            v-model="form.numero_registro"
                            type="text"
                            autocomplete="username"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                            required
                            placeholder="Ej. 224051237"
                        >
                        <span v-if="fieldErrors.numero_registro" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.numero_registro[0] }}</span>
                    </label>

                    <label class="mt-5 block">
                        <span class="text-sm font-semibold text-blue-900">Contrasena</span>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                            required
                        >
                        <span v-if="fieldErrors.password" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.password[0] }}</span>
                    </label>
                </template>

                <template v-else-if="mode === 'forgot'">
                    <label class="block">
                        <span class="text-sm font-semibold text-blue-900">Gmail registrado</span>
                        <input
                            v-model="resetEmail"
                            type="email"
                            autocomplete="email"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                            required
                            placeholder="nombre@gmail.com"
                        >
                        <span v-if="fieldErrors.email" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.email[0] }}</span>
                    </label>
                </template>

                <template v-else>
                    <label class="block">
                        <span class="text-sm font-semibold text-blue-900">Gmail registrado</span>
                        <input
                            v-model="resetEmail"
                            type="email"
                            autocomplete="email"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                            required
                        >
                        <span v-if="fieldErrors.email" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.email[0] }}</span>
                    </label>
                    <label class="mt-5 block">
                        <span class="text-sm font-semibold text-blue-900">Nueva contrasena</span>
                        <input
                            v-model="resetForm.password"
                            type="password"
                            autocomplete="new-password"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                            required
                        >
                        <span v-if="fieldErrors.password" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.password[0] }}</span>
                    </label>
                    <label class="mt-5 block">
                        <span class="text-sm font-semibold text-blue-900">Confirmar contrasena</span>
                        <input
                            v-model="resetForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white/50 px-4 py-3 text-sm outline-none transition-all focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10"
                            required
                        >
                    </label>
                    <p class="mt-3 text-xs text-slate-500">
                        Minimo 8 caracteres con mayuscula, minuscula, numero y simbolo.
                    </p>
                </template>

                <button
                    type="submit"
                    class="mt-8 flex w-full items-center justify-center gap-2 rounded-xl bg-blue-800 px-4 py-3.5 text-sm font-bold text-white shadow-lg transition-all hover:bg-blue-900 hover:shadow-blue-900/30 disabled:cursor-not-allowed disabled:opacity-70"
                    :disabled="submitting"
                >
                    {{ submitting ? 'Procesando...' : (mode === 'login' ? 'Entrar al Sistema' : (mode === 'forgot' ? 'Enviar enlace' : 'Guardar nueva contrasena')) }}
                </button>

                <div class="mt-5 flex justify-center gap-3 text-sm">
                    <button
                        v-if="mode === 'login'"
                        type="button"
                        class="font-semibold text-blue-800 hover:text-blue-950"
                        @click="mode = 'forgot'; message = ''; fieldErrors = {}"
                    >
                        Olvide mi contrasena
                    </button>
                    <button
                        v-else
                        type="button"
                        class="font-semibold text-blue-800 hover:text-blue-950"
                        @click="mode = 'login'; message = ''; fieldErrors = {}"
                    >
                        Volver al login
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
