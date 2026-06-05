<script setup>
import { ref } from 'vue';
import { changePassword } from '../api/auth';

const form = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const submitting = ref(false);
const message = ref('');
const error = ref('');
const fieldErrors = ref({});

async function submitChange() {
    submitting.value = true;
    message.value = '';
    error.value = '';
    fieldErrors.value = {};

    try {
        const payload = await changePassword(form.value);
        message.value = payload.message;
        form.value = {
            current_password: '',
            password: '',
            password_confirmation: '',
        };
    } catch (requestError) {
        error.value = requestError.response?.data?.message ?? 'No se pudo cambiar la contrasena.';
        fieldErrors.value = requestError.response?.data?.errors ?? {};
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <h3 class="text-sm font-bold text-blue-950">Cambiar contrasena</h3>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Usa minimo 8 caracteres con mayuscula, minuscula, numero y simbolo.
        </p>

        <div v-if="message" class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-2 text-xs font-medium text-emerald-700">
            {{ message }}
        </div>
        <div v-if="error" class="mt-3 rounded-md border border-red-200 bg-red-50 p-2 text-xs font-medium text-red-700">
            {{ error }}
        </div>

        <form class="mt-4 space-y-3" @submit.prevent="submitChange">
            <label class="block">
                <span class="text-xs font-semibold text-slate-700">Contrasena actual</span>
                <input
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    autocomplete="current-password"
                    required
                >
                <span v-if="fieldErrors.current_password" class="mt-1 block text-xs text-red-600">{{ fieldErrors.current_password[0] }}</span>
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-slate-700">Nueva contrasena</span>
                <input
                    v-model="form.password"
                    type="password"
                    class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    autocomplete="new-password"
                    required
                >
                <span v-if="fieldErrors.password" class="mt-1 block text-xs text-red-600">{{ fieldErrors.password[0] }}</span>
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-slate-700">Confirmar contrasena</span>
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    autocomplete="new-password"
                    required
                >
            </label>
            <button
                type="submit"
                class="w-full rounded-md bg-blue-800 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-900 disabled:opacity-60"
                :disabled="submitting"
            >
                {{ submitting ? 'Guardando...' : 'Actualizar contrasena' }}
            </button>
        </form>
    </section>
</template>
