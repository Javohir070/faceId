import { useState } from 'react';
import useIncrease from '../../hooks/useIncrease';
import { formatNumber } from '../../utils/liveFormat';

/**
 * Figma "odometer": son oshganda eskisi yuqoriga chiqib ketadi, yangisi pastdan kiradi.
 * Birinchi renderda va kamayishda animatsiya yo‘q.
 */
export default function AnimatedNumber({ value }) {
    const [display, setDisplay] = useState({ value, previous: null, key: 0 });

    // Prop o‘zgarganini render paytida aniqlash (React tavsiya qilgan "derived state" usuli).
    if (value !== display.value) {
        const grew = typeof value === 'number' && typeof display.value === 'number' && value > display.value;

        setDisplay({
            value,
            previous: grew ? display.value : null,
            key: grew ? display.key + 1 : display.key,
        });
    }

    return (
        <span className="lm-odometer">
            {display.previous !== null ? (
                <span key={`prev-${display.key}`} className="lm-odometer-prev" aria-hidden="true">
                    {formatNumber(display.previous)}
                </span>
            ) : null}
            <span key={`value-${display.key}`} className={display.key > 0 ? 'lm-odometer-in' : undefined}>
                {formatNumber(display.value)}
            </span>
        </span>
    );
}

/** Son oshganda kartaning ramkasi bir marta yorishadi. Ota element `position: relative` bo‘lishi kerak. */
export function BumpGlow({ value }) {
    const bump = useIncrease(value, 1600);

    return bump ? <span key={bump.id} className="lm-glow" aria-hidden="true" /> : null;
}
