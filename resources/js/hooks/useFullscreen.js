import { useEffect, useState } from 'react';

/**
 * Kiosk sahifalari uchun to‘liq ekran holati va almashtirgich.
 *
 * @returns {[boolean, () => void]}
 */
export default function useFullscreen() {
    const [active, setActive] = useState(false);

    useEffect(() => {
        const onChange = () => setActive(Boolean(document.fullscreenElement));

        document.addEventListener('fullscreenchange', onChange);

        return () => document.removeEventListener('fullscreenchange', onChange);
    }, []);

    const toggle = () => {
        if (document.fullscreenElement) {
            document.exitFullscreen?.();
        } else {
            document.documentElement.requestFullscreen?.().catch(() => {});
        }
    };

    return [active, toggle];
}
