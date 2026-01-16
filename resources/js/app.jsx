import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ToastProvider } from './components/Toast';

const appName = import.meta.env.VITE_APP_NAME || 'PMPulse';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
        return pages[`./pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <ToastProvider>
                <App {...props} />
            </ToastProvider>
        );
    },
    progress: {
        color: '#3B82F6',
    },
});
