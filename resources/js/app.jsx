import 'antd/dist/reset.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AdminThemeProvider from './Components/AdminThemeProvider';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Yangi `npm run build` dan keyin ochiq turgan ekran eski chunk'larni so‘raydi va oppoq qoladi —
// sahifani qayta yuklaymiz (10 soniyada ko‘pi bilan bir marta, cheksiz aylanib qolmasin).
window.addEventListener('vite:preloadError', (event) => {
    let last = 0;
    try {
        last = Number(sessionStorage.getItem('vite-preload-reload') || 0);
        sessionStorage.setItem('vite-preload-reload', String(Date.now()));
    } catch {
        // sessionStorage yopiq bo‘lsa ham qayta yuklash ishlaydi
    }
    if (Date.now() - last > 10000) {
        event.preventDefault();
        window.location.reload();
    }
});

const pages = import.meta.glob('./Pages/**/*.jsx');

// ngrok / sekin tarmoqda bitta chunk uzilsa ham sahifa oppoq qolmasin: 3 marta qayta urinamiz,
// baribir bo‘lmasa sahifani qayta yuklaymiz (yuqoridagi 10 soniyalik cheklov bilan).
async function resolvePage(name) {
    for (let attempt = 0; ; attempt++) {
        try {
            return await resolvePageComponent(`./Pages/${name}.jsx`, pages);
        } catch (error) {
            if (attempt >= 2) {
                window.dispatchEvent(new Event('vite:preloadError', { cancelable: true }));
                throw error;
            }
            await new Promise((resolve) => setTimeout(resolve, 1000 * (attempt + 1)));
        }
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: resolvePage,
    setup({ el, App, props }) {
        createRoot(el).render(
            <AdminThemeProvider>
                <App {...props} />
            </AdminThemeProvider>,
        );
    },
    progress: {
        color: '#228f5c',
        showSpinner: false,
    },
});
