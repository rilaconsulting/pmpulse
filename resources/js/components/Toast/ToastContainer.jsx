import Toast from './Toast';

export default function ToastContainer({ toasts, onDismiss }) {
    return (
        <div
            aria-live="assertive"
            className="pointer-events-none fixed inset-0 flex items-end px-4 py-6 sm:items-start sm:p-6 z-[100]"
        >
            <div className="flex w-full flex-col items-center space-y-4 sm:items-end">
                {toasts.map((toast) => (
                    <Toast key={toast.id} toast={toast} onDismiss={onDismiss} />
                ))}
            </div>
        </div>
    );
}
