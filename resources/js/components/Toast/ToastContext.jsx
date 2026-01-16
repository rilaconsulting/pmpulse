import { createContext, useContext, useCallback, useState } from 'react';
import ToastContainer from './ToastContainer';

const ToastContext = createContext(null);

let toastId = 0;

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const addToast = useCallback(({ type = 'info', title, message, duration = 5000 }) => {
        const id = ++toastId;
        const toast = { id, type, title, message, visible: true };

        setToasts((prev) => [...prev, toast]);

        // Auto-dismiss after duration
        if (duration > 0) {
            setTimeout(() => {
                dismissToast(id);
            }, duration);
        }

        return id;
    }, []);

    const dismissToast = useCallback((id) => {
        // First set visible to false to trigger exit animation
        setToasts((prev) =>
            prev.map((t) => (t.id === id ? { ...t, visible: false } : t))
        );
        // Then remove after animation completes
        setTimeout(() => {
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, 200);
    }, []);

    const toast = useCallback({
        success: (message, title) => addToast({ type: 'success', title, message }),
        error: (message, title) => addToast({ type: 'error', title, message, duration: 8000 }),
        warning: (message, title) => addToast({ type: 'warning', title, message }),
        info: (message, title) => addToast({ type: 'info', title, message }),
    }, [addToast]);

    return (
        <ToastContext.Provider value={toast}>
            {children}
            <ToastContainer toasts={toasts} onDismiss={dismissToast} />
        </ToastContext.Provider>
    );
}

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToast must be used within a ToastProvider');
    }
    return context;
}
