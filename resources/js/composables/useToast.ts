import { ref } from 'vue';

export interface Toast {
    id: number;
    message: string;
    tone: 'info' | 'success' | 'warning' | 'danger';
    action?: { label: string; run: () => void };
}

const toasts = ref<Toast[]>([]);
let next = 1;

/** App-wide toasts (aria-live), with an optional action such as "Hoàn tác". */
export function useToast() {
    const dismiss = (id: number) => (toasts.value = toasts.value.filter((t) => t.id !== id));
    const show = (message: string, tone: Toast['tone'] = 'info', options: { action?: Toast['action']; seconds?: number } = {}) => {
        const id = next++;
        toasts.value.push({ id, message, tone, action: options.action });
        setTimeout(() => dismiss(id), (options.seconds ?? 5) * 1000);
        return id;
    };
    return { toasts, show, dismiss };
}
