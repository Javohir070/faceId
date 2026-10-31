/** Ism yoki nomning bosh harflari: «Backend jamoasi» → «BJ», «HR» → «HR». */
export function initials(name = '') {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '—';
    }

    const letters = parts.length > 1
        ? Array.from(parts[0])[0] + Array.from(parts[1])[0]
        : Array.from(parts[0]).slice(0, 2).join('');

    return letters.toUpperCase();
}

const TONES = {
    brand: 'bg-brand-50 text-brand-700',
    neutral: 'border border-line bg-surface-muted text-ink-muted',
};

const SIZES = {
    sm: 'h-8 w-8 text-[11px]',
    md: 'h-9 w-9 text-xs',
    lg: 'h-11 w-11 text-sm',
};

/** Rasm o‘rniga bosh harfli belgi: guruhlar uchun kvadrat, odamlar uchun dumaloq. */
export default function InitialsAvatar({ name, tone = 'brand', size = 'md', round = false }) {
    return (
        <span
            aria-hidden="true"
            className={`inline-flex shrink-0 select-none items-center justify-center font-extrabold tracking-wide ${round ? 'rounded-full' : 'rounded-lg'} ${SIZES[size]} ${TONES[tone]}`}
        >
            {initials(name)}
        </span>
    );
}
