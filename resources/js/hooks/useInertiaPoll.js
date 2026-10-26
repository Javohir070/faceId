import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Inertia partial reload polling — sahifa ochiq bo‘lganda props ni yangilaydi.
 *
 * @param {string[]} only
 * @param {number} intervalMs
 * @param {boolean} enabled
 */
export default function useInertiaPoll(only, intervalMs = 10000, enabled = true) {
    const onlyRef = useRef(only);
    onlyRef.current = only;

    useEffect(() => {
        if (!enabled || intervalMs < 1000) {
            return undefined;
        }

        // Sekin tarmoqda javob intervaldan kech kelsa, yangi reload eskisini bekor qilib, sahifa umuman
        // yangilanmay qolardi — oldingi so‘rov tugamaguncha yangisi yuborilmaydi.
        // 30 soniyadan ko‘p osilib qolgan so‘rov esa tashlab yuboriladi (yangi reload uni bekor qiladi).
        let inFlightSince = 0;

        const tick = () => {
            if (document.hidden || (inFlightSince && Date.now() - inFlightSince < 30000)) {
                return;
            }

            inFlightSince = Date.now();

            router.reload({
                only: onlyRef.current,
                preserveScroll: true,
                preserveState: true,
                // ngrok/internet bir zum uzilsa, uning xato sahifasi ekran ustida modal bo‘lib qolmasin —
                // eski ma’lumot turaveradi, keyingi tikda qayta urinamiz.
                onHttpException: () => false,
                onNetworkError: () => false,
                onFinish: () => {
                    inFlightSince = 0;
                },
            });
        };

        const id = window.setInterval(tick, intervalMs);

        const onVisible = () => {
            if (!document.hidden) {
                tick();
            }
        };

        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.clearInterval(id);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [intervalMs, enabled]);
}
