// Monitor sahifalari uchun umumiy formatlash yordamchilari.

// Guruhlar (OAV, Homiy, Ishtirokchi…) bazadan keladi — rang tartib bo‘yicha navbatlanadi.
export const GROUP_TONES = ['blue', 'yellow', 'pink', 'green'];

export function initialsOf(name) {
    return String(name || '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('') || '?';
}

export function formatNumber(value) {
    return String(value ?? 0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

/** Ulush foizi — jami 0 bo‘lsa 0 (NaN/Infinity emas). */
export function share(part, total) {
    return total > 0 ? ((part ?? 0) / total) * 100 : 0;
}

export function formatPercent(part, total) {
    return `${share(part, total).toFixed(1)}%`;
}
