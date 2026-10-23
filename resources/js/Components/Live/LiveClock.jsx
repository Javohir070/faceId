import { useEffect, useState } from 'react';
import { MONTHS, WEEKDAYS } from '../../utils/uzDate';

function useNow() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 1000);

        return () => window.clearInterval(id);
    }, []);

    return now;
}

/** Monitor sahifalari (kirish monitori, tashrif statistikasi) sarlavhasidagi soat va sana. */
export default function LiveClock() {
    const now = useNow();
    const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const date = `${now.getDate()}-${MONTHS[now.getMonth()]}, ${now.getFullYear()} · ${WEEKDAYS[now.getDay()]}`;

    return (
        <div className="lm-clock">
            <p className="lm-clock-time">{time}</p>
            <p className="lm-clock-date">{date}</p>
        </div>
    );
}
