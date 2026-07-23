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

        const tick = () => {
            if (document.hidden) {
                return;
            }

            router.reload({
                only: onlyRef.current,
                preserveScroll: true,
                preserveState: true,
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
