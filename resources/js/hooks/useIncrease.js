import { useEffect, useRef, useState } from 'react';

/**
 * Son oshganini kuzatadi: oshgan zahoti `{ delta, id }` qaytaradi, `holdMs` dan keyin `null`.
 * Birinchi renderda va son kamayganda (masalan, yarim tunda nolga tushganda) hech narsa qaytarmaydi.
 * Ketma-ket oshishlar `holdMs` ichida jamlanadi ("+2 hozirgina").
 *
 * @param {number|null|undefined} value
 * @param {number} holdMs
 * @returns {{ delta: number, id: number } | null}
 */
export default function useIncrease(value, holdMs = 10000) {
    const previous = useRef(value);
    const [bump, setBump] = useState(null);

    useEffect(() => {
        const before = previous.current;
        previous.current = value;

        if (typeof before === 'number' && typeof value === 'number' && value > before) {
            setBump((current) => ({ delta: (current?.delta ?? 0) + (value - before), id: Date.now() }));
        }
    }, [value]);

    useEffect(() => {
        if (!bump) {
            return undefined;
        }

        const timer = window.setTimeout(() => setBump(null), holdMs);

        return () => window.clearTimeout(timer);
    }, [bump, holdMs]);

    return bump;
}
