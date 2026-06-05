import { reactive } from 'vue';

let nextId = 1;

export const toastState = reactive({
    items: [],
    confirmation: null,
});

function push(type, title, message = '', options = {}) {
    const id = nextId++;
    const duration = options.duration ?? 4200;

    toastState.items.push({
        id,
        type,
        title,
        message,
    });

    if (duration > 0) {
        window.setTimeout(() => dismiss(id), duration);
    }

    return id;
}

export function dismiss(id) {
    const index = toastState.items.findIndex(item => item.id === id);

    if (index !== -1) {
        toastState.items.splice(index, 1);
    }
}

export function useToast() {
    return {
        success: (title, message = '', options = {}) => push('success', title, message, options),
        error: (title, message = '', options = {}) => push('error', title, message, options),
        warning: (title, message = '', options = {}) => push('warning', title, message, options),
        info: (title, message = '', options = {}) => push('info', title, message, options),
        confirm: ({
            title = 'Confirmar accion',
            message = 'Esta accion requiere confirmacion.',
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            tone = 'primary',
        } = {}) => new Promise(resolve => {
            toastState.confirmation = {
                mode: 'confirm',
                title,
                message,
                confirmText,
                cancelText,
                tone,
                resolve,
            };
        }),
        alert: ({
            title = 'Atencion',
            message = 'Revise la informacion antes de continuar.',
            confirmText = 'Aceptar',
            tone = 'danger',
        } = {}) => new Promise(resolve => {
            toastState.confirmation = {
                mode: 'alert',
                title,
                message,
                confirmText,
                tone,
                resolve,
            };
        }),
        prompt: ({
            title = 'Agregar observacion',
            message = 'Escriba la observacion para continuar.',
            confirmText = 'Guardar',
            cancelText = 'Cancelar',
            placeholder = 'Observacion...',
            defaultValue = '',
            tone = 'primary',
        } = {}) => new Promise(resolve => {
            toastState.confirmation = {
                mode: 'prompt',
                title,
                message,
                confirmText,
                cancelText,
                placeholder,
                value: defaultValue,
                tone,
                resolve,
            };
        }),
    };
}

export function resolveConfirmation(value) {
    if (!toastState.confirmation) {
        return;
    }

    toastState.confirmation.resolve(value);
    toastState.confirmation = null;
}

export function clearConfirmation(value = false) {
    if (!toastState.confirmation) {
        return;
    }

    toastState.confirmation.resolve(value);
    toastState.confirmation = null;
}

export function installNativeAlertBridge() {
    if (window.__cupAlertBridgeInstalled) {
        return;
    }

    window.__cupAlertBridgeInstalled = true;
    window.alert = (message = '') => {
        push('warning', 'Atencion', String(message), { duration: 6000 });
    };
}
