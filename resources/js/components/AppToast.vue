<script setup>
import { computed } from 'vue';
import { clearConfirmation, dismiss, resolveConfirmation, toastState } from '../api/toast';

const toastStyles = {
    success: {
        border: 'border-emerald-200',
        bg: 'bg-emerald-50',
        icon: 'bg-emerald-600 text-white',
        title: 'text-emerald-950',
        text: 'text-emerald-700',
        mark: '✓',
    },
    error: {
        border: 'border-red-200',
        bg: 'bg-red-50',
        icon: 'bg-red-600 text-white',
        title: 'text-red-950',
        text: 'text-red-700',
        mark: '!',
    },
    warning: {
        border: 'border-amber-200',
        bg: 'bg-amber-50',
        icon: 'bg-amber-500 text-white',
        title: 'text-amber-950',
        text: 'text-amber-700',
        mark: '!',
    },
    info: {
        border: 'border-blue-200',
        bg: 'bg-blue-50',
        icon: 'bg-blue-600 text-white',
        title: 'text-blue-950',
        text: 'text-blue-700',
        mark: 'i',
    },
};

const confirmation = computed(() => toastState.confirmation);
const hasValidConfirmation = computed(() => {
    const mode = confirmation.value?.mode;
    return ['alert', 'confirm', 'prompt'].includes(mode);
});

function confirmButtonClass(tone) {
    return tone === 'danger'
        ? 'bg-red-600 text-white hover:bg-red-700'
        : 'bg-cyan-700 text-white hover:bg-cyan-800';
}

function cancelConfirmation() {
    clearConfirmation(false);
}
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 top-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3">
            <TransitionGroup
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <article
                    v-for="toast in toastState.items"
                    :key="toast.id"
                    class="pointer-events-auto rounded-lg border p-4 shadow-lg"
                    :class="[toastStyles[toast.type].border, toastStyles[toast.type].bg]"
                >
                    <div class="flex gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold" :class="toastStyles[toast.type].icon">
                            {{ toastStyles[toast.type].mark }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold" :class="toastStyles[toast.type].title">{{ toast.title }}</h3>
                            <p v-if="toast.message" class="mt-1 whitespace-pre-line break-words text-sm" :class="toastStyles[toast.type].text">{{ toast.message }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded px-2 text-lg leading-none text-slate-400 transition hover:bg-white/70 hover:text-slate-700"
                            aria-label="Cerrar notificacion"
                            @click="dismiss(toast.id)"
                        >
                            ×
                        </button>
                    </div>
                </article>
            </TransitionGroup>
        </div>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="hasValidConfirmation"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/40 px-4"
                @click.self="cancelConfirmation"
                @keydown.esc.window="cancelConfirmation"
            >
                <section class="pointer-events-auto w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-950">{{ confirmation.title }}</h2>
                        <button
                            type="button"
                            class="rounded px-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Cerrar mensaje"
                            @click="cancelConfirmation"
                        >
                            ×
                        </button>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ confirmation.message }}</p>

                    <textarea
                        v-if="confirmation.mode === 'prompt'"
                        v-model="confirmation.value"
                        class="mt-4 min-h-24 w-full rounded border border-slate-300 p-3 text-sm outline-none transition focus:border-cyan-700 focus:ring-2 focus:ring-cyan-100"
                        :placeholder="confirmation.placeholder"
                    />

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            v-if="confirmation.mode !== 'alert'"
                            type="button"
                            class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="resolveConfirmation(false)"
                        >
                            {{ confirmation.cancelText }}
                        </button>
                        <button
                            type="button"
                            class="rounded px-4 py-2 text-sm font-semibold transition"
                            :class="confirmButtonClass(confirmation.tone)"
                            @click="resolveConfirmation(confirmation.mode === 'prompt' ? confirmation.value : true)"
                        >
                            {{ confirmation.confirmText }}
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
