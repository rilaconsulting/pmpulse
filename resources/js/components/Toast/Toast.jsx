import { Transition } from '@headlessui/react';
import {
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';

const ICONS = {
    success: CheckCircleIcon,
    error: ExclamationCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon,
};

const STYLES = {
    success: 'bg-green-50 text-green-800 border-green-200',
    error: 'bg-red-50 text-red-800 border-red-200',
    warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
    info: 'bg-blue-50 text-blue-800 border-blue-200',
};

const ICON_STYLES = {
    success: 'text-green-500',
    error: 'text-red-500',
    warning: 'text-yellow-500',
    info: 'text-blue-500',
};

export default function Toast({ toast, onDismiss }) {
    const Icon = ICONS[toast.type] || ICONS.info;

    return (
        <Transition
            show={toast.visible}
            enter="transform ease-out duration-300 transition"
            enterFrom="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enterTo="translate-y-0 opacity-100 sm:translate-x-0"
            leave="transition ease-in duration-200"
            leaveFrom="opacity-100"
            leaveTo="opacity-0"
        >
            <div
                className={`pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg border shadow-lg ${STYLES[toast.type] || STYLES.info}`}
                role="alert"
            >
                <div className="p-4">
                    <div className="flex items-start">
                        <div className="flex-shrink-0">
                            <Icon className={`h-5 w-5 ${ICON_STYLES[toast.type] || ICON_STYLES.info}`} aria-hidden="true" />
                        </div>
                        <div className="ml-3 w-0 flex-1">
                            {toast.title && (
                                <p className="text-sm font-medium">{toast.title}</p>
                            )}
                            <p className={`text-sm ${toast.title ? 'mt-1 opacity-90' : ''}`}>
                                {toast.message}
                            </p>
                        </div>
                        <div className="ml-4 flex flex-shrink-0">
                            <button
                                type="button"
                                className="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 hover:opacity-70"
                                onClick={() => onDismiss(toast.id)}
                            >
                                <span className="sr-only">Close</span>
                                <XMarkIcon className="h-5 w-5" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    );
}
