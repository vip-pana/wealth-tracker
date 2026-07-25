import { createContext, useContext } from 'react';

export type ToastType = 'success' | 'error';

/**
 * Imperatively push a toast onto the global stack rendered by AppLayout.
 * Use this for client-side events that don't carry an Inertia flash message
 * (e.g. a background job finishing while the user watches). Inertia flash
 * messages (flash.success / flash.error) are picked up automatically.
 */
export type PushToast = (message: string, type?: ToastType) => void;

const noop: PushToast = () => undefined;

export const ToastContext = createContext<PushToast>(noop);

export function useToast(): PushToast {
    return useContext(ToastContext);
}
